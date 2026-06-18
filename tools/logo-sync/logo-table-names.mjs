import process from "node:process";

export const LOGO_B2B_CORE_TABLES = [
  {
    key: "customers",
    label: "Cari kartlar",
    env: "LOGO_CUSTOMER_TABLE",
    scope: "firm",
    suffix: "CLCARD",
    module: "customers",
    integration: "read-write",
  },
  {
    key: "customer_totals",
    label: "Cari aylik toplamlar",
    env: "LOGO_CUSTOMER_TOTAL_TABLE",
    scope: "period",
    suffix: "CLTOTFIL",
    module: "dashboard,reports",
    integration: "optional-read",
  },
  {
    key: "customer_fiches",
    label: "Cari fis basliklari",
    env: "LOGO_CUSTOMER_FICHE_TABLE",
    scope: "period",
    suffix: "CLFICHE",
    module: "ledger,collections",
    integration: "optional-read",
  },
  {
    key: "ledger",
    label: "Cari hareketler",
    env: "LOGO_LEDGER_TABLE",
    scope: "period",
    suffix: "CLFLINE",
    module: "ledger,dashboard,reports",
    integration: "read",
  },
  {
    key: "products",
    label: "Urun/malzeme kartlari",
    env: "LOGO_PRODUCT_TABLE",
    scope: "firm",
    suffix: "ITEMS",
    module: "search,catalogs,pos,warehouse",
    integration: "read",
  },
  {
    key: "product_units",
    label: "Urun birim atamalari",
    env: "LOGO_PRODUCT_UNIT_TABLE",
    scope: "firm",
    suffix: "ITMUNITA",
    module: "search,pos,warehouse",
    integration: "optional-read",
  },
  {
    key: "unit_sets",
    label: "Birim setleri",
    env: "LOGO_UNIT_SET_TABLE",
    scope: "firm",
    suffix: "UNITSETF",
    module: "search,pos,warehouse",
    integration: "optional-read",
  },
  {
    key: "units",
    label: "Birimler",
    env: "LOGO_UNIT_TABLE",
    scope: "firm",
    suffix: "UNITSETL",
    module: "search,pos,warehouse",
    integration: "optional-read",
  },
  {
    key: "substitute_products",
    label: "Muadil/alternatif malzemeler",
    env: "LOGO_PRODUCT_SUBSTITUTE_TABLE",
    scope: "firm",
    suffix: "ITEMSUBS",
    module: "search,catalogs",
    integration: "optional-read",
  },
  {
    key: "prices",
    label: "Alis/satis fiyatlari",
    env: "LOGO_PRICE_TABLE",
    scope: "firm",
    suffix: "PRCLIST",
    module: "search,catalogs,pos",
    integration: "read",
  },
  {
    key: "stock_totals",
    label: "Gunluk malzeme ambar toplamlari",
    env: "LOGO_STOCK_TABLE",
    scope: "period",
    suffix: "STINVTOT",
    module: "search,pos,warehouse,reports",
    integration: "read",
  },
  {
    key: "stock_fiches",
    label: "Stok fisleri",
    env: "LOGO_STOCK_FICHE_TABLE",
    scope: "period",
    suffix: "STFICHE",
    module: "warehouse,pos,returns",
    integration: "write-via-procedure",
  },
  {
    key: "stock_lines",
    label: "Stok/fatura/irsaliye satirlari",
    env: "LOGO_STOCK_LINE_TABLE",
    scope: "period",
    suffix: "STLINE",
    module: "warehouse,pos,returns,reports",
    integration: "optional-read",
  },
  {
    key: "orders",
    label: "Siparis fisleri",
    env: "LOGO_ORDER_FICHE_TABLE",
    scope: "period",
    suffix: "ORFICHE",
    module: "orders,cart,warehouse",
    integration: "write-via-procedure",
  },
  {
    key: "order_lines",
    label: "Siparis satirlari",
    env: "LOGO_ORDER_LINE_TABLE",
    scope: "period",
    suffix: "ORFLINE",
    module: "orders,cart,warehouse",
    integration: "write-via-procedure",
  },
  {
    key: "invoices",
    label: "Faturalar",
    env: "LOGO_INVOICE_TABLE",
    scope: "period",
    suffix: "INVOICE",
    module: "pos,orders,reports,returns",
    integration: "write-via-procedure",
  },
  {
    key: "payments",
    label: "Odeme/tahsilat hareketleri",
    env: "LOGO_PAYMENT_TABLE",
    scope: "period",
    suffix: "PAYTRANS",
    module: "collections,pos,ledger",
    integration: "optional-read-write",
  },
  {
    key: "cashboxes",
    label: "Kasalar",
    env: "LOGO_CASHBOX_TABLE",
    scope: "firm",
    suffix: "KSCARD",
    module: "collections,pos",
    integration: "optional-read",
  },
  {
    key: "cash_lines",
    label: "Kasa hareketleri",
    env: "LOGO_CASH_LINE_TABLE",
    scope: "period",
    suffix: "KSLINES",
    module: "collections,pos,ledger",
    integration: "write-via-procedure",
  },
  {
    key: "banks",
    label: "Bankalar",
    env: "LOGO_BANK_TABLE",
    scope: "firm",
    suffix: "BNCARD",
    module: "collections,ledger",
    integration: "optional-read",
  },
  {
    key: "bank_accounts",
    label: "Banka hesaplari",
    env: "LOGO_BANK_ACCOUNT_TABLE",
    scope: "firm",
    suffix: "BANKACC",
    module: "collections,ledger",
    integration: "optional-read",
  },
  {
    key: "bank_fiches",
    label: "Banka fisleri",
    env: "LOGO_BANK_FICHE_TABLE",
    scope: "period",
    suffix: "BNFICHE",
    module: "collections,ledger",
    integration: "optional-read-write",
  },
  {
    key: "bank_lines",
    label: "Banka hareketleri",
    env: "LOGO_BANK_LINE_TABLE",
    scope: "period",
    suffix: "BNFLINE",
    module: "collections,ledger",
    integration: "optional-read-write",
  },
  {
    key: "checks_notes",
    label: "Cek/senet kartlari",
    env: "LOGO_CHECK_NOTE_TABLE",
    scope: "period",
    suffix: "CSCARD",
    module: "collections,ledger",
    integration: "optional-read-write",
  },
  {
    key: "salespersons",
    label: "Satis elemanlari",
    env: "LOGO_SALESPERSON_TABLE",
    scope: "firm",
    suffix: "SLSMAN",
    module: "customers,reports",
    integration: "optional-read",
  },
  {
    key: "salesperson_customers",
    label: "Satis elemani-cari iliskisi",
    env: "LOGO_SALESPERSON_CUSTOMER_TABLE",
    scope: "firm",
    suffix: "SLSCLREL",
    module: "customers,reports",
    integration: "optional-read",
  },
  {
    key: "special_codes",
    label: "Ozel kodlar",
    env: "LOGO_SPECIAL_CODE_TABLE",
    scope: "firm",
    suffix: "SPECODES",
    module: "customers,search,reports",
    integration: "optional-read",
  },
  {
    key: "warehouses",
    label: "Malzeme-ambar bilgileri",
    env: "LOGO_WAREHOUSE_INFO_TABLE",
    scope: "firm",
    suffix: "INVDEF",
    module: "warehouse,search",
    integration: "optional-read",
  },
  {
    key: "locations",
    label: "Stok yerleri",
    env: "LOGO_LOCATION_TABLE",
    scope: "firm",
    suffix: "LOCATION",
    module: "warehouse",
    integration: "optional-read",
  },
  {
    key: "documents",
    label: "Dokuman/resim bilgileri",
    env: "LOGO_PRODUCT_DOCUMENT_TABLE",
    scope: "period",
    suffix: "PERDOC",
    module: "search,catalogs",
    integration: "optional-read",
  },
];

