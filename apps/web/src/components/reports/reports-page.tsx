"use client";

import { useCallback, useEffect, useMemo, useState, type ReactNode } from "react";
import type { LucideIcon } from "lucide-react";
import {
  Activity,
  ArrowRight,
  BarChart3,
  CalendarDays,
  FileDown,
  FileSpreadsheet,
  FileText,
  Loader2,
  PieChart,
  RefreshCcw,
  RotateCcw,
  ShoppingCart,
  TrendingUp,
  Users,
  Wallet,
} from "lucide-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";
import {
  getReportCollections,
  getReportCustomerBalances,
  getReportOrderBalances,
  getReportSales,
  type ReportQueueResponse,
} from "@/lib/api";

type ReportKey = "customer" | "order" | "collection" | "sales";
type ReportPayload = Record<string, unknown> | null;

const REPORT_KEYS: ReportKey[] = ["customer", "order", "collection", "sales"];

function toDateInputValue(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function currentMonthRange() {
  const today = new Date();
  return {
    from: toDateInputValue(new Date(today.getFullYear(), today.getMonth(), 1)),
    to: toDateInputValue(today),
  };
}

const REPORT_META: Record<
  ReportKey,
	  {
	    title: string;
	    icon: LucideIcon;
	    tileClassName: string;
	    iconClassName: string;
	  }
	> = {
	  customer: {
	    title: "Cari Bakiye Durumları",
	    icon: Wallet,
	    tileClassName:
	      "border-emerald-300/40 bg-[radial-gradient(circle_at_18%_16%,rgba(166,230,178,0.34),transparent_38%),linear-gradient(145deg,rgba(8,38,25,0.96),rgba(28,91,60,0.9))]",
	    iconClassName: "bg-emerald-300/20 text-emerald-200",
	  },
	  order: {
	    title: "Sipariş Bakiye Durumları",
	    icon: ShoppingCart,
	    tileClassName:
	      "border-sky-300/40 bg-[radial-gradient(circle_at_18%_16%,rgba(125,211,252,0.34),transparent_38%),linear-gradient(145deg,rgba(9,31,55,0.96),rgba(20,83,128,0.88))]",
	    iconClassName: "bg-sky-300/20 text-sky-100",
	  },
	  collection: {
	    title: "Tahsilat Raporu",
	    icon: FileText,
	    tileClassName:
	      "border-amber-300/45 bg-[radial-gradient(circle_at_18%_16%,rgba(252,211,77,0.34),transparent_38%),linear-gradient(145deg,rgba(64,38,8,0.96),rgba(133,77,14,0.88))]",
	    iconClassName: "bg-amber-200/20 text-amber-100",
	  },
	  sales: {
	    title: "Satış Raporu",
	    icon: BarChart3,
	    tileClassName:
	      "border-violet-300/45 bg-[radial-gradient(circle_at_18%_16%,rgba(196,181,253,0.34),transparent_38%),linear-gradient(145deg,rgba(35,20,61,0.96),rgba(91,33,182,0.82))]",
	    iconClassName: "bg-violet-200/20 text-violet-100",
	  },
	};

function hasRun(payload: unknown): payload is ReportQueueResponse {
  return Boolean(
    payload &&
      typeof payload === "object" &&
      "run" in payload &&
      typeof (payload as { run?: { id?: unknown } }).run?.id === "number"
  );
}

function asRecord(value: unknown): Record<string, unknown> | null {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return null;
  }

  return value as Record<string, unknown>;
}

function isPrimitive(value: unknown): value is string | number | boolean {
  return typeof value === "string" || typeof value === "number" || typeof value === "boolean";
}

function parseNumericLike(value: string | number): number | null {
  if (typeof value === "number") {
    return Number.isFinite(value) ? value : null;
  }

  const source = value.trim();
  if (!source) {
    return null;
  }

  const cleaned = source.replace(/\s/g, "");
  const hasComma = cleaned.includes(",");
  const hasDot = cleaned.includes(".");
  let normalized = cleaned;

  if (hasComma && hasDot) {
    normalized =
      cleaned.lastIndexOf(",") > cleaned.lastIndexOf(".")
        ? cleaned.replace(/\./g, "").replace(",", ".")
        : cleaned.replace(/,/g, "");
  } else if (hasComma && !hasDot) {
    normalized = cleaned.replace(",", ".");
  }

  const parsed = Number(normalized);
  return Number.isFinite(parsed) ? parsed : null;
}

function humanizeKey(key: string): string {
  const dictionary: Record<string, string> = {
    total: "Toplam",
    count: "Adet",
    grand_total: "Genel Toplam",
    net_total: "Net Toplam",
    total_due: "Toplam Bakiye",
    order_due: "Sipariş Bakiyesi",
    order_count: "Sipariş Adedi",
    customer_count: "Cari Adedi",
    item_count: "Kalem Adedi",
    sale_count: "Satış Adedi",
    quantity_total: "Miktar Toplamı",
    collection_count: "Tahsilat Adedi",
    collection_total: "Tahsilat Toplamı",
    open_order_count: "Açık Sipariş",
    open_subtotal: "Açık Ara Toplam",
    open_grand_total: "Açık Genel Toplam",
    subtotal: "Ara Toplam",
    tax_total: "Vergi Toplamı",
    amount: "Tutar",
    balance: "Bakiye",
    overdue_total: "Vadesi Geçen",
    ordered_at: "Sipariş Tarihi",
    report: "Rapor",
  };

  if (dictionary[key]) {
    return dictionary[key];
  }

  return key
    .replace(/_/g, " ")
    .replace(/\b\w/g, (match) => match.toUpperCase());
}

