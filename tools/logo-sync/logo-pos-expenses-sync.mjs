#!/usr/bin/env node

import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";

import dotenv from "dotenv";
import sql from "mssql";

import { logoFirmTable, logoPeriodTable } from "./logo-table-names.mjs";

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const envPath = path.join(scriptDir, ".env");

if (fs.existsSync(envPath)) {
  dotenv.config({ path: envPath });
}

const config = buildConfig();
const startedAt = Date.now();

main().catch((error) => {
  console.error("[logo-sync] failed:", error instanceof Error ? error.message : error);
  process.exitCode = 1;
});

async function main() {
  validateConfig(config);

  console.log(
    `[logo-sync] connecting to ${config.logo.server}${config.logo.instanceName ? `\\${config.logo.instanceName}` : ""}/${config.logo.database}`
  );

  const pool = new sql.ConnectionPool(config.logo.connection);
  await pool.connect();

  try {
    const rows = await fetchLogoExpenses(pool, config);
    const records = rows.map((row) => mapExpenseRow(row, config)).filter(Boolean);

    console.log(`[logo-sync] fetched ${records.length} Batum POS expense(s) from Logo`);

    if (records.length > 0) {
      const summary = await sendExpenses(config, records);
      console.log("[logo-sync] sync response:", JSON.stringify(summary));
    }

    console.log(`[logo-sync] completed duration=${Date.now() - startedAt}ms`);
  } finally {
    await pool.close();
  }
}

function buildConfig() {
  const timeoutMs = parseInteger(process.env.LOGO_SQL_REQUEST_TIMEOUT_MS, 30000);
  const port = parseInteger(process.env.LOGO_SQL_PORT, undefined);
  const syncUrl =
    nullable(process.env.POWERSA_POS_EXPENSES_SYNC_URL) ??
    derivePosExpensesSyncUrl(process.env.POWERSA_SYNC_URL);
  const syncKey = (
    process.env.POWERSA_POS_EXPENSES_SYNC_KEY ??
    process.env.POWERSA_COLLECTIONS_SYNC_KEY ??
    process.env.POWERSA_SYNC_KEY ??
    ""
  ).trim();

  return {
    logo: {
      server: (process.env.LOGO_SQL_SERVER ?? "").trim(),
      instanceName: nullable(process.env.LOGO_SQL_INSTANCE),
      port,
      database: (process.env.LOGO_SQL_DATABASE ?? "").trim(),
      user: (process.env.LOGO_SQL_USER ?? "").trim(),
      password: process.env.LOGO_SQL_PASSWORD ?? "",
      cashLineTable: nullable(process.env.LOGO_CASH_LINE_TABLE) ?? logoPeriodTable("KSLINES"),
      cashboxTable: nullable(process.env.LOGO_CASHBOX_TABLE) ?? logoFirmTable("KSCARD"),
      connection: {
        server: (process.env.LOGO_SQL_SERVER ?? "").trim(),
        database: (process.env.LOGO_SQL_DATABASE ?? "").trim(),
        user: (process.env.LOGO_SQL_USER ?? "").trim(),
        password: process.env.LOGO_SQL_PASSWORD ?? "",
        pool: {
          max: 4,
          min: 0,
          idleTimeoutMillis: 30000,
        },
        options: {
          encrypt: parseBoolean(process.env.LOGO_SQL_ENCRYPT, false),
          trustServerCertificate: parseBoolean(process.env.LOGO_SQL_TRUST_SERVER_CERTIFICATE, true),
          instanceName: nullable(process.env.LOGO_SQL_INSTANCE),
        },
        requestTimeout: timeoutMs,
      },
    },
    sync: {
      url: syncUrl ?? "",
      key: syncKey,
      dealerId: parseInteger(process.env.POWERSA_DEALER_ID, undefined),
      dealerCode: nullable(process.env.POWERSA_DEALER_CODE),
      cashboxCode: nullable(process.env.POWERSA_POS_EXPENSES_CASHBOX_CODE ?? process.env.LOGO_POS_EXPENSE_CASHBOX_CODE),
      cashboxName: nullable(process.env.POWERSA_POS_EXPENSES_CASHBOX_NAME ?? process.env.LOGO_POS_EXPENSE_CASHBOX_NAME),
      cashboxNameLike: nullable(process.env.POWERSA_POS_EXPENSES_CASHBOX_NAME_LIKE ?? process.env.LOGO_POS_EXPENSE_CASHBOX_NAME_LIKE) ?? "BATUM",
      sinceDate: nullable(process.env.POWERSA_POS_EXPENSES_SINCE_DATE),
      days: parseInteger(process.env.POWERSA_POS_EXPENSES_IMPORT_DAYS, 7),
      limit: parseInteger(process.env.POWERSA_POS_EXPENSES_IMPORT_LIMIT, 250),
    },
  };
}

