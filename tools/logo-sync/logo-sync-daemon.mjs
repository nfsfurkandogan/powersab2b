#!/usr/bin/env node

import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";
import { spawn } from "node:child_process";

import dotenv from "dotenv";

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const envPath = path.join(scriptDir, ".env");
const statusPath = path.join(scriptDir, "sync-daemon-status.json");
const defaultCustomerExportProcedure = "dbo.PowersaB2B_ExportCustomer";
const defaultFastSteps = [
  "customers",
  "ledger",
  "customers-export",
  "collections",
  "pos-sales",
  "pos-expenses",
  "documents-export",
];
const defaultSlowSteps = ["product-stocks"];

if (fs.existsSync(envPath)) {
  dotenv.config({ path: envPath });
}

const config = buildConfig();
let stopping = false;

process.on("SIGINT", stop);
process.on("SIGTERM", stop);

main().catch((error) => {
  log(`fatal ${formatError(error)}`);
  process.exitCode = 1;
});

async function main() {
  log(
    `daemon started fastIntervalMs=${config.fastIntervalMs} slowIntervalMs=${config.slowIntervalMs} fastSteps=${config.fastSteps.join(",")} slowSteps=${config.slowSteps.join(",") || "none"}`
  );

  let nextSlowRunAt = 0;
  while (!stopping) {
    const loopStartedAt = Date.now();
    const dueSteps = [...config.fastSteps];

    if (config.slowSteps.length > 0 && loopStartedAt >= nextSlowRunAt) {
      dueSteps.push(...config.slowSteps);
      nextSlowRunAt = loopStartedAt + config.slowIntervalMs;
    }

    const summary = await runLoop(dueSteps);
    writeStatus(summary);

    const delayMs = summary.failed > 0 ? config.errorBackoffMs : config.fastIntervalMs;
    await delay(delayMs, () => stopping);
  }

  log("daemon stopped");
}

async function runLoop(steps) {
  const startedAt = new Date();
  const stepResults = [];
  let failed = 0;

  for (const step of steps) {
    if (stopping) break;
    const result = await runStep(step);
    stepResults.push(result);
    if (!result.ok) failed++;
  }

  const finishedAt = new Date();
  return {
    pid: process.pid,
    started_at: startedAt.toISOString(),
    finished_at: finishedAt.toISOString(),
    duration_ms: finishedAt.getTime() - startedAt.getTime(),
    failed,
    steps: stepResults,
  };
}

async function runStep(stepName) {
  const step = resolveStep(stepName);
  if (!step) {
    return { name: stepName, ok: false, skipped: true, error: "unknown step" };
  }

  if (step.when && !step.when()) {
    return { name: stepName, ok: true, skipped: true, reason: "not configured" };
  }

  const startedAt = Date.now();
  log(`step started ${stepName}`);

  try {
    await runNodeScript(step.script, step.env);
    const durationMs = Date.now() - startedAt;
    log(`step finished ${stepName} duration=${durationMs}ms`);
    return { name: stepName, ok: true, skipped: false, duration_ms: durationMs };
  } catch (error) {
    const durationMs = Date.now() - startedAt;
    log(`step failed ${stepName} duration=${durationMs}ms error=${formatError(error)}`);
    return { name: stepName, ok: false, skipped: false, duration_ms: durationMs, error: formatError(error) };
  }
}

