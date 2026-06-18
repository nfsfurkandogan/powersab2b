#!/usr/bin/env node

import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import dns from "node:dns/promises";
import { fileURLToPath } from "node:url";

import dotenv from "dotenv";
import sql from "mssql";

import {
  LOGO_B2B_CORE_TABLES,
  logoFirmTable,
  logoPeriodTable,
  resolveLogoTable,
} from "./logo-table-names.mjs";

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const envPath = path.join(scriptDir, ".env");

if (fs.existsSync(envPath)) {
  dotenv.config({ path: envPath });
}

const config = buildConfig();
const EXPORT_PROCEDURE_CONTRACTS = {
  customer: [
    "CustomerCode",
    "Name",
    "ContactName",
    "Email",
    "Phone",
    "City",
    "District",
    "TaxOffice",
    "TaxNumber",
    "CreditLimit",
    "IsActive",
    "Address",
    "Iban",
    "ExportKey",
    "PayloadJson",
    "ExternalRef",
  ],
  collection: [
    "CustomerExternalRef",
    "CustomerCode",
    "CollectionDate",
    "Method",
    "Amount",
    "Currency",
    "ReferenceNo",
    "Note",
    "ExportKey",
    "CashboxId",
    "CashboxCode",
    "CashboxName",
    "PayloadJson",
    "ExternalRef",
  ],
  posSale: [
    "CustomerExternalRef",
    "CustomerCode",
    "SaleDate",
    "ReceiptNo",
    "SaleType",
    "DocumentType",
    "Subtotal",
    "DiscountTotal",
    "VatTotal",
    "GrandTotal",
    "CashboxCode",
    "ExportKey",
    "PayloadJson",
    "ExternalRef",
  ],
  posExpense: [
    "ExpenseDate",
    "Category",
    "Amount",
    "Currency",
    "Note",
    "CashboxCode",
    "ExportKey",
    "PayloadJson",
    "ExternalRef",
  ],
  order: [
    "CustomerExternalRef",
    "CustomerCode",
    "OrderDate",
    "OrderNo",
    "Currency",
    "Subtotal",
    "DiscountTotal",
    "VatTotal",
    "GrandTotal",
    "ExportKey",
    "PayloadJson",
    "ExternalRef",
  ],
  shipment: [
    "CustomerExternalRef",
    "CustomerCode",
    "ShipmentDate",
    "ShipmentNo",
    "OrderNo",
    "WarehouseCode",
    "Subtotal",
    "VatTotal",
    "GrandTotal",
    "ExportKey",
    "PayloadJson",
    "ExternalRef",
  ],
  return: [
    "CustomerExternalRef",
    "CustomerCode",
    "ReturnDate",
    "RequestNo",
    "ReturnType",
    "ReasonCode",
    "Amount",
    "Currency",
    "ExportKey",
    "PayloadJson",
    "ExternalRef",
  ],
};

main().catch((error) => {
  console.error("[logo-sync-doctor] failed:", error instanceof Error ? error.message : error);
  process.exitCode = 1;
});

