#!/usr/bin/env node

import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";

import dotenv from "dotenv";
import sql from "mssql";

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const envPath = path.join(scriptDir, ".env");

if (fs.existsSync(envPath)) {
  dotenv.config({ path: envPath });
}

const firmNo = String(process.env.LOGO_FIRM_NO ?? "003").padStart(3, "0");
const periodNo = String(process.env.LOGO_PERIOD_NO ?? "01").padStart(2, "0");

const tableNames = {
  customers: firmTable("CLCARD"),
  items: firmTable("ITEMS"),
  cashboxes: firmTable("KSCARD"),
  orders: periodTable("ORFICHE"),
  orderLines: periodTable("ORFLINE"),
  stockFiches: periodTable("STFICHE"),
  stockLines: periodTable("STLINE"),
  invoices: periodTable("INVOICE"),
  cashLines: periodTable("KSLINES"),
  ledgerLines: periodTable("CLFLINE"),
  paytrans: periodTable("PAYTRANS"),
};

const procedureContracts = [
  "dbo.PowersaB2B_ExportCustomer",
  "dbo.PowersaB2B_ExportCollection",
  "dbo.PowersaB2B_ExportOrder",
  "dbo.PowersaB2B_ExportShipment",
  "dbo.PowersaB2B_ExportPosSale",
  "dbo.PowersaB2B_ExportPosExpense",
  "dbo.PowersaB2B_ExportReturn",
  "dbo.PowersaB2B_ExportReturnScrap",
];

const latestColumnPreferences = [
  "LOGICALREF",
  "FICHENO",
  "NUMBER",
  "DATE_",
  "TRCODE",
  "GRPCODE",
  "CLIENTREF",
  "CARDREF",
  "STOCKREF",
  "SOURCEINDEX",
  "SOURCECOSTGRP",
  "DESTINDEX",
  "DESTCOSTGRP",
  "AMOUNT",
  "PRICE",
  "TOTAL",
  "NETTOTAL",
  "GROSSTOTAL",
  "VAT",
  "VATAMNT",
  "LINEEXP",
  "GENEXP1",
  "GENEXP2",
  "SPECODE",
  "CYPHCODE",
  "CANCELLED",
];

main().catch((error) => {
  console.error("[logo-write-preflight] failed:", error instanceof Error ? error.message : error);
  process.exitCode = 1;
});

async function main() {
  validateConfig();

  const pool = await sql.connect({
    server: process.env.LOGO_SQL_SERVER,
    database: process.env.LOGO_SQL_DATABASE,
    user: process.env.LOGO_SQL_USER,
    password: process.env.LOGO_SQL_PASSWORD,
    port: process.env.LOGO_SQL_PORT ? Number(process.env.LOGO_SQL_PORT) : undefined,
    options: {
      encrypt: parseBool(process.env.LOGO_SQL_ENCRYPT, false),
      trustServerCertificate: parseBool(process.env.LOGO_SQL_TRUST_SERVER_CERTIFICATE, true),
      instanceName: nullable(process.env.LOGO_SQL_INSTANCE),
    },
    requestTimeout: Number(process.env.LOGO_SQL_REQUEST_TIMEOUT_MS ?? 30000),
  });

  try {
    const report = {
      firm_no: firmNo,
      period_no: periodNo,
      generated_at: new Date().toISOString(),
      configured_procedures: await inspectProcedures(pool),
      export_log: await inspectExportLog(pool),
      tables: {},
      latest_samples: {},
      write_readiness: [],
    };

    for (const [key, tableName] of Object.entries(tableNames)) {
      report.tables[key] = await inspectTable(pool, tableName);
      report.latest_samples[key] = await latestRows(pool, tableName, report.tables[key].columns ?? []);
    }

    report.write_readiness = buildReadiness(report);
    console.log(JSON.stringify(report, null, 2));
  } finally {
    await pool.close();
  }
}

function validateConfig() {
  const missing = [
    "LOGO_SQL_SERVER",
    "LOGO_SQL_DATABASE",
    "LOGO_SQL_USER",
    "LOGO_SQL_PASSWORD",
  ].filter((key) => !nullable(process.env[key]));

  if (missing.length > 0) {
    throw new Error(`missing required config: ${missing.join(", ")}`);
  }
}

async function inspectProcedures(pool) {
  const result = {};

  for (const procedureName of procedureContracts) {
    const parameters = await pool
      .request()
      .input("procedureName", sql.NVarChar(256), procedureName)
      .query(`
        SELECT
          p.name,
          TYPE_NAME(p.user_type_id) AS type_name,
          p.max_length,
          p.precision,
          p.scale,
          p.is_output
        FROM sys.parameters p
        WHERE p.object_id = OBJECT_ID(@procedureName)
        ORDER BY p.parameter_id;
      `);

    result[procedureName] = {
      exists: (parameters.recordset ?? []).length > 0,
      parameters: parameters.recordset ?? [],
    };
  }

  return result;
}

async function inspectExportLog(pool) {
  const exists = await objectExists(pool, "dbo.POWERSA_B2B_EXPORT_LOG", "U");

  if (!exists) {
    return { exists: false };
  }

  const summary = await pool.request().query(`
    SELECT DOCUMENT_TYPE, STATUS, COUNT(*) AS total, MAX(UPDATED_AT) AS last_updated_at
    FROM dbo.POWERSA_B2B_EXPORT_LOG
    GROUP BY DOCUMENT_TYPE, STATUS
    ORDER BY DOCUMENT_TYPE, STATUS;
  `);

  const latest = await pool.request().query(`
    SELECT TOP 20 ID, EXPORT_KEY, DOCUMENT_TYPE, EXTERNAL_REF, STATUS, ERROR_MESSAGE, UPDATED_AT
    FROM dbo.POWERSA_B2B_EXPORT_LOG
    ORDER BY ID DESC;
  `);

  return {
    exists: true,
    summary: summary.recordset ?? [],
    latest: latest.recordset ?? [],
  };
}