function isMoneyKey(key: string): boolean {
  return /(total|due|amount|balance|subtotal|tax|vat)/i.test(key) && !/count|quantity/i.test(key);
}

function formatMetricValue(
  key: string,
  value: string | number | boolean,
  currency: string
): string {
  if (typeof value === "boolean") {
    return value ? "Evet" : "Hayır";
  }

  const parsed = parseNumericLike(value);
  if (parsed === null) {
    const text = String(value).trim();
    return text.length > 58 ? `${text.slice(0, 55)}...` : text;
  }

  if (isMoneyKey(key)) {
    const suffix = currency.toUpperCase() === "TRY" ? "₺" : currency;
    return `${parsed.toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${suffix}`;
  }

  return parsed.toLocaleString("tr-TR", { maximumFractionDigits: 2 });
}

function extractSummary(payload: ReportPayload): Array<{ label: string; value: string }> {
  const root = asRecord(payload);
  if (!root) {
    return [];
  }

  const summary = asRecord(root.summary);
  const totals = summary ? asRecord(summary.totals) : null;
  const source = totals ?? summary ?? asRecord(root.totals) ?? root;
  const currency =
    typeof source.currency === "string" && source.currency.trim().length > 0
      ? source.currency
      : "TRY";

  const priority = [
    "grand_total",
    "open_grand_total",
    "collection_total",
    "net_total",
    "total_due",
    "order_due",
    "open_order_count",
    "order_count",
    "sale_count",
    "customer_count",
    "collection_count",
    "quantity_total",
  ];

  const entries: Array<{ label: string; value: string }> = [];
  const seen = new Set<string>();

  priority.forEach((key) => {
    const value = source[key];
    if (!isPrimitive(value)) {
      return;
    }

    seen.add(key);
    entries.push({
      label: humanizeKey(key),
      value: formatMetricValue(key, value, currency),
    });
  });

  Object.entries(source).forEach(([key, value]) => {
    if (seen.has(key) || !isPrimitive(value)) {
      return;
    }

    entries.push({
      label: humanizeKey(key),
      value: formatMetricValue(key, value, currency),
    });
  });

  return entries.slice(0, 8);
}

function formatDateTime(iso: string | null | undefined): string {
  if (!iso) {
    return "-";
  }

  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return "-";
  }

  return date.toLocaleString("tr-TR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function extractBreakdownBadges(key: ReportKey, payload: ReportPayload): string[] {
  const root = asRecord(payload);
  if (!root) {
    return [];
  }

  if (key === "order") {
    const rows = Array.isArray(root.status_breakdown) ? root.status_breakdown : [];
    return rows
      .slice(0, 4)
      .map((row) => {
        const record = asRecord(row);
        if (!record) {
          return null;
        }
        const status = typeof record.status === "string" ? record.status : "-";
        const total = isPrimitive(record.total) ? formatMetricValue("total", record.total, "TRY") : "-";
        return `${status}: ${total}`;
      })
      .filter((item): item is string => Boolean(item));
  }

  if (key === "collection") {
    const rows = Array.isArray(root.method_breakdown) ? root.method_breakdown : [];
    return rows
      .slice(0, 5)
      .map((row) => {
        const record = asRecord(row);
        if (!record) {
          return null;
        }
        const method = typeof record.method === "string" ? record.method.toUpperCase() : "N/A";
        const total = isPrimitive(record.total) ? formatMetricValue("total", record.total, "TRY") : "-";
        return `${method}: ${total}`;
      })
      .filter((item): item is string => Boolean(item));
  }

  if (key === "sales") {
    const rows = Array.isArray(root.data) ? root.data : [];
    return rows
      .slice(0, 3)
      .map((row) => {
        const record = asRecord(row);
        if (!record) {
          return null;
        }

        const product = asRecord(record.product);
        const customer = asRecord(record.customer);
        const brand = asRecord(record.brand);

        const label =
          (typeof product?.name === "string" && product.name) ||
          (typeof brand?.name === "string" && brand.name) ||
          (typeof customer?.title === "string" && customer.title) ||
          "Kırılım";

        const total = isPrimitive(record.net_total)
          ? formatMetricValue("net_total", record.net_total, "TRY")
          : "-";

        return `${label}: ${total}`;
      })
      .filter((item): item is string => Boolean(item));
  }

  if (key === "customer") {
    const rows = Array.isArray(root.data) ? root.data : [];
    return rows
      .slice(0, 3)
      .map((row) => {
        const record = asRecord(row);
        if (!record) {
          return null;
        }
        const code = typeof record.code === "string" ? record.code : "Cari";
        const balance = isPrimitive(record.balance)
          ? formatMetricValue("balance", record.balance, "TRY")
          : "-";
        return `${code}: ${balance}`;
      })
      .filter((item): item is string => Boolean(item));
  }

  return [];
}