async function main() {
  console.log(`[logo-sync-doctor] cwd=${scriptDir}`);
  console.log(`[logo-sync-doctor] node=${process.version}`);

  const missing = validateConfig(config);
  if (missing.length > 0) {
    console.log(`[logo-sync-doctor] missing config: ${missing.join(", ")}`);
  } else {
    console.log("[logo-sync-doctor] config looks complete.");
  }

  await resolveHost(config.logo.server);
  await probeSyncEndpoint("customers", config.sync.customerUrl, config.sync.key);
  await probeSyncEndpoint("products", config.sync.productUrl, config.sync.productKey);
  await probeSyncEndpoint("ledger", config.sync.ledgerUrl, config.sync.ledgerKey);
  await probePendingEndpoint("customer export", config.sync.customersPendingUrl, config.sync.customersKey);
  await probePendingEndpoint("collection export", config.sync.collectionsPendingUrl, config.sync.collectionsKey);
  await probePendingEndpoint("POS sale export", config.sync.posSalesPendingUrl, config.sync.posSalesKey);
  await probePendingEndpoint("POS expense export", config.sync.posExpensesPendingUrl, config.sync.posExpensesKey);
  await probePendingEndpoint("order export", config.sync.ordersPendingUrl, config.sync.ordersKey);
  await probePendingEndpoint("shipment export", config.sync.shipmentsPendingUrl, config.sync.shipmentsKey);
  await probePendingEndpoint("return export", config.sync.returnsPendingUrl, config.sync.returnsKey);

  if (missing.length > 0) {
    console.log("[logo-sync-doctor] SQL checks skipped because required config is incomplete.");
    return;
  }

  const pool = new sql.ConnectionPool(config.logo.connection);
  await pool.connect();

  try {
    console.log("[logo-sync-doctor] SQL connection established.");

    await printCoreTableCoverage(pool, config.logo.coreTables);

    if (config.logo.productImageTable) {
      const productImageTable = await inspectTable(pool, config.logo.productImageTable);
      console.log(
        `[logo-sync-doctor] product image table ok: ${config.logo.productImageTable} (${productImageTable.columns.length} columns)`
      );
    } else {
      await printCandidates(pool, [
        "%PERDOC%",
        "%FIRMDOC%",
        "%FOLDER%",
        "%IMAGE%",
        "%PIC%",
        "%PHOTO%",
        "%LDATA%",
        "%RESIM%",
      ], "product image/document");
    }

    if (config.logo.productRafTable) {
      const productRafTable = await inspectTable(pool, config.logo.productRafTable);
      console.log(
        `[logo-sync-doctor] product raf table ok: ${config.logo.productRafTable} (${productRafTable.columns.length} columns)`
      );
    } else {
      await printCandidates(pool, [
        "%RAF%",
        "%RAFBIL%",
        "%SHELF%",
        "%LOCATION%",
      ], "product raf");
    }

    if (config.logo.oemTable) {
      const oemTable = await inspectTable(pool, config.logo.oemTable);
      console.log(
        `[logo-sync-doctor] oem table ok: ${config.logo.oemTable} (${oemTable.columns.length} columns)`
      );
    } else {
      await printCandidates(pool, [
        "%OEM%",
        "%ORJ%",
      ], "oem");
    }

    if (config.logo.competitorTable) {
      const competitorTable = await inspectTable(pool, config.logo.competitorTable);
      console.log(
        `[logo-sync-doctor] competitor table ok: ${config.logo.competitorTable} (${competitorTable.columns.length} columns)`
      );
    } else {
      await printCandidates(pool, [
        "%RKP%",
        "%RAKIP%",
        "%COMP%",
        "%MUADIL%",
        "%ALT%",
      ], "competitor");
    }

    if (config.logo.productSubstituteTable) {
      const productSubstituteTable = await inspectTable(pool, config.logo.productSubstituteTable);
      console.log(
        `[logo-sync-doctor] substitute product table ok: ${config.logo.productSubstituteTable} (${productSubstituteTable.columns.length} columns)`
      );
    }

    await printProcedureStatus(
      pool,
      config.logo.customerExportProcedure,
      "customer export",
      EXPORT_PROCEDURE_CONTRACTS.customer
    );
    await printProcedureStatus(
      pool,
      config.logo.collectionExportProcedure,
      "collection export",
      EXPORT_PROCEDURE_CONTRACTS.collection
    );
    await printProcedureStatus(
      pool,
      config.logo.posSaleExportProcedure,
      "POS sale export",
      EXPORT_PROCEDURE_CONTRACTS.posSale
    );
    await printProcedureStatus(
      pool,
      config.logo.posExpenseExportProcedure,
      "POS expense export",
      EXPORT_PROCEDURE_CONTRACTS.posExpense
    );
    await printProcedureStatus(
      pool,
      config.logo.orderExportProcedure,
      "order export",
      EXPORT_PROCEDURE_CONTRACTS.order
    );
    await printProcedureStatus(
      pool,
      config.logo.shipmentExportProcedure,
      "shipment export",
      EXPORT_PROCEDURE_CONTRACTS.shipment
    );
    await printProcedureStatus(
      pool,
      config.logo.returnExportProcedure,
      "return export",
      EXPORT_PROCEDURE_CONTRACTS.return
    );
  } finally {
    await pool.close();
  }
}

