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

const tables = [
  `dbo.LG_${firmNo}_${periodNo}_ORFICHE`,
  `dbo.LG_${firmNo}_${periodNo}_ORFLINE`,
  `dbo.LG_${firmNo}_${periodNo}_STFICHE`,
  `dbo.LG_${firmNo}_${periodNo}_STLINE`,
  `dbo.LG_${firmNo}_${periodNo}_INVOICE`,
  `dbo.LG_${firmNo}_${periodNo}_KSLINES`,
  `dbo.LG_${firmNo}_${periodNo}_CLFLINE`,
  `dbo.LG_${firmNo}_CLCARD`,
  `dbo.LG_${firmNo}_ITEMS`,
  `dbo.LG_${firmNo}_KSCARD`,
  "dbo.L_CAPIWHOUSE",
  "dbo.L_CAPIDIV",
];

main().catch((error) => {
  console.error("[inspect-write]", error instanceof Error ? error.message : error);
  process.exitCode = 1;
});

async function main() {
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
    const report = [];
    for (const table of tables) {
      report.push(await inspectTable(pool, table));
    }

    console.log(JSON.stringify({
      firm_no: firmNo,
      period_no: periodNo,
      generated_at: new Date().toISOString(),
      tables: report,
    }, null, 2));
  } finally {
    await pool.close();
  }
}

async function inspectTable(pool, tableName) {
  const [schema, table] = splitTable(tableName);
  const columns = await pool.request()
    .input("schema", sql.NVarChar(128), schema)
    .input("table", sql.NVarChar(128), table)
    .query(`
      SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = @table
      ORDER BY ORDINAL_POSITION
    `);

  if ((columns.recordset ?? []).length === 0) {
    return { table: tableName, exists: false };
  }

  const qualified = `[${schema}].[${table}]`;
  const count = await pool.request().query(`SELECT COUNT(*) AS total FROM ${qualified}`);
  const sampleColumns = (columns.recordset ?? [])
    .map((row) => row.COLUMN_NAME)
    .filter((name) => [
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
      "TOTAL",
      "NETTOTAL",
      "GROSSTOTAL",
      "GENEXP1",
      "SPECODE",
      "CYPHCODE",
      "CANCELLED",
    ].includes(String(name).toUpperCase()));

  let latest = [];
  if (sampleColumns.length > 0) {
    const select = sampleColumns.map((name) => `[${name}]`).join(", ");
    const order = columns.recordset.some((row) => row.COLUMN_NAME.toUpperCase() === "LOGICALREF")
      ? "LOGICALREF DESC"
      : "1";
    latest = (await pool.request().query(`SELECT TOP 5 ${select} FROM ${qualified} ORDER BY ${order}`)).recordset;
  }

  return {
    table: tableName,
    exists: true,
    rows: count.recordset?.[0]?.total ?? null,
    columns: columns.recordset,
    latest,
  };
}

function splitTable(tableName) {
  const parts = String(tableName).split(".");
  if (parts.length === 1) {
    return ["dbo", parts[0]];
  }

  return [parts[parts.length - 2], parts[parts.length - 1]];
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
