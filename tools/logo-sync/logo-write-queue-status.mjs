#!/usr/bin/env node

import fs from "node:fs";
import path from "node:path";
import process from "node:process";
import { fileURLToPath } from "node:url";

import dotenv from "dotenv";

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const envPath = path.join(scriptDir, ".env");

if (fs.existsSync(envPath)) {
  dotenv.config({ path: envPath });
}

const config = buildConfig();

main().catch((error) => {
  console.error("[logo-write-queue-status] failed:", error instanceof Error ? error.message : error);
  process.exitCode = 1;
});

async function main() {
  const steps = buildSteps(config).filter((step) => step.pendingUrl && step.syncKey);

  if (steps.length === 0) {
    throw new Error("No B2B write pending endpoints configured. Set POWERSA_SYNC_URL and POWERSA_SYNC_KEY.");
  }

  const report = {
    checked_at: new Date().toISOString(),
    steps: {},
  };

  for (const step of steps) {
    try {
      const payload = await fetchPending(step);
      const records = Array.isArray(payload.records) ? payload.records : [];
      report.steps[step.key] = summarize(step, records);
    } catch (error) {
      report.steps[step.key] = {
        received: 0,
        ids: [],
        error: error instanceof Error ? error.message : String(error),
      };
    }
  }

  console.log(JSON.stringify(report, null, 2));
}

function buildConfig() {
  const fallbackKey = (process.env.POWERSA_SYNC_KEY ?? "").trim();
  const collectionsKey = (process.env.POWERSA_COLLECTIONS_SYNC_KEY ?? fallbackKey).trim();
  const ordersKey = (process.env.POWERSA_ORDERS_SYNC_KEY ?? collectionsKey).trim();
  const shipmentsKey = (process.env.POWERSA_SHIPMENTS_SYNC_KEY ?? ordersKey).trim();
  const returnsKey = (process.env.POWERSA_RETURNS_SYNC_KEY ?? ordersKey).trim();
  const returnScrapsKey = (process.env.POWERSA_RETURN_SCRAPS_SYNC_KEY ?? returnsKey).trim();
  const posSalesKey = (process.env.POWERSA_POS_SALES_SYNC_KEY ?? collectionsKey).trim();
  const posExpensesKey = (process.env.POWERSA_POS_EXPENSES_SYNC_KEY ?? collectionsKey).trim();
  const customersKey = (process.env.POWERSA_CUSTOMERS_SYNC_KEY ?? fallbackKey).trim();

  return {
    syncUrl: nullable(process.env.POWERSA_SYNC_URL),
    dealerId: parseInteger(process.env.POWERSA_DEALER_ID, undefined),
    dealerCode: nullable(process.env.POWERSA_DEALER_CODE),
    limit: parseInteger(process.env.POWERSA_WRITE_QUEUE_STATUS_LIMIT, 100),
    keys: {
      customers: customersKey,
      collections: collectionsKey,
      orders: ordersKey,
      shipments: shipmentsKey,
      returns: returnsKey,
      returnScraps: returnScrapsKey,
      posSales: posSalesKey,
      posExpenses: posExpensesKey,
    },
  };
}

function buildSteps(currentConfig) {
  return [
    {
      key: "customers",
      label: "customer",
      idField: "customer_id",
      pendingUrl: nullable(process.env.POWERSA_CUSTOMERS_PENDING_URL) ?? deriveUrl(currentConfig.syncUrl, "customers", "pending"),
      syncKey: currentConfig.keys.customers,
      summarize: summarizeCustomer,
    },
    {
      key: "collections",
      label: "collection",
      idField: "collection_id",
      pendingUrl: nullable(process.env.POWERSA_COLLECTIONS_PENDING_URL) ?? deriveUrl(currentConfig.syncUrl, "collections", "pending"),
      syncKey: currentConfig.keys.collections,
      summarize: summarizeCollection,
    },
    {
      key: "orders",
      label: "order",
      idField: "order_id",
      pendingUrl: nullable(process.env.POWERSA_ORDERS_PENDING_URL) ?? deriveUrl(currentConfig.syncUrl, "orders", "pending"),
      syncKey: currentConfig.keys.orders,
      summarize: summarizeDocument,
    },
    {
      key: "shipments",
      label: "shipment",
      idField: "shipment_id",
      pendingUrl: nullable(process.env.POWERSA_SHIPMENTS_PENDING_URL) ?? deriveUrl(currentConfig.syncUrl, "shipments", "pending"),
      syncKey: currentConfig.keys.shipments,
      summarize: summarizeShipment,
    },
    {
      key: "returns",
      label: "return",
      idField: "return_request_id",
      pendingUrl: nullable(process.env.POWERSA_RETURNS_PENDING_URL) ?? deriveUrl(currentConfig.syncUrl, "returns", "pending"),
      syncKey: currentConfig.keys.returns,
      summarize: summarizeReturn,
    },
    {
      key: "return-scraps",
      label: "return scrap",
      idField: "return_request_id",
      pendingUrl: nullable(process.env.POWERSA_RETURN_SCRAPS_PENDING_URL) ?? deriveUrl(currentConfig.syncUrl, "return-scraps", "pending"),
      syncKey: currentConfig.keys.returnScraps,
      summarize: summarizeReturnScrap,
    },
    {
      key: "pos-sales",
      label: "POS sale",
      idField: "pos_sale_id",
      pendingUrl: nullable(process.env.POWERSA_POS_SALES_PENDING_URL) ?? deriveUrl(currentConfig.syncUrl, "pos-sales", "pending"),
      syncKey: currentConfig.keys.posSales,
      summarize: summarizePosSale,
    },
    {
      key: "pos-expenses",
      label: "POS expense",
      idField: "pos_expense_id",
      pendingUrl: nullable(process.env.POWERSA_POS_EXPENSES_PENDING_URL) ?? deriveUrl(currentConfig.syncUrl, "pos-expenses", "pending"),
      syncKey: currentConfig.keys.posExpenses,
      summarize: summarizePosExpense,
    },
  ].map((step) => ({
    ...step,
    dealerId: currentConfig.dealerId,
    dealerCode: currentConfig.dealerCode,
    limit: currentConfig.limit,
  }));
}