function buildConfig() {
  const timeoutMs = parseInteger(process.env.LOGO_SQL_REQUEST_TIMEOUT_MS, 30000);
  const port = parseInteger(process.env.LOGO_SQL_PORT, undefined);
  const key = (process.env.POWERSA_SYNC_KEY ?? "").trim();
  const productKey = (process.env.POWERSA_PRODUCTS_SYNC_KEY ?? key).trim();
  const ledgerKey = (process.env.POWERSA_LEDGER_SYNC_KEY ?? key).trim();
  const collectionsKey = (process.env.POWERSA_COLLECTIONS_SYNC_KEY ?? key).trim();
  const posSalesKey = (process.env.POWERSA_POS_SALES_SYNC_KEY ?? collectionsKey).trim();
  const posExpensesKey = (process.env.POWERSA_POS_EXPENSES_SYNC_KEY ?? collectionsKey).trim();
  const ordersKey = (process.env.POWERSA_ORDERS_SYNC_KEY ?? collectionsKey).trim();
  const shipmentsKey = (process.env.POWERSA_SHIPMENTS_SYNC_KEY ?? ordersKey).trim();
  const returnsKey = (process.env.POWERSA_RETURNS_SYNC_KEY ?? ordersKey).trim();

  return {
    logo: {
      server: (process.env.LOGO_SQL_SERVER ?? "").trim(),
      instanceName: nullable(process.env.LOGO_SQL_INSTANCE),
      port,
      database: (process.env.LOGO_SQL_DATABASE ?? "").trim(),
      user: (process.env.LOGO_SQL_USER ?? "").trim(),
      password: process.env.LOGO_SQL_PASSWORD ?? "",
      customerTable: nullable(process.env.LOGO_CUSTOMER_TABLE) ?? logoFirmTable("CLCARD"),
      productTable: nullable(process.env.LOGO_PRODUCT_TABLE) ?? logoFirmTable("ITEMS"),
      ledgerTable: nullable(process.env.LOGO_LEDGER_TABLE) ?? logoPeriodTable("CLFLINE"),
      stockTable: nullable(process.env.LOGO_STOCK_TABLE),
      priceTable: nullable(process.env.LOGO_PRICE_TABLE),
      coreTables: LOGO_B2B_CORE_TABLES.map((definition) => ({
        ...definition,
        tableName: resolveLogoTable(definition),
      })),
      productImageTable:
        nullable(process.env.LOGO_PRODUCT_IMAGE_TABLE) ??
        nullable(process.env.LOGO_PRODUCT_DOCUMENT_TABLE),
      productRafTable: nullable(process.env.LOGO_PRODUCT_RAF_TABLE),
      oemTable: nullable(process.env.LOGO_OEM_TABLE),
      competitorTable: nullable(process.env.LOGO_COMPETITOR_TABLE),
      productSubstituteTable: nullable(process.env.LOGO_PRODUCT_SUBSTITUTE_TABLE),
      customerExportProcedure: nullable(process.env.LOGO_CUSTOMER_EXPORT_PROCEDURE),
      collectionExportProcedure: nullable(process.env.LOGO_COLLECTION_EXPORT_PROCEDURE),
      posSaleExportProcedure: nullable(process.env.LOGO_POS_SALE_EXPORT_PROCEDURE),
      posExpenseExportProcedure: nullable(process.env.LOGO_POS_EXPENSE_EXPORT_PROCEDURE),
      orderExportProcedure: nullable(process.env.LOGO_ORDER_EXPORT_PROCEDURE),
      shipmentExportProcedure: nullable(process.env.LOGO_SHIPMENT_EXPORT_PROCEDURE),
      returnExportProcedure: nullable(process.env.LOGO_RETURN_EXPORT_PROCEDURE),
      connection: {
        server: (process.env.LOGO_SQL_SERVER ?? "").trim(),
        database: (process.env.LOGO_SQL_DATABASE ?? "").trim(),
        user: (process.env.LOGO_SQL_USER ?? "").trim(),
        password: process.env.LOGO_SQL_PASSWORD ?? "",
        pool: {
          max: 2,
          min: 0,
          idleTimeoutMillis: 30000,
        },
        options: {
          encrypt: parseBoolean(process.env.LOGO_SQL_ENCRYPT, false),
          trustServerCertificate: parseBoolean(
            process.env.LOGO_SQL_TRUST_SERVER_CERTIFICATE,
            true
          ),
          instanceName: nullable(process.env.LOGO_SQL_INSTANCE),
        },
        requestTimeout: timeoutMs,
      },
    },
    sync: {
      customerUrl: (process.env.POWERSA_SYNC_URL ?? "").trim(),
      productUrl:
        (process.env.POWERSA_PRODUCTS_SYNC_URL ?? "").trim() ||
        deriveProductsSyncUrl(process.env.POWERSA_SYNC_URL),
      ledgerUrl:
        (process.env.POWERSA_LEDGER_SYNC_URL ?? "").trim() ||
        deriveLedgerSyncUrl(process.env.POWERSA_SYNC_URL),
      key,
      productKey,
      ledgerKey,
      customersKey: (process.env.POWERSA_CUSTOMERS_SYNC_KEY ?? key).trim(),
      customersPendingUrl:
        (process.env.POWERSA_CUSTOMERS_PENDING_URL ?? "").trim() ||
        derivePendingUrl(process.env.POWERSA_SYNC_URL, "customers"),
      collectionsKey,
      collectionsPendingUrl:
        (process.env.POWERSA_COLLECTIONS_PENDING_URL ?? "").trim() ||
        derivePendingUrl(process.env.POWERSA_SYNC_URL, "collections"),
      posSalesKey,
      posSalesPendingUrl:
        (process.env.POWERSA_POS_SALES_PENDING_URL ?? "").trim() ||
        derivePendingUrl(process.env.POWERSA_SYNC_URL, "pos-sales"),
      posExpensesKey,
      posExpensesPendingUrl:
        (process.env.POWERSA_POS_EXPENSES_PENDING_URL ?? "").trim() ||
        derivePendingUrl(process.env.POWERSA_SYNC_URL, "pos-expenses"),
      ordersKey,
      ordersPendingUrl:
        (process.env.POWERSA_ORDERS_PENDING_URL ?? "").trim() ||
        derivePendingUrl(process.env.POWERSA_SYNC_URL, "orders"),
      shipmentsKey,
      shipmentsPendingUrl:
        (process.env.POWERSA_SHIPMENTS_PENDING_URL ?? "").trim() ||
        derivePendingUrl(process.env.POWERSA_SYNC_URL, "shipments"),
      returnsKey,
      returnsPendingUrl:
        (process.env.POWERSA_RETURNS_PENDING_URL ?? "").trim() ||
        derivePendingUrl(process.env.POWERSA_SYNC_URL, "returns"),
    },
  };
}