function validateConfig(currentConfig) {
  const missing = [];

  if (!currentConfig.logo.server) missing.push("LOGO_SQL_SERVER");
  if (!currentConfig.logo.database) missing.push("LOGO_SQL_DATABASE");
  if (!currentConfig.logo.user) missing.push("LOGO_SQL_USER");
  if (!currentConfig.logo.password) missing.push("LOGO_SQL_PASSWORD");
  if (!currentConfig.sync.url) missing.push("POWERSA_POS_EXPENSES_SYNC_URL or POWERSA_SYNC_URL");
  if (!currentConfig.sync.key) missing.push("POWERSA_POS_EXPENSES_SYNC_KEY or POWERSA_SYNC_KEY");

  if (missing.length > 0) {
    throw new Error(`missing required config: ${missing.join(", ")}`);
  }

  assertSafeTableName(currentConfig.logo.cashLineTable, "LOGO_CASH_LINE_TABLE");
  assertSafeTableName(currentConfig.logo.cashboxTable, "LOGO_CASHBOX_TABLE");

  if (currentConfig.logo.port !== undefined) {
    currentConfig.logo.connection.port = currentConfig.logo.port;
  }
}

async function fetchLogoExpenses(pool, currentConfig) {
  const request = pool.request();
  request.input("limit", sql.Int, currentConfig.sync.limit);
  request.input("cashboxCode", sql.NVarChar(64), currentConfig.sync.cashboxCode);
  request.input("cashboxName", sql.NVarChar(255), currentConfig.sync.cashboxName);
  request.input("cashboxNameLike", sql.NVarChar(255), currentConfig.sync.cashboxNameLike);
  request.input("sinceDate", sql.Date, currentConfig.sync.sinceDate ? new Date(currentConfig.sync.sinceDate) : null);
  request.input("days", sql.Int, currentConfig.sync.days);

  const result = await request.query(`
    DECLARE @MinDate DATE = COALESCE(@sinceDate, DATEADD(DAY, -@days, CONVERT(DATE, GETDATE())));

    SELECT TOP (@limit)
      L.LOGICALREF AS external_id,
      L.DATE_ AS expense_date,
      L.FICHENO AS reference_no,
      L.DOCODE AS document_no,
      L.SPECODE AS category,
      L.CYPHCODE AS cyph_code,
      L.LINEEXP AS note,
      L.AMOUNT AS amount,
      K.CODE AS cashbox_code,
      K.NAME AS cashbox_name
    FROM ${currentConfig.logo.cashLineTable} L WITH (NOLOCK)
    LEFT JOIN ${currentConfig.logo.cashboxTable} K WITH (NOLOCK) ON K.LOGICALREF = L.CARDREF
    WHERE ISNULL(L.CANCELLED, 0) = 0
      AND L.TRCODE = 12
      AND L.DATE_ >= @MinDate
      AND (
        (@cashboxCode IS NOT NULL AND K.CODE = @cashboxCode)
        OR (@cashboxName IS NOT NULL AND K.NAME = @cashboxName)
        OR (@cashboxCode IS NULL AND @cashboxName IS NULL AND @cashboxNameLike IS NOT NULL AND K.NAME LIKE '%' + @cashboxNameLike + '%')
      )
    ORDER BY L.DATE_ DESC, L.LOGICALREF DESC;
  `);

  return result.recordset ?? [];
}