async function fetchPending(step) {
  const query = new URLSearchParams();
  query.set("limit", String(step.limit));

  if (step.dealerId) {
    query.set("dealer_id", String(step.dealerId));
  } else if (step.dealerCode) {
    query.set("dealer_code", step.dealerCode);
  }

  const url = `${step.pendingUrl}${step.pendingUrl.includes("?") ? "&" : "?"}${query.toString()}`;
  const response = await fetch(url, {
    method: "GET",
    headers: {
      accept: "application/json",
      "x-integration-key": step.syncKey,
    },
  });

  if (!response.ok) {
    const contentType = response.headers.get("content-type") ?? "";
    const body = contentType.includes("application/json")
      ? JSON.stringify(await response.json())
      : await response.text();

    throw new Error(`${step.key} pending endpoint returned ${response.status}: ${body}`);
  }

  return response.json();
}

function summarize(step, records) {
  const details = step.summarize(records);

  return {
    received: records.length,
    ids: records.map((record) => record[step.idField]).filter((value) => value !== undefined),
    ...details,
  };
}

function summarizeCustomer(records) {
  return {
    missing_customer_code: count(records, (record) => blank(record.customer_code)),
    missing_name: count(records, (record) => blank(record.name)),
  };
}

function summarizeCollection(records) {
  return {
    missing_customer_ref: count(records, missingCustomerReference),
    missing_amount: count(records, (record) => Number(record.amount ?? 0) <= 0),
    missing_cashbox: count(records, (record) => blank(record.cashbox_code) && blank(record.cashbox_name)),
  };
}

function summarizeDocument(records) {
  return {
    missing_customer_ref: count(records, missingCustomerReference),
    total_items: sum(records, (record) => items(record).length),
    missing_product_refs: sum(records, (record) => count(items(record), missingProductReference)),
    missing_unit_refs: sum(records, (record) => count(items(record), missingUnitReference)),
    empty_items: count(records, (record) => items(record).length === 0),
  };
}

function summarizeShipment(records) {
  return {
    ...summarizeDocument(records),
    missing_warehouse: count(records, (record) => blank(record.warehouse_code)),
  };
}

function summarizeReturn(records) {
  return {
    missing_customer_ref: count(records, missingCustomerReference),
    missing_product_ref: count(records, missingProductReference),
    missing_quantity: count(records, (record) => Number(record.quantity ?? record.qty ?? 0) <= 0),
  };
}

function summarizeReturnScrap(records) {
  return {
    ...summarizeDocument(records),
    missing_document_no: count(records, (record) => blank(record.document_no) && blank(record.docode)),
    missing_quantity: count(records, (record) => Number(record.quantity ?? record.qty ?? 0) <= 0),
  };
}

function summarizePosSale(records) {
  return {
    ...summarizeDocument(records),
    missing_cashbox: count(records, (record) => blank(record.cashbox_code)),
    missing_payments: count(records, (record) => !Array.isArray(record.payments) || record.payments.length === 0),
  };
}

function summarizePosExpense(records) {
  return {
    missing_amount: count(records, (record) => Number(record.amount ?? 0) <= 0),
    missing_category: count(records, (record) => blank(record.category)),
    missing_cashbox: count(records, (record) => blank(record.cashbox_code) && blank(record.cashbox_name)),
  };
}

function missingCustomerReference(record) {
  return blank(record.customer_external_ref) && blank(record.customer_code);
}

function missingProductReference(record) {
  return blank(record.product_external_ref) && blank(record.logo?.stock_ref);
}

function missingUnitReference(record) {
  return blank(record.logo?.unitset_ref) || blank(record.logo?.uom_ref);
}

function items(record) {
  return Array.isArray(record.items) ? record.items : [];
}

function count(records, predicate) {
  return records.filter(predicate).length;
}

function sum(records, callback) {
  return records.reduce((total, record) => total + Number(callback(record) ?? 0), 0);
}

function deriveUrl(baseUrl, resource, action) {
  const normalized = nullable(baseUrl);

  if (!normalized) {
    return null;
  }

  return normalized.replace(/\/customers\/sync$/i, `/${resource}/${action}`);
}

function nullable(value) {
  const normalized = String(value ?? "").trim();
  return normalized === "" ? null : normalized;
}

function blank(value) {
  return value === null || value === undefined || String(value).trim() === "";
}

function parseInteger(value, fallback) {
  if (value === undefined || value === null || String(value).trim() === "") {
    return fallback;
  }

  const parsed = Number.parseInt(String(value), 10);
  return Number.isFinite(parsed) ? parsed : fallback;
}