function validateConfig(currentConfig) {
  const missing = [];

  if (!currentConfig.logo.server) missing.push("LOGO_SQL_SERVER");
  if (!currentConfig.logo.database) missing.push("LOGO_SQL_DATABASE");
  if (!currentConfig.logo.user) missing.push("LOGO_SQL_USER");
  if (!currentConfig.logo.password) missing.push("LOGO_SQL_PASSWORD");
  if (!currentConfig.sync.customerUrl) missing.push("POWERSA_SYNC_URL");
  if (!currentConfig.sync.key) missing.push("POWERSA_SYNC_KEY");

  return missing;
}

async function resolveHost(hostname) {
  if (!hostname) {
    return;
  }

  try {
    const result = await dns.lookup(hostname);
    console.log(`[logo-sync-doctor] DNS ok: ${hostname} -> ${result.address}`);
  } catch (error) {
    console.warn(
      `[logo-sync-doctor] DNS lookup failed for ${hostname}: ${error instanceof Error ? error.message : error}`
    );
  }
}

async function probeSyncEndpoint(label, url, integrationKey) {
  if (!url) {
    console.warn(`[logo-sync-doctor] ${label} sync URL is empty.`);
    return;
  }

  try {
    const response = await fetch(url, {
      method: "POST",
      headers: {
        accept: "application/json",
        "content-type": "application/json",
        ...(integrationKey ? { "x-integration-key": integrationKey } : {}),
      },
      body: JSON.stringify({}),
    });

    console.log(
      `[logo-sync-doctor] ${label} endpoint probe: ${response.status} ${response.statusText}`
    );
  } catch (error) {
    console.warn(
      `[logo-sync-doctor] ${label} endpoint probe failed: ${error instanceof Error ? error.message : error}`
    );
  }
}