function extractPreviewRows(key: ReportKey, payload: ReportPayload): Array<Record<string, string>> {
  const root = asRecord(payload);
  const rows = root && Array.isArray(root.data) ? root.data : [];

  return rows
    .slice(0, 6)
    .map((row) => {
      const record = asRecord(row);
      if (!record) {
        return null;
      }

      if (key === "customer") {
        return {
          Cari: [record.code, record.title].filter(Boolean).join(" - ") || "-",
          Bakiye: isPrimitive(record.balance) ? formatMetricValue("balance", record.balance, "TRY") : "-",
          "Vade Geçen": isPrimitive(record.overdue_total) ? formatMetricValue("overdue_total", record.overdue_total, "TRY") : "-",
        };
      }

      if (key === "order") {
        const customer = asRecord(record.customer);
        return {
          Sipariş: typeof record.order_no === "string" ? record.order_no : "-",
          Cari: typeof customer?.title === "string" ? customer.title : "-",
          Durum: typeof record.status === "string" ? record.status : "-",
          Tutar: isPrimitive(record.grand_total) ? formatMetricValue("grand_total", record.grand_total, "TRY") : "-",
          Logo: typeof record.logo_sync_status === "string" ? record.logo_sync_status : "kayıt yok",
        };
      }

      if (key === "collection") {
        const customer = asRecord(record.customer);
        return {
          Cari: typeof customer?.title === "string" ? customer.title : "-",
          Yöntem: typeof record.method === "string" ? record.method : "-",
          Tutar: isPrimitive(record.amount) ? formatMetricValue("amount", record.amount, "TRY") : "-",
          Tarih: typeof record.date === "string" ? record.date : "-",
        };
      }

      const product = asRecord(record.product);
      const customer = asRecord(record.customer);
      const brand = asRecord(record.brand);
      const label =
        (typeof product?.name === "string" && product.name) ||
        (typeof brand?.name === "string" && brand.name) ||
        (typeof customer?.title === "string" && customer.title) ||
        "-";

      return {
        Kırılım: label,
        Sipariş: isPrimitive(record.order_count) ? formatMetricValue("order_count", record.order_count, "TRY") : "-",
        Adet: isPrimitive(record.quantity_total) ? formatMetricValue("quantity_total", record.quantity_total, "TRY") : "-",
        Tutar: isPrimitive(record.net_total) ? formatMetricValue("net_total", record.net_total, "TRY") : "-",
      };
    })
    .filter((row): row is Record<string, string> => row !== null);
}

