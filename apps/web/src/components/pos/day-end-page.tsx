"use client";

import { type FormEvent, type ReactNode, useEffect, useMemo } from "react";
import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  CheckCircle2,
  Loader2,
  RefreshCcw,
  ShoppingCart,
  Wallet,
} from "lucide-react";
import { toast } from "sonner";

import {
  closePosSession,
  getCurrentPosSession,
  getPosDayEndReport,
  type PosDayEndReport,
} from "@/lib/api";
import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { subscribePosDayEndRefresh } from "@/lib/pos-day-end-events";
import { cn } from "@/lib/utils";

function toMoney(value: string | number | null | undefined): number {
  if (value === null || value === undefined) {
    return 0;
  }

  const num = typeof value === "number" ? value : Number(value);
  return Number.isFinite(num) ? num : 0;
}

type ReportTables = NonNullable<PosDayEndReport["report_tables"]>;
type SaleReportRow = ReportTables["normal_sales"][number];
type CollectionReportRow = ReportTables["cash_collections"][number];
type PanelTone = "emerald";

const emptyReportTables: ReportTables = {
  normal_sales: [],
  cash_sales: [],
  card_sales: [],
  cash_collections: [],
  card_collections: [],
};

function compactMoney(value: string | number | null | undefined): string {
  return `GEL ${toMoney(value).toLocaleString("tr-TR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function customerLabel(row: { customer_code?: string | null; customer_name?: string | null }): string {
  return row.customer_name?.trim() || row.customer_code?.trim() || "Perakende satış";
}

function saleDocumentNo(row: SaleReportRow): string {
  return row.receipt_no?.trim() || `POS-${row.id}`;
}

function collectionDocumentNo(row: CollectionReportRow): string {
  return row.reference_no?.trim() || `TAH-${row.id}`;
}

function reportTableTotal(rows: Array<{ grand_total?: string; amount?: string }>): number {
  return rows.reduce((sum, row) => sum + toMoney(row.grand_total ?? row.amount ?? 0), 0);
}

const panelToneClassNames: Record<
  PanelTone,
  {
    frame: string;
    header: string;
    tableHead: string;
    total: string;
    highlightRow: string;
    evenRow: string;
    oddRow: string;
    empty: string;
  }
> = {
  emerald: {
    frame: "border-[#2e4a3a] !bg-[linear-gradient(180deg,rgba(18,35,27,0.96)_0%,rgba(10,24,18,0.98)_100%)] shadow-[0_18px_42px_-38px_rgba(0,0,0,0.82)]",
    header: "border-[#345844] bg-[linear-gradient(135deg,#244b35_0%,#173624_100%)] text-[#edf7ef]",
    tableHead: "border-[#2a4736] bg-[#102319] text-[#dbe9df]",
    total: "border-[#2a4736] bg-[#102319] text-[#edf7ef]",
    highlightRow: "bg-[#c8bc73] text-[#14160d]",
    evenRow: "bg-[#12271d] text-[#edf7ef]",
    oddRow: "bg-[#192e23] text-[#edf7ef]",
    empty: "text-[#b9cbbb]",
  },
};

function panelRowClassName(index: number, tone: PanelTone): string {
  const classes = panelToneClassNames[tone];
  return index === 0 ? classes.highlightRow : index % 2 === 0 ? classes.evenRow : classes.oddRow;
}

function PanelFrame({
  title,
  total,
  children,
  className,
  tone = "slate",
}: {
  title: string;
  total: string | number;
  children: ReactNode;
  className?: string;
  tone?: PanelTone;
}) {
  const toneClasses = panelToneClassNames[tone];

  return (
    <section
      className={cn(
        "min-h-[280px] overflow-hidden rounded-[14px] border shadow-[0_18px_42px_-36px_rgba(0,0,0,0.78)]",
        toneClasses.frame,
        className
      )}
    >
      <div className={cn("border-b px-4 py-3 text-center text-base font-black shadow-[inset_0_1px_0_rgba(255,255,255,0.1)]", toneClasses.header)}>
        {title}
      </div>
      <div className="flex h-[calc(100%-50px)] flex-col">
        {children}
        <div className={cn("mt-auto flex h-11 items-center justify-end border-t px-4 text-base font-black", toneClasses.total)}>
          {compactMoney(total)}
        </div>
      </div>
    </section>
  );
}

function EmptyRows({ label = "Kayıt yok", tone = "emerald" }: { label?: string; tone?: PanelTone }) {
  return (
    <div className={cn("flex min-h-[120px] flex-1 items-center justify-center px-4 text-sm font-black", panelToneClassNames[tone].empty)}>
      {label}
    </div>
  );
}

function SalePanel({ title, rows, className, tone }: { title: string; rows: SaleReportRow[]; className?: string; tone: PanelTone }) {
  const toneClasses = panelToneClassNames[tone];

  return (
    <PanelFrame title={title} total={reportTableTotal(rows)} className={className} tone={tone}>
      <div className={cn("grid grid-cols-[104px_minmax(0,1fr)_118px] border-b text-[12px] font-black uppercase tracking-[0.02em]", toneClasses.tableHead)}>
        <span className="px-4 py-3">Belge No</span>
        <span className="px-4 py-3">Firma İsmi</span>
        <span className="px-4 py-3 text-right">Tutar</span>
      </div>
      {rows.length > 0 ? (
        <div className="min-h-0 flex-1 overflow-auto">
          {rows.map((row, index) => (
            <div
              key={`${title}-${row.id}`}
              className={cn(
                "grid grid-cols-[104px_minmax(0,1fr)_118px] border-b border-white/10 text-[13px] font-extrabold",
                panelRowClassName(index, tone)
              )}
            >
              <span className="truncate px-4 py-3">{saleDocumentNo(row)}</span>
              <span className="truncate px-4 py-3">{customerLabel(row)}</span>
              <span className="truncate px-4 py-3 text-right tabular-nums">{compactMoney(row.grand_total)}</span>
            </div>
          ))}
        </div>
      ) : (
        <EmptyRows tone={tone} />
      )}
    </PanelFrame>
  );
}

function CollectionPanel({ title, rows, tone }: { title: string; rows: CollectionReportRow[]; tone: PanelTone }) {
  const toneClasses = panelToneClassNames[tone];

  return (
    <PanelFrame title={title} total={reportTableTotal(rows)} className="min-h-[320px]" tone={tone}>
      <div className={cn("grid grid-cols-[minmax(0,1fr)_118px] border-b text-[12px] font-black uppercase tracking-[0.02em]", toneClasses.tableHead)}>
        <span className="px-4 py-3">Cari İsim</span>
        <span className="px-4 py-3 text-right">Tutar</span>
      </div>
      {rows.length > 0 ? (
        <div className="min-h-0 flex-1 overflow-auto">
          {rows.map((row, index) => (
            <div
              key={`${title}-${row.id}`}
              className={cn(
                "grid grid-cols-[minmax(0,1fr)_118px] border-b border-white/10 text-[13px] font-extrabold",
                panelRowClassName(index, tone)
              )}
            >
              <span className="truncate px-4 py-3" title={collectionDocumentNo(row)}>
                {customerLabel(row)}
              </span>
              <span className="truncate px-4 py-3 text-right tabular-nums">{compactMoney(row.amount)}</span>
            </div>
          ))}
        </div>
      ) : (
        <EmptyRows label="Tahsilat kaydı yok" tone={tone} />
      )}
    </PanelFrame>
  );
}

type SummaryRowTone = "neutral" | "positive" | "warning" | "total";

function SummaryRow({ label, value, tone = "neutral" }: { label: string; value: string; tone?: SummaryRowTone }) {
  const toneClassNames: Record<SummaryRowTone, { row: string; label: string; value: string }> = {
    neutral: {
      row: "",
      label: "text-[#dfece2]",
      value: "border-[#2f4638] bg-[#0d2017] text-[#f0f8f1]",
    },
    positive: {
      row: "",
      label: "text-[#bff7cf]",
      value: "border-[#49c879]/65 bg-[linear-gradient(180deg,#1f8b4d_0%,#136436_100%)] text-[#f3fff6]",
    },
    warning: {
      row: "rounded-[12px] border border-[#f4c400] bg-[linear-gradient(180deg,#f4d20f_0%,#d9af07_100%)] px-3 py-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.26),0_14px_26px_-24px_rgba(244,196,0,0.75)]",
      label: "text-[#171203]",
      value: "border-transparent bg-transparent text-[#171203] shadow-none",
    },
    total: {
      row: "rounded-[12px] border border-[#55dd82] bg-[linear-gradient(180deg,#2fc765_0%,#1d9c51_100%)] px-3 py-2 shadow-[inset_0_1px_0_rgba(255,255,255,0.2),0_14px_26px_-24px_rgba(47,199,101,0.75)]",
      label: "text-[#f2fff5]",
      value: "border-transparent bg-transparent text-[#061b0c] shadow-none",
    },
  };
  const toneClasses = toneClassNames[tone];
  const isBandRow = tone === "warning" || tone === "total";

  return (
    <div
      className={cn(
        isBandRow
          ? "flex min-w-0 items-center justify-between gap-4"
          : "grid grid-cols-[minmax(0,1fr)_minmax(126px,148px)] items-center gap-2",
        toneClasses.row
      )}
    >
      <span className={cn("min-w-0 truncate whitespace-nowrap text-[15px] font-black leading-tight", toneClasses.label)}>{label}</span>
      <span
        className={cn(
          "flex h-11 shrink-0 items-center justify-end rounded-[10px] border px-3 text-right text-[17px] font-black leading-none tabular-nums shadow-[inset_0_1px_0_rgba(255,255,255,0.12)]",
          isBandRow ? "min-w-[112px] px-0" : "",
          toneClasses.value
        )}
      >
        {value}
      </span>
    </div>
  );
}

export function PosDayEndPage() {
  const { user } = useSession();
  const queryClient = useQueryClient();
  const roleSlugs = useMemo(() => user?.roles.map((role) => role.slug) ?? [], [user?.roles]);
  const hasPosMenuPermission = user?.menu_permissions?.includes("pos") ?? false;
  const isPointRole =
    roleSlugs.includes("point") ||
    (hasPosMenuPermission && !roleSlugs.some((role) => role === "admin" || role === "dealer_admin" || role === "cashier"));

  const currentSessionQuery = useQuery({
    queryKey: ["pos", "session", "current"],
    queryFn: () => getCurrentPosSession(),
    refetchInterval: 15_000,
  });

  const session = currentSessionQuery.data?.data ?? null;

  const dayEndReportQuery = useQuery({
    queryKey: ["pos", "day-end", session?.id ?? null],
    queryFn: () => {
      if (!session) {
        throw new Error("No active session");
      }

      return getPosDayEndReport({ pos_session_id: session.id });
    },
    enabled: Boolean(session),
    refetchInterval: 15_000,
  });

  const report = dayEndReportQuery.data?.data;

  useEffect(() => {
    return subscribePosDayEndRefresh(() => {
      void queryClient.invalidateQueries({ queryKey: ["pos", "session", "current"] });
      void queryClient.invalidateQueries({ queryKey: ["pos", "day-end"] });
    });
  }, [queryClient]);

  const closeSessionMutation = useMutation({
    mutationFn: closePosSession,
    onSuccess: () => {
      toast.success("POS oturumu kapatıldı");
      void currentSessionQuery.refetch();
      void dayEndReportQuery.refetch();
    },
    onError: (error) => {
      const message = error instanceof Error ? error.message : "POS oturumu kapatılamadı";
      toast.error(message);
    },
  });

  const closeSessionSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!session) {
      toast.error("Açık POS oturumu yok.");
      return;
    }

    await closeSessionMutation.mutateAsync({
      cashbox_id: session.cashbox.id ?? undefined,
      closing_cash_counted: Math.max(0, dayEndGrandTotal),
    });
  };

  const busy =
    closeSessionMutation.isPending ||
    currentSessionQuery.isFetching ||
    dayEndReportQuery.isFetching;
  const reportTables = report?.report_tables ?? emptyReportTables;
  const normalSaleTotal = useMemo(() => reportTableTotal(reportTables.normal_sales), [reportTables.normal_sales]);
  const cardSaleTotal = useMemo(() => reportTableTotal(reportTables.card_sales), [reportTables.card_sales]);
  const cardCollectionTotal = useMemo(() => reportTableTotal(reportTables.card_collections), [reportTables.card_collections]);
  const cashSaleTotal = useMemo(() => reportTableTotal(reportTables.cash_sales), [reportTables.cash_sales]);
  const cashCollectionTotal = useMemo(() => reportTableTotal(reportTables.cash_collections), [reportTables.cash_collections]);
  const cardGrandTotal = normalSaleTotal + cardSaleTotal + cardCollectionTotal;
  const openingCashTotal = toMoney(session?.opening_cash ?? 0);
  const expenseTotal = toMoney(report?.summary.expense_total ?? 0);
  const dayEndGrandTotal = openingCashTotal + cashSaleTotal + cashCollectionTotal - expenseTotal;
  const reportDate = session ? new Date(session.opened_at).toLocaleDateString("tr-TR") : "-";
  const cashboxLabel = session ? `${session.cashbox.code ?? "-"} / ${session.cashbox.name ?? "Tanımsız"}` : "-";

  const content = (
    <div className="min-h-[calc(100vh-16px)] space-y-3 bg-[linear-gradient(180deg,#07120e_0%,#0b1712_55%,#06110d_100%)] p-3 !text-[#eef8ef]">
      <section className="flex flex-col gap-4 rounded-[16px] border border-[#24382d] bg-[linear-gradient(135deg,rgba(17,32,24,0.96)_0%,rgba(9,22,16,0.98)_100%)] px-5 py-4 shadow-[0_18px_48px_-42px_rgba(0,0,0,0.92),inset_0_1px_0_rgba(255,255,255,0.05)] lg:flex-row lg:items-center lg:justify-between">
        <div className="flex min-w-0 items-center gap-3">
          <span className="flex h-16 w-16 shrink-0 items-center justify-center rounded-[18px] border border-[#3c5d48] bg-[#203d2c] text-[#edf7ef] shadow-[0_18px_38px_-30px_rgba(0,0,0,0.88)]">
            <Wallet className="h-8 w-8" />
          </span>
          <div className="min-w-0">
            <h1 className="truncate text-4xl font-black tracking-tight text-white">Gün Sonu Raporu</h1>
            <p className="mt-2 truncate text-sm font-bold text-slate-300">
              {session ? `Oturum #${session.id} · ${cashboxLabel} · ${new Date(session.opened_at).toLocaleString("tr-TR")}` : "Açık POS oturumu bekleniyor"}
            </p>
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            variant="outline"
            className="h-14 rounded-[16px] !border-slate-500/70 !bg-slate-100 px-6 text-base font-black !text-slate-950 shadow-[0_16px_28px_-22px_rgba(0,0,0,0.9)] hover:!bg-white hover:!text-slate-950"
            onClick={() => {
              void currentSessionQuery.refetch();
              void dayEndReportQuery.refetch();
            }}
            disabled={busy}
          >
            <RefreshCcw className="h-4 w-4" /> Yenile
          </Button>
          <Button asChild className="h-14 rounded-[16px] !bg-[#3f7b58] px-6 text-base font-black !text-white shadow-[0_18px_34px_-26px_rgba(0,0,0,0.86)] hover:!bg-[#4c8b67] hover:!text-white">
            <Link href="/pos#point-sales">
              <ShoppingCart className="h-4 w-4" /> Satışa Dön
            </Link>
          </Button>
        </div>
      </section>

      {currentSessionQuery.isLoading ? (
        <div className="grid gap-3 xl:grid-cols-4">
          {Array.from({ length: 6 }).map((_, index) => (
            <Skeleton key={`day-end-loading-${index}`} className="h-56 rounded-[8px]" />
          ))}
        </div>
      ) : null}

      {!currentSessionQuery.isLoading && !session ? (
        <section className="rounded-[12px] border border-[#d7e5db] bg-[#111b15] p-5">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
              <p className="text-lg font-black text-[#f4f8f5]">Açık POS oturumu bulunmuyor.</p>
              <p className="mt-1 text-sm font-semibold text-[#aebcb1]">
                Gün sonu almak için önce hızlı satış ekranındaki mevcut oturumla çalışın.
              </p>
            </div>
            <Button asChild className="h-12 rounded-[12px] !bg-[#3f8b5f] px-5 font-black !text-white hover:!bg-[#4a9b6d]">
              <Link href="/pos#point-sales">
                <ShoppingCart className="h-4 w-4" /> Hızlı Satışa Dön
              </Link>
            </Button>
          </div>
        </section>
      ) : null}

      {!currentSessionQuery.isLoading && session ? (
        <>
          {dayEndReportQuery.isLoading ? (
            <div className="grid gap-3 xl:grid-cols-4">
              {Array.from({ length: 8 }).map((_, index) => (
                <Skeleton key={`day-end-skeleton-${index}`} className="h-56 rounded-[8px]" />
              ))}
            </div>
          ) : report ? (
            <section className="grid min-h-[calc(100vh-142px)] gap-3 xl:grid-cols-[1.05fr_1.15fr_1.15fr_0.9fr] xl:grid-rows-2">
              <SalePanel title="Cari Satış" rows={reportTables.normal_sales} tone="emerald" className="xl:row-span-2 xl:min-h-full" />
              <SalePanel title="Nakit Satış" rows={reportTables.cash_sales} tone="emerald" />
              <SalePanel title="Kredi Kartı Satış" rows={reportTables.card_sales} tone="emerald" />

              <form
                className="flex min-h-[340px] flex-col rounded-[14px] border border-[#34463a] bg-[linear-gradient(180deg,rgba(24,38,30,0.98)_0%,rgba(11,24,17,0.99)_100%)] p-4 shadow-[0_18px_42px_-36px_rgba(0,0,0,0.82)] xl:row-span-2 xl:min-h-full"
                onSubmit={closeSessionSubmit}
              >
                <div className="space-y-2.5">
                  <SummaryRow label="Tarih" value={reportDate} />
                  {[
                    ["Cari Satış", compactMoney(normalSaleTotal)],
                    ["Kredi Kart Satış", compactMoney(cardSaleTotal)],
                    ["Kredi Kart Tahsilat", compactMoney(cardCollectionTotal)],
                  ].map(([label, value]) => (
                    <SummaryRow key={label} label={label} value={value} />
                  ))}
                  <SummaryRow label="Genel Toplam" value={compactMoney(cardGrandTotal)} tone="warning" />
                  <div className="space-y-2.5 border-t border-[#2f7d4d] pt-3">
                    {[
                      { label: "Devreden", value: compactMoney(openingCashTotal), tone: "positive" as const },
                      { label: "Nakit Satış", value: compactMoney(cashSaleTotal), tone: "positive" as const },
                      { label: "Nakit Tahsilat", value: compactMoney(cashCollectionTotal), tone: "positive" as const },
                      { label: "Masraf", value: compactMoney(expenseTotal), tone: "warning" as const },
                      { label: "Genel Toplam", value: compactMoney(dayEndGrandTotal), tone: "total" as const },
                    ].map((row) => (
                      <SummaryRow key={row.label} label={row.label} value={row.value} tone={row.tone} />
                    ))}
                  </div>
                </div>

                <div className="mt-auto flex justify-end pt-6">
                  <Button type="submit" className="h-[52px] rounded-[12px] !bg-[#3f7b58] px-7 text-base font-black !text-white shadow-[0_18px_34px_-26px_rgba(0,0,0,0.86)] hover:!bg-[#4c8b67] hover:!text-white" disabled={busy}>
                    {closeSessionMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />}
                    Kaydet
                  </Button>
                </div>
              </form>
              <CollectionPanel title="Nakit Tahsilat" rows={reportTables.cash_collections} tone="emerald" />
              <CollectionPanel title="Kredi Kartı Tahsilat" rows={reportTables.card_collections} tone="emerald" />
            </section>
          ) : (
            <section className="rounded-[12px] border border-[#d7e5db] bg-[#111b15] p-5">
              <p className="text-sm font-black text-[#aebcb1]">Canlı özet verisi alınamadı.</p>
            </section>
          )}
        </>
      ) : null}
    </div>
  );

  if (isPointRole) {
    return <div className="point-admin-page">{content}</div>;
  }

  return content;
}