async function probePendingEndpoint(label, url, integrationKey) {
  if (!url) {
    console.warn(`[logo-sync-doctor] ${label} pending URL is empty.`);
    return;
  }

  try {
    const probeUrl = `${url}${url.includes("?") ? "&" : "?"}limit=1`;
    const response = await fetch(probeUrl, {
      method: "GET",
      headers: {
        accept: "application/json",
        ...(integrationKey ? { "x-integration-key": integrationKey } : {}),
      },
    });

    console.log(
      `[logo-sync-doctor] ${label} pending probe: ${response.status} ${response.statusText}`
    );
  } catch (error) {
    console.warn(
      `[logo-sync-doctor] ${label} pending probe failed: ${error instanceof Error ? error.message : error}`
    );
  }
}

async function inspectTable(pool, tableName) {
  const [schemaName, objectName] = splitTableName(tableName);
  const result = await pool
    .request()
    .input("schemaName", sql.NVarChar(128), schemaName)
    .input("tableName", sql.NVarChar(128), objectName)
    .query(`
      SELECT COLUMN_NAME
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @schemaName
        AND TABLE_NAME = @tableName
      ORDER BY ORDINAL_POSITION
    `);

  const columns = (result.recordset ?? [])
    .map((row) => normalizeString(row.COLUMN_NAME))
    .filter(Boolean);

  if (columns.length === 0) {
    throw new Error(`table not found: ${tableName}`);
  }

  return {
    columns,
  };
}

async function printCoreTableCoverage(pool, tableDefinitions) {
  console.log("[logo-sync-doctor] B2B core Logo table coverage:");

  const missingRequired = [];

  for (const definition of tableDefinitions) {
    const tableInfo = await inspectTableIfExists(pool, definition.tableName);

    if (tableInfo) {
      console.log(
        `  ok - ${definition.key}: ${definition.tableName} (${tableInfo.columns.length} columns, module=${definition.module}, mode=${definition.integration})`
      );
      continue;
    }

    const message = `${definition.key}: ${definition.tableName} (module=${definition.module}, mode=${definition.integration})`;
    if (isRequiredCoreTable(definition)) {
      missingRequired.push(message);
      console.error(`  missing - ${message}`);
    } else {
      console.warn(`  optional missing - ${message}`);
    }
  }

  if (missingRequired.length > 0) {
    throw new Error(`required Logo table(s) not found: ${missingRequired.join("; ")}`);
  }
}

async function inspectTableIfExists(pool, tableName) {
  try {
    return await inspectTable(pool, tableName);
  } catch (error) {
    if (
      error instanceof Error &&
      error.message.startsWith("table not found:")
    ) {
      return null;
    }

    throw error;
  }
}

function isRequiredCoreTable(definition) {
  return ["customers", "products", "ledger"].includes(definition.key);
}

async function printCandidates(pool, patterns, label) {
  const request = pool.request();
  const clauses = [];

  patterns.forEach((pattern, index) => {
    const key = `pattern${index}`;
    request.input(key, sql.NVarChar(128), pattern);
    clauses.push(`t.TABLE_NAME LIKE @${key}`);
    clauses.push(`c.COLUMN_NAME LIKE @${key}`);
  });

  const result = await request.query(`
    SELECT DISTINCT TOP 15 t.TABLE_SCHEMA, t.TABLE_NAME
    FROM INFORMATION_SCHEMA.TABLES t
    LEFT JOIN INFORMATION_SCHEMA.COLUMNS c
      ON c.TABLE_SCHEMA = t.TABLE_SCHEMA
     AND c.TABLE_NAME = t.TABLE_NAME
    WHERE t.TABLE_TYPE = 'BASE TABLE'
      AND (${clauses.join(" OR ")})
    ORDER BY t.TABLE_NAME ASC
  `);

  const candidates = (result.recordset ?? []).map(
    (row) => `${row.TABLE_SCHEMA}.${row.TABLE_NAME}`
  );

  if (candidates.length === 0) {
    console.warn(`[logo-sync-doctor] no ${label} table candidates found.`);
    return;
  }

  console.log(`[logo-sync-doctor] ${label} table candidates:`);
  for (const candidate of candidates) {
    console.log(`  - ${candidate}`);
  }
}