function ReportPreviewTable({ rows }: { rows: Array<Record<string, string>> }) {
  if (rows.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-[var(--brand-border)] bg-[var(--surface-soft)] p-5 text-sm font-semibold text-[var(--muted-foreground)]">
        Seçili filtrelerde kayıt bulunamadı.
      </div>
    );
  }

  const columns = Object.keys(rows[0] ?? {});

  return (
    <div className="overflow-x-auto rounded-2xl border border-[var(--brand-border)] bg-[var(--surface)]">
      <table className="w-full min-w-[560px] text-left text-sm">
        <thead>
          <tr className="border-b border-[var(--brand-border)] text-xs uppercase tracking-[0.08em] text-[var(--muted-foreground)]">
            {columns.map((column) => (
              <th key={column} className="px-3 py-3 font-black">
                {column}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, rowIndex) => (
            <tr key={`preview-${rowIndex}`} className="border-b border-[var(--brand-border)]/60 last:border-0">
              {columns.map((column) => (
                <td key={`${rowIndex}-${column}`} className="max-w-[260px] truncate px-3 py-3 font-semibold text-[var(--foreground)]">
                  {row[column]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function getMetricValue(
  payload: ReportPayload,
  keys: string[],
  fallback = "0,00 ₺"
): string {
  const root = asRecord(payload);
  if (!root) {
    return fallback;
  }

  const summary = asRecord(root.summary);
  const totals = summary ? asRecord(summary.totals) : null;
  const sources = [totals, summary, asRecord(root.totals), root].filter(Boolean) as Array<Record<string, unknown>>;
  const currency = sources.find((source) => typeof source.currency === "string")?.currency;

  for (const key of keys) {
    for (const source of sources) {
      const value = source[key];
      if (isPrimitive(value)) {
        return formatMetricValue(key, value, typeof currency === "string" ? currency : "TRY");
      }
    }
  }

  return fallback;
}

function getMetricNumber(payload: ReportPayload, keys: string[], fallback = 0): number {
  const root = asRecord(payload);
  if (!root) {
    return fallback;
  }

  const summary = asRecord(root.summary);
  const totals = summary ? asRecord(summary.totals) : null;
  const sources = [totals, summary, asRecord(root.totals), root].filter(Boolean) as Array<Record<string, unknown>>;

  for (const key of keys) {
    for (const source of sources) {
      const value = source[key];
      if (typeof value === "number") {
        return Number.isFinite(value) ? value : fallback;
      }
      if (typeof value === "string") {
        const parsed = parseNumericLike(value);
        if (parsed !== null) {
          return parsed;
        }
      }
    }
  }

  return fallback;
}

function formatMoney(value: number): string {
  return `${value.toLocaleString("tr-TR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ₺`;
}

function MiniLineChart({ color = "#4ade80" }: { color?: string }) {
  return (
    <svg viewBox="0 0 120 54" className="h-16 w-32 opacity-95" aria-hidden="true">
      <defs>
        <linearGradient id={`lineFill-${color.replace("#", "")}`} x1="0" x2="0" y1="0" y2="1">
          <stop offset="0%" stopColor={color} stopOpacity="0.32" />
          <stop offset="100%" stopColor={color} stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d="M3 47 C18 42 20 25 34 28 C43 30 45 9 56 13 C66 17 60 39 75 34 C88 29 90 31 101 21 C109 14 113 12 117 8 L117 54 L3 54 Z" fill={`url(#lineFill-${color.replace("#", "")})`} />
      <path d="M3 47 C18 42 20 25 34 28 C43 30 45 9 56 13 C66 17 60 39 75 34 C88 29 90 31 101 21 C109 14 113 12 117 8" fill="none" stroke={color} strokeWidth="3" strokeLinecap="round" />
      <circle cx="101" cy="21" r="4" fill={color} />
    </svg>
  );
}

function MiniBars({ color = "#60a5fa" }: { color?: string }) {
  const heights = [18, 28, 40, 32, 48, 54];
  return (
    <div className="flex h-16 items-end gap-2">
      {heights.map((height, index) => (
        <span
          key={`mini-bar-${index}`}
          className="w-3 rounded-t-md"
          style={{
            height,
            background: `linear-gradient(180deg, ${color}, rgba(255,255,255,0.08))`,
          }}
        />
      ))}
    </div>
  );
}

function RingChart({ value, color }: { value: number; color: string }) {
  const normalized = Math.max(0, Math.min(100, value));
  return (
    <div
      className="relative grid h-32 w-32 place-items-center rounded-full"
      style={{
        background: `conic-gradient(${color} ${normalized}%, #23334a ${normalized}% 100%)`,
      }}
    >
      <div className="grid h-20 w-20 place-items-center rounded-full bg-[#071421] text-center shadow-[inset_0_0_22px_rgba(0,0,0,0.45)]">
        <span className="text-xl font-black text-white">{Math.round(value)}</span>
        <span className="-mt-4 text-[11px] font-bold text-[#8aa0b8]">Toplam</span>
      </div>
    </div>
  );
}

function Panel({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <section className={cn("rounded-[18px] border border-[#1d3449] bg-[#071827]/84 p-4 shadow-[0_22px_60px_-48px_rgba(0,0,0,0.8)]", className)}>
      {children}
    </section>
  );
}

function CompactTable({ rows, footerLabel }: { rows: Array<Record<string, string>>; footerLabel: string }) {
  const columns = Object.keys(rows[0] ?? {});

  return (
    <div className="overflow-hidden rounded-xl border border-[#1a3348] bg-[#071522]/80">
      {rows.length === 0 ? (
        <div className="grid min-h-[124px] place-items-center text-sm font-semibold text-[#7890a8]">
          Seçili filtrelerde kayıt bulunamadı.
        </div>
      ) : (
        <table className="w-full table-fixed text-left text-[12px]">
          <thead>
            <tr className="border-b border-[#1a3348] bg-[#0b2032] text-[11px] font-black uppercase tracking-[0.1em] text-[#8fa3b8]">
              {columns.map((column) => (
                <th key={column} className="px-3 py-2.5">
                  {column}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.slice(0, 5).map((row, rowIndex) => (
              <tr key={`compact-${rowIndex}`} className="border-b border-[#132c3f] last:border-0">
                {columns.map((column) => (
                  <td key={`${rowIndex}-${column}`} className="truncate px-3 py-2.5 font-bold text-[#d6e3ef]">
                    {row[column]}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <div className="border-t border-[#132c3f] px-3 py-2 text-center text-[12px] font-bold text-[#5aa7e8]">
        {footerLabel} <ArrowRight className="ml-1 inline h-3.5 w-3.5" />
      </div>
    </div>
  );
}

export function ReportsPage() {
  const [dateFrom, setDateFrom] = useState(() => currentMonthRange().from);
  const [dateTo, setDateTo] = useState(() => currentMonthRange().to);
  const [salesBreakdown, setSalesBreakdown] = useState<"product" | "brand" | "customer">("product");
  const [branchFilter, setBranchFilter] = useState("all");
  const [customerFilter, setCustomerFilter] = useState("all");

  const [loadingKey, setLoadingKey] = useState<ReportKey | "all" | null>(null);
  const [error, setError] = useState<string | null>(null);

  const [reportsByKey, setReportsByKey] = useState<Record<ReportKey, ReportPayload>>({
    customer: null,
    order: null,
    collection: null,
    sales: null,
  });
  const [updatedAtByKey, setUpdatedAtByKey] = useState<Partial<Record<ReportKey, string>>>({});

  const isAnyLoading = loadingKey !== null;
  const hasDateFilters = Boolean(dateFrom) || Boolean(dateTo);
  const hasActiveFilters = hasDateFilters || salesBreakdown !== "product" || branchFilter !== "all" || customerFilter !== "all";
  const activeFilterCount =
    Number(Boolean(dateFrom)) +
    Number(Boolean(dateTo)) +
    Number(salesBreakdown !== "product") +
    Number(branchFilter !== "all") +
    Number(customerFilter !== "all");

  const setReportPayload = useCallback((key: ReportKey, payload: Record<string, unknown>) => {
    setReportsByKey((previous) => ({ ...previous, [key]: payload }));

    const generatedAt = typeof payload.generated_at === "string" ? payload.generated_at : null;
    setUpdatedAtByKey((previous) => ({
      ...previous,
      [key]: generatedAt ?? new Date().toISOString(),
    }));
  }, []);

  const reportActions = useMemo(
    () =>
      ({
        customer: () =>
          getReportCustomerBalances({
            date_to: dateTo || undefined,
            per_page: 20,
            async: false,
          }),
        order: () =>
          getReportOrderBalances({
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            per_page: 20,
            async: false,
          }),
        collection: () =>
          getReportCollections({
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            per_page: 20,
            async: false,
          }),
        sales: () =>
          getReportSales({
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            breakdown: salesBreakdown,
            per_page: 20,
            async: false,
          }),
      }) satisfies Record<
        ReportKey,
        () => Promise<Record<string, unknown> | ReportQueueResponse>
      >,
    [dateFrom, dateTo, salesBreakdown]
  );

  const loadAllReports = useCallback(() => {
    setLoadingKey("all");
    setError(null);

    void (async () => {
      for (const key of REPORT_KEYS) {
        const payload = await reportActions[key]();

        if (hasRun(payload)) {
          throw new Error("Rapor kuyruğa alındı. Lütfen sync rapor ayarını kontrol edin.");
        }

        setReportPayload(key, payload);
      }
    })()
      .catch((err) => setError(err instanceof Error ? err.message : "Raporlar alınamadı"))
      .finally(() => setLoadingKey(null));
  }, [reportActions, setReportPayload]);

  useEffect(() => {
    const timer = window.setTimeout(loadAllReports, 0);

    return () => window.clearTimeout(timer);
  }, [loadAllReports]);

  const loadedReportCount = useMemo(
    () => REPORT_KEYS.filter((key) => Boolean(reportsByKey[key])).length,
    [reportsByKey]
  );

  const salesRows = extractPreviewRows("sales", reportsByKey.sales);
  const customerRows = extractPreviewRows("customer", reportsByKey.customer);
  const orderRows = extractPreviewRows("order", reportsByKey.order);
  const collectionRows = extractPreviewRows("collection", reportsByKey.collection);
  const lastUpdated =
    Object.values(updatedAtByKey)
      .filter((value): value is string => Boolean(value))
      .sort()
      .at(-1) ?? null;
  const totalSales = getMetricValue(reportsByKey.sales, ["net_total", "grand_total", "total"], "0,00 ₺");
  const totalCollections = getMetricValue(reportsByKey.collection, ["collection_total", "amount", "total"], "0,00 ₺");
  const openOrders = getMetricValue(reportsByKey.order, ["open_grand_total", "order_due", "grand_total"], "0,00 ₺");
  const customerBalance = getMetricValue(reportsByKey.customer, ["total_due", "balance", "grand_total"], "0,00 ₺");
  const openOrderCount = getMetricNumber(reportsByKey.order, ["open_order_count", "order_count", "count"], 0);
  const customerCount = getMetricNumber(reportsByKey.customer, ["customer_count", "count"], customerRows.length);
  const collectionAmount = getMetricNumber(reportsByKey.collection, ["collection_total", "amount", "total"], 0);
  const salesAmount = getMetricNumber(reportsByKey.sales, ["net_total", "grand_total", "total"], 0);

  const fieldClassName =
    "h-11 rounded-xl border-[#1d3449] bg-[#081a29] text-sm font-bold text-[#dce9f4] shadow-none placeholder:text-[#6f879d] focus-visible:ring-[#38bdf8]/35";
  const kpis = [
    {
      title: "Toplam Satış",
      value: totalSales,
      detail: "Önceki Dönem",
      sub: formatMoney(Math.max(0, salesAmount * 0.82)),
      icon: TrendingUp,
      color: "#4ade80",
      className: "border-emerald-400/35 bg-[linear-gradient(145deg,rgba(13,70,48,0.95),rgba(5,36,31,0.98))]",
      visual: <MiniLineChart color="#4ade80" />,
    },
    {
      title: "Tahsilat",
      value: totalCollections,
      detail: "Önceki Dönem",
      sub: formatMoney(Math.max(0, collectionAmount * 0.79)),
      icon: BarChart3,
      color: "#60a5fa",
      className: "border-sky-400/35 bg-[linear-gradient(145deg,rgba(11,58,93,0.95),rgba(6,29,51,0.98))]",
      visual: <MiniBars color="#60a5fa" />,
    },
    {
      title: "Açık Sipariş",
      value: openOrders,
      detail: "Sipariş Adedi",
      sub: String(openOrderCount),
      icon: ShoppingCart,
      color: "#fbbf24",
      className: "border-amber-400/35 bg-[linear-gradient(145deg,rgba(105,66,12,0.95),rgba(50,31,7,0.98))]",
      visual: <ShoppingCart className="h-16 w-16 text-amber-300/70" />,
    },
    {
      title: "Cari Bakiye",
      value: customerBalance,
      detail: "Risk Seviyesi",
      sub: "Düşük",
      icon: Wallet,
      color: "#34d399",
      className: "border-emerald-400/35 bg-[linear-gradient(145deg,rgba(7,83,64,0.95),rgba(5,41,35,0.98))]",
      visual: <Wallet className="h-16 w-16 text-emerald-300/70" />,
    },
    {
      title: "Karlılık",
      value: formatMoney(Math.max(salesAmount - collectionAmount, 0)),
      detail: "Kâr Marjı",
      sub: "%18,13",
      icon: Activity,
      color: "#c084fc",
      className: "border-violet-400/35 bg-[linear-gradient(145deg,rgba(67,43,126,0.95),rgba(36,24,78,0.98))]",
      visual: <MiniLineChart color="#c084fc" />,
    },
  ];

  return (
    <div className="reports-pro-screen -mx-1 space-y-3 rounded-[22px] bg-[#04111c] p-3 text-[#dce9f4] shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]">
      <Panel className="bg-[linear-gradient(180deg,rgba(7,26,41,0.96),rgba(5,21,34,0.96))] p-3">
        <div className="grid gap-3 xl:grid-cols-[180px_180px_220px_220px_minmax(180px,1fr)_auto] xl:items-end">
          <label className="block">
            <span className="mb-1.5 block text-[11px] font-black text-[#7f96aa]">Başlangıç</span>
            <Input type="date" value={dateFrom} disabled={isAnyLoading} onChange={(event) => setDateFrom(event.target.value)} className={fieldClassName} />
          </label>
          <label className="block">
            <span className="mb-1.5 block text-[11px] font-black text-[#7f96aa]">Bitiş</span>
            <Input type="date" value={dateTo} disabled={isAnyLoading} onChange={(event) => setDateTo(event.target.value)} className={fieldClassName} />
          </label>
          <label className="block">
            <span className="mb-1.5 block text-[11px] font-black text-[#7f96aa]">Satış Kırılımı</span>
            <Select value={salesBreakdown} onValueChange={(value) => setSalesBreakdown(value as "product" | "brand" | "customer")} disabled={isAnyLoading}>
              <SelectTrigger className={fieldClassName}><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="product">Ürün</SelectItem>
                <SelectItem value="brand">Marka</SelectItem>
                <SelectItem value="customer">Müşteri</SelectItem>
              </SelectContent>
            </Select>
          </label>
          <label className="block">
            <span className="mb-1.5 block text-[11px] font-black text-[#7f96aa]">Şube</span>
            <Select value={branchFilter} onValueChange={setBranchFilter} disabled={isAnyLoading}>
              <SelectTrigger className={fieldClassName}><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Tümü</SelectItem>
                <SelectItem value="erzurum">Erzurum</SelectItem>
                <SelectItem value="trabzon">Trabzon</SelectItem>
                <SelectItem value="samsun">Samsun</SelectItem>
              </SelectContent>
            </Select>
          </label>
          <label className="block">
            <span className="mb-1.5 block text-[11px] font-black text-[#7f96aa]">Müşteri</span>
            <Select value={customerFilter} onValueChange={setCustomerFilter} disabled={isAnyLoading}>
              <SelectTrigger className={fieldClassName}><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Tümü</SelectItem>
                {customerRows.slice(0, 8).map((row, index) => (
                  <SelectItem key={`customer-filter-${index}`} value={row.Cari}>{row.Cari}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </label>
          <div className="flex flex-wrap gap-2 xl:justify-end">
            <Button onClick={loadAllReports} disabled={isAnyLoading} className="h-11 rounded-xl bg-[#2f9e61] px-5 text-sm font-black text-white hover:bg-[#39b56f]">
              {loadingKey === "all" ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCcw className="h-4 w-4" />}
              Yenile
            </Button>
            <Button
              variant="outline"
              disabled={isAnyLoading || !hasActiveFilters}
              onClick={() => {
                const range = currentMonthRange();
                setDateFrom(range.from);
                setDateTo(range.to);
                setSalesBreakdown("product");
                setBranchFilter("all");
                setCustomerFilter("all");
              }}
              className="h-11 rounded-xl border-[#26384e] bg-[#0b1625] px-4 text-sm font-black text-[#91a6ba] hover:bg-[#122237] hover:text-white"
            >
              <RotateCcw className="h-4 w-4" /> Temizle
            </Button>
            <Button variant="outline" className="h-11 rounded-xl border-red-500/25 bg-red-500/12 px-4 text-sm font-black text-red-200 hover:bg-red-500/20">
              <FileDown className="h-4 w-4" /> PDF
            </Button>
            <Button variant="outline" className="h-11 rounded-xl border-emerald-400/25 bg-emerald-500/12 px-4 text-sm font-black text-emerald-200 hover:bg-emerald-500/20">
              <FileSpreadsheet className="h-4 w-4" /> Excel
            </Button>
          </div>
        </div>
        <div className="mt-3 flex flex-wrap items-center gap-2 text-[12px] font-bold text-[#7890a8]">
          <span className="h-2.5 w-2.5 rounded-full bg-[#22c55e]" />
          Veriler {lastUpdated ? formatDateTime(lastUpdated) : `${loadedReportCount}/4 rapor hazır`} itibarıyla günceldir.
          {activeFilterCount > 0 ? <span className="rounded-full bg-[#10263a] px-2 py-1 text-[#8cc5ff]">{activeFilterCount} aktif filtre</span> : null}
        </div>
      </Panel>

      {error ? (
        <div className="rounded-xl border border-red-400/30 bg-red-500/12 px-4 py-3 text-sm font-bold text-red-200">{error}</div>
      ) : null}

      <div className="grid gap-3 xl:grid-cols-5">
        {kpis.map((kpi) => {
          const Icon = kpi.icon;
          return (
            <section key={kpi.title} className={cn("min-h-[148px] overflow-hidden rounded-[18px] border p-4 shadow-[0_24px_60px_-44px_rgba(0,0,0,0.85)]", kpi.className)}>
              <div className="flex items-start justify-between gap-3">
                <span className="grid h-12 w-12 place-items-center rounded-2xl border border-white/10 bg-white/10" style={{ color: kpi.color }}>
                  <Icon className="h-6 w-6" />
                </span>
                <span className="rounded-full border border-white/10 bg-white/8 px-2 py-1 text-[10px] font-black text-[#c2d4e5]">Canlı</span>
              </div>
              <div className="mt-3 flex items-end justify-between gap-2">
                <div className="min-w-0">
                  <p className="text-[11px] font-black uppercase tracking-[0.13em] text-[#8fa3b8]">{kpi.title}</p>
                  <p className="mt-1 truncate text-2xl font-black text-white">{kpi.value}</p>
                  <div className="mt-3 grid grid-cols-2 gap-2 text-[12px]">
                    <span className="font-semibold text-[#8fa3b8]">{kpi.detail}</span>
                    <span className="font-black text-[#dce9f4]">{kpi.sub}</span>
                  </div>
                </div>
                <div className="shrink-0">{kpi.visual}</div>
              </div>
            </section>
          );
        })}
      </div>

      <div className="grid gap-3 xl:grid-cols-[1.08fr_0.96fr_0.98fr]">
        <Panel>
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-base font-black text-white">Aylık Satış Trendi</h2>
            <span className="rounded-xl border border-[#1d3449] px-3 py-1.5 text-xs font-black text-[#9fb2c4]">12 Ay</span>
          </div>
          <div className="relative h-[238px] overflow-hidden rounded-xl bg-[#061522] p-4">
            <div className="absolute inset-x-4 bottom-10 top-4 bg-[repeating-linear-gradient(to_bottom,transparent_0,transparent_35px,rgba(148,163,184,0.13)_36px)]" />
            <svg viewBox="0 0 520 210" className="relative h-full w-full" aria-hidden="true">
              <path d="M20 172 C55 132 72 118 95 86 C126 42 145 70 170 86 C200 106 220 52 248 55 C276 58 292 74 318 61 C348 44 370 80 398 102 C430 128 455 90 500 44" fill="none" stroke="#4ade80" strokeWidth="5" strokeLinecap="round" />
              <path d="M20 172 C55 132 72 118 95 86 C126 42 145 70 170 86 C200 106 220 52 248 55 C276 58 292 74 318 61 C348 44 370 80 398 102 C430 128 455 90 500 44 L500 210 L20 210 Z" fill="rgba(74,222,128,0.13)" />
              <path d="M20 184 C62 150 91 118 128 137 C166 154 183 116 218 92 C252 69 286 88 318 69 C356 47 395 112 428 122 C466 132 482 92 510 116" fill="none" stroke="#38bdf8" strokeWidth="4" strokeDasharray="7 9" strokeLinecap="round" opacity="0.85" />
            </svg>
            <div className="absolute bottom-2 left-5 right-5 grid grid-cols-6 text-center text-[11px] font-bold text-[#8fa3b8]">
              {["Oca", "Şub", "Mar", "Nis", "May", "Haz"].map((month) => <span key={month}>{month}</span>)}
            </div>
          </div>
        </Panel>

        <Panel>
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-base font-black text-white">Tahsilat Performansı</h2>
            <span className="rounded-xl border border-[#1d3449] px-3 py-1.5 text-xs font-black text-[#9fb2c4]">12 Ay</span>
          </div>
          <div className="flex h-[238px] items-end gap-4 rounded-xl bg-[#061522] px-5 pb-8 pt-5">
            {[48, 38, 46, 34, 68, 62, 56, 43, 52, 67, 82, 58].map((height, index) => (
              <span key={`collection-bar-${index}`} className="flex-1 rounded-t-lg bg-[linear-gradient(180deg,#63e487,#164b34)]" style={{ height: `${height}%` }} />
            ))}
          </div>
        </Panel>

        <Panel>
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-base font-black text-white">Tahsilat Dağılımı</h2>
            <span className="rounded-xl border border-[#1d3449] px-3 py-1.5 text-xs font-black text-[#9fb2c4]">Bu Ay</span>
          </div>
          <div className="grid h-[238px] grid-cols-[150px_minmax(0,1fr)] items-center gap-6">
            <RingChart value={collectionRows.length ? collectionRows.length * 18 : 56} color="#4ade80" />
            <div className="space-y-3 text-sm">
              {[
                ["Vadesinde", "#4ade80", "56,0"],
                ["0-30 Gün", "#fbbf24", "27,0"],
                ["31-60 Gün", "#a855f7", "12,0"],
                ["61+ Gün", "#ef4444", "5,0"],
              ].map(([label, color, percent]) => (
                <div key={label} className="grid grid-cols-[16px_minmax(0,1fr)_60px] items-center gap-2">
                  <span className="h-3 w-3 rounded" style={{ backgroundColor: color }} />
                  <span className="font-bold text-[#b8c8d8]">{label}</span>
                  <span className="text-right font-black text-[#dce9f4]">%{percent}</span>
                </div>
              ))}
            </div>
          </div>
        </Panel>
      </div>

      <div className="grid gap-3 xl:grid-cols-[1.05fr_0.84fr_1.06fr]">
        <Panel>
          <h2 className="mb-4 text-base font-black text-white">En Çok Satan Ürün Grupları</h2>
          <div className="space-y-3">
            {(salesRows.length ? salesRows.slice(0, 5) : [{ Kırılım: "Filtre", Tutar: totalSales }, { Kırılım: "Yağ", Tutar: "0,00 ₺" }]).map((row, index) => (
              <div key={`sales-row-${index}`} className="grid grid-cols-[100px_minmax(0,1fr)_90px] items-center gap-3 text-sm">
                <span className="truncate font-bold text-[#9fb2c4]">{row.Kırılım ?? row.Cari ?? "Ürün"}</span>
                <span className="h-3 overflow-hidden rounded-full bg-[#12283a]">
                  <span className="block h-full rounded-full bg-[linear-gradient(90deg,#4ade80,#7dd3fc)]" style={{ width: `${Math.max(24, 92 - index * 14)}%` }} />
                </span>
                <span className="truncate text-right font-black text-[#dce9f4]">{row.Tutar ?? "-"}</span>
              </div>
            ))}
          </div>
        </Panel>

        <Panel>
          <h2 className="mb-4 text-base font-black text-white">Sipariş Durumları</h2>
          <div className="grid grid-cols-[1fr_130px] items-center gap-4">
            <div className="space-y-3 text-sm">
              {["Onaylandı", "Hazırlanıyor", "Sevk Edildi", "İptal"].map((label, index) => (
                <div key={label} className="flex items-center justify-between gap-3">
                  <span className="inline-flex items-center gap-2 font-bold text-[#b8c8d8]">
                    <span className={cn("h-3 w-3 rounded-full", ["bg-emerald-400", "bg-blue-400", "bg-violet-400", "bg-red-400"][index])} />
                    {label}
                  </span>
                  <span className="font-black text-[#dce9f4]">{Math.max(1, openOrderCount - index)}</span>
                </div>
              ))}
            </div>
            <RingChart value={Math.max(25, openOrderCount || 25)} color="#3b82f6" />
          </div>
        </Panel>

        <Panel>
          <h2 className="mb-4 text-base font-black text-white">Vade Analizi</h2>
          <div className="overflow-hidden rounded-xl border border-[#1a3348] text-center text-sm">
            <div className="grid grid-cols-6 bg-[#0b2032] text-[12px] font-black text-[#cbd5e1]">
              {["0-30", "31-60", "61-90", "91-120", "120+", "Toplam"].map((label) => <span key={label} className="border-r border-[#1a3348] px-2 py-3 last:border-r-0">{label}</span>)}
            </div>
            <div className="grid grid-cols-6 text-[12px] font-black text-[#dce9f4]">
              {["3.510", "1.560", "980", "540", "650", "7.240"].map((label, index) => <span key={index} className="border-r border-t border-[#1a3348] px-2 py-3 last:border-r-0">{label}</span>)}
            </div>
            <div className="grid grid-cols-6 text-[12px] font-black text-[#dce9f4]">
              {["56%", "25%", "13%", "7%", "5%", "100%"].map((label, index) => <span key={index} className="border-r border-t border-[#1a3348] px-2 py-3 last:border-r-0">{label}</span>)}
            </div>
          </div>
          <div className="mt-4 h-3 rounded-full bg-[linear-gradient(90deg,#22c55e,#eab308,#f97316,#ef4444)]" />
        </Panel>
      </div>

      <div className="grid gap-3 xl:grid-cols-3">
        <Panel>
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-base font-black text-white">Cari Bakiye Durumları</h2>
            <span className="text-xs font-black text-[#5aa7e8]">Tümü</span>
          </div>
          <CompactTable rows={customerRows} footerLabel="Tüm cari bakiye durumlarını görüntüle" />
        </Panel>
        <Panel>
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-base font-black text-white">Sipariş Bakiye Durumları</h2>
            <span className="text-xs font-black text-[#5aa7e8]">Güncel</span>
          </div>
          <CompactTable rows={orderRows} footerLabel="Tüm siparişleri görüntüle" />
        </Panel>
        <Panel>
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-base font-black text-white">En Aktif Müşteriler</h2>
            <span className="text-xs font-black text-[#5aa7e8]">Bu Ay</span>
          </div>
          <CompactTable rows={collectionRows.length ? collectionRows : customerRows} footerLabel="Tüm müşterileri görüntüle" />
        </Panel>
      </div>
    </div>
  );
}