function resolveStep(name) {
  const steps = {
    customers: {
      script: "logo-customers-sync.mjs",
      when: () => hasAny("POWERSA_SYNC_URL", "POWERSA_CUSTOMERS_SYNC_URL"),
    },
    products: {
      script: "logo-products-sync.mjs",
      when: () => hasAny("POWERSA_PRODUCTS_SYNC_URL"),
    },
    "product-stocks": {
      script: "logo-products-sync.mjs",
      env: { SYNC_PRODUCTS_STOCK_ONLY: "true" },
      when: () => hasAny("POWERSA_PRODUCTS_SYNC_URL"),
    },
    ledger: {
      script: "logo-ledger-sync.mjs",
      when: () => parseBoolean(process.env.LOGO_LEDGER_SYNC_ENABLED, true) && hasAny("POWERSA_LEDGER_SYNC_URL", "POWERSA_SYNC_URL"),
    },
    "customers-export": {
      script: "logo-customers-export.mjs",
      env: { LOGO_CUSTOMER_EXPORT_PROCEDURE: customerExportProcedure() },
      when: () => customerExportProcedure() !== "" && hasAny("POWERSA_CUSTOMERS_PENDING_URL", "POWERSA_SYNC_URL"),
    },
    collections: {
      script: "logo-collections-export.mjs",
      when: () => hasAny("LOGO_COLLECTION_EXPORT_PROCEDURE") && hasAny("POWERSA_COLLECTIONS_PENDING_URL", "POWERSA_SYNC_URL"),
    },
    "pos-sales": {
      script: "logo-pos-sales-export.mjs",
      when: () => hasAny("LOGO_POS_SALE_EXPORT_PROCEDURE") && hasAny("POWERSA_POS_SALES_PENDING_URL", "POWERSA_SYNC_URL"),
    },
    "pos-expenses": {
      script: "logo-pos-expenses-export.mjs",
      when: () => hasAny("LOGO_POS_EXPENSE_EXPORT_PROCEDURE") && hasAny("POWERSA_POS_EXPENSES_PENDING_URL", "POWERSA_SYNC_URL"),
    },
    "documents-export": {
      script: "logo-documents-export.mjs",
      when: () =>
        hasAny(
          "LOGO_ORDER_EXPORT_PROCEDURE",
          "LOGO_SHIPMENT_EXPORT_PROCEDURE",
          "LOGO_RETURN_EXPORT_PROCEDURE",
          "LOGO_RETURN_SCRAP_EXPORT_PROCEDURE"
        ) &&
        hasAny(
          "POWERSA_ORDERS_PENDING_URL",
          "POWERSA_SHIPMENTS_PENDING_URL",
          "POWERSA_RETURNS_PENDING_URL",
          "POWERSA_RETURN_SCRAPS_PENDING_URL",
          "POWERSA_SYNC_URL"
        ),
    },
  };

  return steps[name];
}

function runNodeScript(scriptName, extraEnv = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(process.execPath, [path.join(scriptDir, scriptName)], {
      cwd: scriptDir,
      stdio: "inherit",
      env: { ...process.env, ...extraEnv },
    });

    child.on("error", reject);
    child.on("exit", (code) => {
      if (code === 0) {
        resolve();
        return;
      }
      reject(new Error(`${scriptName} exited with code ${code}`));
    });
  });
}

function buildConfig() {
  return {
    fastIntervalMs: parseIntEnv("SYNC_DAEMON_FAST_INTERVAL_MS", 3_000, 1_000, 300_000),
    slowIntervalMs: parseIntEnv("SYNC_DAEMON_SLOW_INTERVAL_MS", 300_000, 10_000, 3_600_000),
    errorBackoffMs: parseIntEnv("SYNC_DAEMON_ERROR_BACKOFF_MS", 30_000, 5_000, 600_000),
    fastSteps: parseStepList(process.env.SYNC_DAEMON_FAST_STEPS, defaultFastSteps),
    slowSteps: parseStepList(process.env.SYNC_DAEMON_SLOW_STEPS, defaultSlowSteps),
  };
}

function parseStepList(value, fallback) {
  const rawValue = value === undefined || value === null ? "" : String(value).trim();
  if (["none", "off", "false", "0"].includes(rawValue.toLowerCase())) {
    return [];
  }

  const raw = rawValue === "" ? fallback.join(",") : rawValue;
  return raw
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean);
}

function parseIntEnv(name, fallback, min, max) {
  const parsed = Number(process.env[name]);
  if (!Number.isFinite(parsed)) return fallback;
  return Math.max(min, Math.min(max, Math.round(parsed)));
}

function parseBoolean(value, fallback) {
  if (value === undefined || value === null || String(value).trim() === "") return fallback;
  const normalized = String(value).trim().toLowerCase();
  if (["1", "true", "yes", "evet", "on"].includes(normalized)) return true;
  if (["0", "false", "no", "hayir", "off"].includes(normalized)) return false;
  return fallback;
}

function hasAny(...keys) {
  return keys.some((key) => String(process.env[key] ?? "").trim() !== "");
}

function customerExportProcedure() {
  return String(process.env.LOGO_CUSTOMER_EXPORT_PROCEDURE ?? defaultCustomerExportProcedure).trim();
}

function writeStatus(summary) {
  try {
    fs.writeFileSync(statusPath, JSON.stringify(summary, null, 2));
  } catch (error) {
    log(`status write failed ${formatError(error)}`);
  }
}

function delay(ms, shouldStop) {
  return new Promise((resolve) => {
    const startedAt = Date.now();
    const timer = setInterval(() => {
      if (shouldStop() || Date.now() - startedAt >= ms) {
        clearInterval(timer);
        resolve();
      }
    }, 250);
  });
}

function stop() {
  stopping = true;
}

function log(message) {
  console.log(`[${new Date().toISOString()}] [logo-sync-daemon] ${message}`);
}

function formatError(error) {
  return error instanceof Error ? error.message : String(error);
}