async function printProcedureStatus(pool, procedureName, label, expectedParameters = []) {
  if (!procedureName) {
    console.warn(`[logo-sync-doctor] ${label} procedure not configured.`);
    return;
  }

  const normalizedProcedureName = procedureName.replaceAll("[", "").replaceAll("]", "");
  const result = await pool
    .request()
    .input("procedureName", sql.NVarChar(256), normalizedProcedureName)
    .query(`
      SELECT p.name
      FROM sys.parameters p
      WHERE p.object_id = OBJECT_ID(@procedureName)
      ORDER BY p.parameter_id ASC;
    `);

  const parameters = (result.recordset ?? [])
    .map((row) => normalizeString(row.name))
    .filter(Boolean);

  if (parameters.length === 0) {
    console.warn(
      `[logo-sync-doctor] ${label} procedure not found or has no parameters: ${procedureName}`
    );
    return;
  }

  console.log(
    `[logo-sync-doctor] ${label} procedure ok: ${procedureName} (${parameters.join(", ")})`
  );

  const actual = new Set(parameters.map((parameter) => parameter.toLowerCase()));
  const missing = expectedParameters.filter(
    (parameter) => !actual.has(`@${parameter.toLowerCase()}`)
  );

  if (missing.length > 0) {
    console.warn(
      `[logo-sync-doctor] ${label} procedure contract warning: missing parameter(s): ${missing
        .map((parameter) => `@${parameter}`)
        .join(", ")}`
    );
    return;
  }

  console.log(`[logo-sync-doctor] ${label} procedure contract ok.`);
}

function deriveProductsSyncUrl(syncUrl) {
  const normalized = nullable(syncUrl);
  if (!normalized) {
    return "";
  }

  return normalized.replace(/\/customers\/sync$/, "/products/sync");
}

function deriveLedgerSyncUrl(syncUrl) {
  const normalized = nullable(syncUrl);
  if (!normalized) {
    return "";
  }

  return normalized.replace(/\/customers\/sync$/, "/ledger/sync");
}

function derivePendingUrl(syncUrl, resource) {
  const normalized = nullable(syncUrl);
  if (!normalized) {
    return "";
  }

  return normalized.replace(/\/customers\/sync$/, `/${resource}/pending`);
}

function splitTableName(tableName) {
  const normalized = String(tableName ?? "").trim();
  const parts = normalized.split(".");

  if (parts.length === 1) {
    return ["dbo", stripBrackets(parts[0])];
  }

  const schemaName = stripBrackets(parts[parts.length - 2]);
  const objectName = stripBrackets(parts[parts.length - 1]);
  return [schemaName || "dbo", objectName];
}

function stripBrackets(value) {
  return String(value ?? "").replace(/^\[|\]$/g, "");
}

function nullable(value) {
  const normalized = normalizeString(value);
  return normalized === null ? null : normalized;
}

function normalizeString(value) {
  if (value === null || value === undefined) {
    return null;
  }

  const normalized = String(value).trim();
  return normalized === "" ? null : normalized;
}

function parseInteger(value, fallback) {
  if (value === undefined || value === null || value === "") {
    return fallback;
  }

  const normalized = Number.parseInt(String(value), 10);
  return Number.isFinite(normalized) ? normalized : fallback;
}

function parseBoolean(value, fallback) {
  if (value === undefined || value === null || value === "") {
    return fallback;
  }

  const normalized = String(value).trim().toLowerCase();

  if (["1", "true", "yes", "on"].includes(normalized)) {
    return true;
  }

  if (["0", "false", "no", "off"].includes(normalized)) {
    return false;
  }

  return fallback;
}