export function logoFirmCode(env = process.env) {
  return normalizeLogoNumber(
    env.LOGO_FIRM_NO ?? env.LOGO_COMPANY_NO ?? env.LOGO_COMPANY ?? "002",
    3
  );
}

export function logoPeriodCode(env = process.env) {
  return normalizeLogoNumber(env.LOGO_PERIOD_NO ?? env.LOGO_PERIOD ?? "01", 2);
}

export function logoFirmTable(tableSuffix, env = process.env) {
  return qualifiedLogoTable(`LG_${logoFirmCode(env)}_${normalizeSuffix(tableSuffix)}`);
}

export function logoPeriodTable(tableSuffix, env = process.env) {
  return qualifiedLogoTable(
    `LG_${logoFirmCode(env)}_${logoPeriodCode(env)}_${normalizeSuffix(tableSuffix)}`
  );
}

export function resolveLogoTable(definition, env = process.env) {
  const override = nullable(env[definition.env]);
  if (override) {
    return override;
  }

  return definition.scope === "period"
    ? logoPeriodTable(definition.suffix, env)
    : logoFirmTable(definition.suffix, env);
}

function nullable(value) {
  if (value === null || value === undefined) {
    return null;
  }

  const normalized = String(value).trim();

  return normalized === "" ? null : normalized;
}

function normalizeLogoNumber(value, width) {
  const normalized = String(value ?? "").trim();
  if (normalized === "") {
    return "0".repeat(width);
  }

  if (!/^\d+$/.test(normalized)) {
    throw new Error(`Logo number must contain only digits: ${normalized}`);
  }

  if (normalized.length > width) {
    throw new Error(`Logo number must be at most ${width} digits: ${normalized}`);
  }

  return normalized.padStart(width, "0");
}

function normalizeSuffix(value) {
  const normalized = String(value ?? "").trim().toUpperCase();
  if (!/^[A-Z0-9_]+$/.test(normalized)) {
    throw new Error(`Logo table suffix contains unsupported characters: ${value}`);
  }

  return normalized;
}

function qualifiedLogoTable(tableName) {
  return `dbo.${tableName}`;
}