function mapExpenseRow(row, currentConfig) {
  const amount = Number.parseFloat(String(row.amount ?? 0));
  const externalId = normalizeString(row.external_id);

  if (!Number.isFinite(amount) || amount <= 0 || !externalId) {
    return null;
  }

  const cashboxCode = normalizeString(row.cashbox_code) ?? currentConfig.sync.cashboxCode;
  const cashboxName = normalizeString(row.cashbox_name) ?? currentConfig.sync.cashboxName;
  const category = normalizeString(row.category) ?? normalizeString(row.cyph_code) ?? "Logo Masraf";
  const note = normalizeString(row.note);
  const referenceNo = normalizeString(row.reference_no) ?? normalizeString(row.document_no);

  return {
    external_ref: `KSLINES-${externalId}`,
    expense_date: formatDate(row.expense_date),
    category,
    amount: Number(amount.toFixed(2)),
    currency: "GEL",
    note,
    reference_no: referenceNo,
    cashbox_code: cashboxCode,
    cashbox_name: cashboxName,
    dealer_id: currentConfig.sync.dealerId,
    dealer_code: currentConfig.sync.dealerCode,
    meta: {
      logo_table: currentConfig.logo.cashLineTable,
      logo_cashbox_table: currentConfig.logo.cashboxTable,
      logicalref: externalId,
      document_no: normalizeString(row.document_no),
      cyph_code: normalizeString(row.cyph_code),
    },
  };
}

async function sendExpenses(currentConfig, records) {
  const body = {
    dealer_id: currentConfig.sync.dealerId,
    dealer_code: currentConfig.sync.dealerCode,
    cashbox_code: currentConfig.sync.cashboxCode,
    cashbox_name: currentConfig.sync.cashboxName,
    records,
  };

  const response = await fetch(currentConfig.sync.url, {
    method: "POST",
    headers: {
      accept: "application/json",
      "content-type": "application/json",
      "x-integration-key": currentConfig.sync.key,
    },
    body: JSON.stringify(body),
  });

  if (!response.ok) {
    const contentType = response.headers.get("content-type") ?? "";
    const responseBody = contentType.includes("application/json")
      ? JSON.stringify(await response.json())
      : await response.text();

    throw new Error(`POS expense sync endpoint returned ${response.status}: ${responseBody}`);
  }

  return response.json();
}

function derivePosExpensesSyncUrl(baseUrl) {
  const normalized = nullable(baseUrl);
  if (!normalized) {
    return null;
  }

  return normalized.replace(/\/customers\/sync$/i, "/pos-expenses/sync");
}

function assertSafeTableName(value, label) {
  if (!/^[A-Za-z0-9_.\[\]]+$/.test(value)) {
    throw new Error(`${label} contains unsupported characters`);
  }
}

function formatDate(value) {
  if (value instanceof Date && !Number.isNaN(value.getTime())) {
    return value.toISOString().slice(0, 10);
  }

  const parsed = new Date(value);
  if (!Number.isNaN(parsed.getTime())) {
    return parsed.toISOString().slice(0, 10);
  }

  return String(value).slice(0, 10);
}

function normalizeString(value) {
  if (value === null || value === undefined) {
    return null;
  }

  const normalized = String(value).trim();
  return normalized === "" ? null : normalized;
}

function parseInteger(value, fallback) {
  if (value === undefined || value === null || String(value).trim() === "") {
    return fallback;
  }

  const parsed = Number.parseInt(String(value).trim(), 10);
  return Number.isFinite(parsed) ? parsed : fallback;
}

function parseBoolean(value, fallback) {
  if (value === undefined || value === null || String(value).trim() === "") {
    return fallback;
  }

  const normalized = String(value).trim().toLowerCase();
  if (["1", "true", "yes", "evet", "on"].includes(normalized)) return true;
  if (["0", "false", "no", "hayir", "off"].includes(normalized)) return false;
  return fallback;
}

function nullable(value) {
  const normalized = normalizeString(value);
  return normalized === null ? null : normalized;
}