async function inspectTable(pool, tableName) {
  const [schema, table] = splitTable(tableName);
  const columns = await pool
    .request()
    .input("schema", sql.NVarChar(128), schema)
    .input("table", sql.NVarChar(128), table)
    .query(`
      SELECT
        c.name,
        TYPE_NAME(c.user_type_id) AS type_name,
        c.max_length,
        c.precision,
        c.scale,
        c.is_nullable,
        c.is_identity,
        c.is_computed,
        dc.definition AS default_definition
      FROM sys.columns c
      LEFT JOIN sys.default_constraints dc
        ON dc.parent_object_id = c.object_id
       AND dc.parent_column_id = c.column_id
      WHERE c.object_id = OBJECT_ID(@schema + N'.' + @table)
      ORDER BY c.column_id;
    `);

  const columnRows = columns.recordset ?? [];
  if (columnRows.length === 0) {
    return { table: tableName, exists: false };
  }

  const count = await pool.request().query(`SELECT COUNT(*) AS total FROM ${quoteTable(schema, table)};`);

  return {
    table: tableName,
    exists: true,
    rows: count.recordset?.[0]?.total ?? null,
    columns: columnRows,
  };
}

async function latestRows(pool, tableName, columns) {
  if (!Array.isArray(columns) || columns.length === 0) {
    return [];
  }

  const [schema, table] = splitTable(tableName);
  const columnNames = new Set(columns.map((column) => String(column.name).toUpperCase()));
  const selectedColumns = latestColumnPreferences.filter((column) => columnNames.has(column));

  if (selectedColumns.length === 0) {
    return [];
  }

  const orderColumn = columnNames.has("LOGICALREF") ? "LOGICALREF" : selectedColumns[0];
  const select = selectedColumns.map((column) => `[${column}]`).join(", ");

  return (
    await pool.request().query(`
      SELECT TOP 10 ${select}
      FROM ${quoteTable(schema, table)}
      ORDER BY [${orderColumn}] DESC;
    `)
  ).recordset ?? [];
}

function buildReadiness(report) {
  const checks = [];
  const table = (key) => report.tables[key];
  const hasColumns = (key, required) => {
    const columns = new Set((table(key)?.columns ?? []).map((column) => String(column.name).toUpperCase()));
    return required.every((column) => columns.has(column));
  };

  checks.push(check("orders_tables", table("orders")?.exists && table("orderLines")?.exists, "ORFICHE/ORFLINE tables exist"));
  checks.push(check("orders_min_columns", hasColumns("orders", ["LOGICALREF", "FICHENO", "DATE_", "TRCODE", "CLIENTREF"]) && hasColumns("orderLines", ["LOGICALREF", "ORDFICHEREF", "STOCKREF", "AMOUNT"]), "Order header/line minimum columns exist"));
  checks.push(check("shipment_tables", table("stockFiches")?.exists && table("stockLines")?.exists, "STFICHE/STLINE tables exist"));
  checks.push(check("shipment_min_columns", hasColumns("stockFiches", ["LOGICALREF", "FICHENO", "DATE_", "TRCODE", "CLIENTREF", "SOURCEINDEX"]) && hasColumns("stockLines", ["LOGICALREF", "STFICHEREF", "STOCKREF", "AMOUNT"]), "Shipment stock fiche/line minimum columns exist"));
  checks.push(check("pos_sale_tables", table("invoices")?.exists && table("stockFiches")?.exists && table("stockLines")?.exists, "INVOICE/STFICHE/STLINE tables exist"));
  checks.push(check("customer_refs", table("customers")?.exists && hasColumns("customers", ["LOGICALREF", "CODE"]), "Customer references can be resolved"));
  checks.push(check("product_refs", table("items")?.exists && hasColumns("items", ["LOGICALREF", "CODE"]), "Product references can be resolved"));
  checks.push(check("export_log", report.export_log?.exists === true, "Idempotency export log exists"));

  return checks;
}

function check(key, passed, description) {
  return {
    key,
    passed: Boolean(passed),
    description,
  };
}

async function objectExists(pool, objectName, type) {
  const result = await pool
    .request()
    .input("objectName", sql.NVarChar(256), objectName)
    .input("type", sql.NVarChar(8), type)
    .query("SELECT CASE WHEN OBJECT_ID(@objectName, @type) IS NULL THEN 0 ELSE 1 END AS exists_flag;");

  return Number(result.recordset?.[0]?.exists_flag ?? 0) === 1;
}

function firmTable(suffix) {
  return `dbo.LG_${firmNo}_${suffix}`;
}

function periodTable(suffix) {
  return `dbo.LG_${firmNo}_${periodNo}_${suffix}`;
}

function splitTable(tableName) {
  const parts = String(tableName).split(".");
  if (parts.length === 1) {
    return ["dbo", parts[0]];
  }

  return [parts[parts.length - 2], parts[parts.length - 1]];
}

function quoteTable(schema, table) {
  return `[${String(schema).replaceAll("]", "]]")}].[${String(table).replaceAll("]", "]]")}]`;
}

function nullable(value) {
  const normalized = String(value ?? "").trim();
  return normalized === "" ? undefined : normalized;
}

function parseBool(value, fallback) {
  if (value === undefined || value === null || String(value).trim() === "") {
    return fallback;
  }

  return ["1", "true", "yes"].includes(String(value).trim().toLowerCase());
}
