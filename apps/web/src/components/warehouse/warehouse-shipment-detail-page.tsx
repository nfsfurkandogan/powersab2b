"use client";

import Link from "next/link";
import { FormEvent, useEffect, useMemo, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  AlertTriangle,
  ArrowLeft,
  FileText,
  Loader2,
  PackageCheck,
  Printer,
  Send,
  Trash2,
} from "lucide-react";
import { toast } from "sonner";

import {
  deleteWarehouseShipmentItem,
  finalizeWarehouseShipment,
  getWarehouseShipment,
  returnWarehouseShipmentItem,
  scanWarehouseShipment,
  type WarehouseShipmentItemDto,
  type WarehouseShipmentState,
} from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

function normalizeCode(value: string | null | undefined): string {
  return (value ?? "").trim().toLocaleUpperCase("tr-TR");
}

function toPlainMoney(value: string): string {
  const amount = Number(value);

  if (!Number.isFinite(amount)) {
    return value;
  }

  return new Intl.NumberFormat("tr-TR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
}

function formatDateTime(value: string | null | undefined): string {
  if (!value) {
    return "-";
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat("tr-TR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(parsed);
}

function displayText(value: string | number | null | undefined): string {
  const normalized = String(value ?? "").trim();
  return normalized.length > 0 ? normalized : "-";
}

function resolveItemCode(item: WarehouseShipmentItemDto): string | null {
  const code = (item.sku ?? item.oem ?? "").trim();
  return code.length > 0 ? code : null;
}

function resolveShelfAddress(item: WarehouseShipmentItemDto): string {
  return displayText(item.shelf_address);
}

function maybeApiMessage(error: unknown): string {
  if (error instanceof Error) {
    return error.message;
  }

  return "İşlem başarısız oldu";
}

function optimisticScan(
  source: WarehouseShipmentState,
  barcode: string,
  qty: number
): { applied: boolean; reason?: "not_found" | "over_scan"; state: WarehouseShipmentState } {
  const key = normalizeCode(barcode);

  const matcher = (item: WarehouseShipmentItemDto) => {
    const sku = normalizeCode(item.sku);
    const oem = normalizeCode(item.oem);
    return key.length > 0 && (key === sku || key === oem);
  };

  const fromRemainingIndex = source.remaining_items.findIndex(matcher);
  const fromShippedIndex = source.shipped_items.findIndex(matcher);

  if (fromRemainingIndex === -1 && fromShippedIndex === -1) {
    return { applied: false, reason: "not_found", state: source };
  }

  const baseItem =
    fromRemainingIndex >= 0
      ? source.remaining_items[fromRemainingIndex]
      : source.shipped_items[fromShippedIndex];

  if (!baseItem || baseItem.remaining_qty < qty) {
    return { applied: false, reason: "over_scan", state: source };
  }

  const unitPrice = Number(baseItem.unit_price);
  const safeUnitPrice = Number.isFinite(unitPrice) ? unitPrice : 0;

  const nextItem: WarehouseShipmentItemDto = {
    ...baseItem,
    shipped_qty: baseItem.shipped_qty + qty,
    remaining_qty: Math.max(0, baseItem.remaining_qty - qty),
    line_total_shipped: (safeUnitPrice * (baseItem.shipped_qty + qty)).toFixed(2),
  };

  const nextRemaining = source.remaining_items
    .map((item) => (item.id === nextItem.id ? nextItem : item))
    .filter((item) => item.remaining_qty > 0);

  const shippedExists = source.shipped_items.some((item) => item.id === nextItem.id);
  const nextShipped = shippedExists
    ? source.shipped_items.map((item) => (item.id === nextItem.id ? nextItem : item))
    : [...source.shipped_items, nextItem];

  const sentAmount = Number(source.totals.sent_amount);
  const safeSentAmount = Number.isFinite(sentAmount) ? sentAmount : 0;

  const nextState: WarehouseShipmentState = {
    ...source,
    remaining_items: nextRemaining,
    shipped_items: nextShipped,
    totals: {
      ...source.totals,
      shipped_qty_total: source.totals.shipped_qty_total + qty,
      remaining_qty_total: Math.max(0, source.totals.remaining_qty_total - qty),
      sent_amount: (safeSentAmount + safeUnitPrice * qty).toFixed(2),
      gonderilen_tutar: (safeSentAmount + safeUnitPrice * qty).toFixed(2),
    },
  };

  return { applied: true, state: nextState };
}

function optimisticReturnItem(
  source: WarehouseShipmentState,
  itemId: number,
  qty?: number
): { applied: boolean; state: WarehouseShipmentState } {
  const shippedItem = source.shipped_items.find((item) => item.id === itemId);

  if (!shippedItem || shippedItem.shipped_qty <= 0) {
    return { applied: false, state: source };
  }

  const returnQty = Math.min(Math.max(1, qty ?? shippedItem.shipped_qty), shippedItem.shipped_qty);
  const unitPrice = Number(shippedItem.unit_price);
  const safeUnitPrice = Number.isFinite(unitPrice) ? unitPrice : 0;
  const nextItem: WarehouseShipmentItemDto = {
    ...shippedItem,
    shipped_qty: Math.max(0, shippedItem.shipped_qty - returnQty),
    remaining_qty: shippedItem.remaining_qty + returnQty,
    line_total_shipped: (safeUnitPrice * Math.max(0, shippedItem.shipped_qty - returnQty)).toFixed(2),
  };

  const remainingExists = source.remaining_items.some((item) => item.id === itemId);
  const nextRemaining = remainingExists
    ? source.remaining_items.map((item) => (item.id === itemId ? nextItem : item))
    : [...source.remaining_items, nextItem].sort((first, second) => first.id - second.id);
  const nextShipped = source.shipped_items
    .map((item) => (item.id === itemId ? nextItem : item))
    .filter((item) => item.shipped_qty > 0);

  const sentAmount = Number(source.totals.sent_amount);
  const safeSentAmount = Number.isFinite(sentAmount) ? sentAmount : 0;
  const nextSentAmount = Math.max(0, safeSentAmount - safeUnitPrice * returnQty);

  const nextState: WarehouseShipmentState = {
    ...source,
    remaining_items: nextRemaining,
    shipped_items: nextShipped,
    totals: {
      ...source.totals,
      shipped_qty_total: Math.max(0, source.totals.shipped_qty_total - returnQty),
      remaining_qty_total: source.totals.remaining_qty_total + returnQty,
      sent_amount: nextSentAmount.toFixed(2),
      gonderilen_tutar: nextSentAmount.toFixed(2),
    },
  };

  return { applied: true, state: nextState };
}

function optimisticDeleteItem(
  source: WarehouseShipmentState,
  itemId: number
): { applied: boolean; state: WarehouseShipmentState } {
  const item =
    source.remaining_items.find((entry) => entry.id === itemId) ??
    source.shipped_items.find((entry) => entry.id === itemId);

  if (!item) {
    return { applied: false, state: source };
  }

  const lineTotal = Number(item.line_total_shipped);
  const safeLineTotal = Number.isFinite(lineTotal) ? lineTotal : 0;
  const sentAmount = Number(source.totals.sent_amount);
  const safeSentAmount = Number.isFinite(sentAmount) ? sentAmount : 0;
  const nextSentAmount = Math.max(0, safeSentAmount - safeLineTotal);

  const nextState: WarehouseShipmentState = {
    ...source,
    remaining_items: source.remaining_items.filter((entry) => entry.id !== itemId),
    shipped_items: source.shipped_items.filter((entry) => entry.id !== itemId),
    totals: {
      ...source.totals,
      ordered_qty_total: Math.max(0, source.totals.ordered_qty_total - item.ordered_qty),
      shipped_qty_total: Math.max(0, source.totals.shipped_qty_total - item.shipped_qty),
      remaining_qty_total: Math.max(0, source.totals.remaining_qty_total - item.remaining_qty),
      sent_amount: nextSentAmount.toFixed(2),
      gonderilen_tutar: nextSentAmount.toFixed(2),
    },
  };

  return { applied: true, state: nextState };
}

export function WarehouseShipmentDetailPage({ shipmentId }: { shipmentId: string }) {
  const inputRef = useRef<HTMLInputElement | null>(null);
  const invoiceWindowRef = useRef<Window | null>(null);
  const [barcode, setBarcode] = useState("");
  const [warning, setWarning] = useState<string | null>(null);

  const queryClient = useQueryClient();
  const queryKey = useMemo(() => ["warehouse", "shipment", shipmentId] as const, [shipmentId]);

  const shipmentQuery = useQuery({
    queryKey,
    queryFn: () => getWarehouseShipment(shipmentId),
    refetchInterval: 8000,
  });

  const shipmentState = shipmentQuery.data?.data;

  const printUrls = useMemo(
    () => ({
      packingSlip: `/warehouse/shipments/${shipmentId}/print/packing-slip`,
      label: `/warehouse/shipments/${shipmentId}/print/label`,
    }),
    [shipmentId]
  );
  const invoiceUrl = useMemo(() => {
    const orderId = shipmentState?.shipment.order.id;

    return orderId ? `/warehouse/orders/${orderId}/invoice` : null;
  }, [shipmentState?.shipment.order.id]);

  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  const scanMutation = useMutation({
    mutationFn: (payload: { barcode: string; qty?: number }) => scanWarehouseShipment(shipmentId, payload),
    onMutate: async (variables) => {
      await queryClient.cancelQueries({ queryKey });
      const previous = queryClient.getQueryData<{ data: WarehouseShipmentState }>(queryKey);

      if (previous?.data) {
        const optimistic = optimisticScan(previous.data, variables.barcode, Math.max(1, variables.qty ?? 1));
        if (optimistic.applied) {
          queryClient.setQueryData<{ data: WarehouseShipmentState }>(queryKey, { data: optimistic.state });
        }
      }

      return { previous };
    },
    onError: (error, _variables, context) => {
      if (context?.previous) {
        queryClient.setQueryData(queryKey, context.previous);
      }

      const message = maybeApiMessage(error);
      setWarning(message);
      toast.error(message);
    },
    onSuccess: (response) => {
      setWarning(null);
      queryClient.setQueryData(queryKey, response);
      toast.success("Barkod işlendi");
    },
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey });
      inputRef.current?.focus();
    },
  });

  const returnItemMutation = useMutation({
    mutationFn: (payload: { item_id: number; qty?: number }) => returnWarehouseShipmentItem(shipmentId, payload),
    onMutate: async (variables) => {
      await queryClient.cancelQueries({ queryKey });
      const previous = queryClient.getQueryData<{ data: WarehouseShipmentState }>(queryKey);

      if (previous?.data) {
        const optimistic = optimisticReturnItem(previous.data, variables.item_id, variables.qty);
        if (optimistic.applied) {
          queryClient.setQueryData<{ data: WarehouseShipmentState }>(queryKey, { data: optimistic.state });
        }
      }

      return { previous };
    },
    onError: (error, _variables, context) => {
      if (context?.previous) {
        queryClient.setQueryData(queryKey, context.previous);
      }

      const message = maybeApiMessage(error);
      setWarning(message);
      toast.error(message);
    },
    onSuccess: (response) => {
      setWarning(null);
      queryClient.setQueryData(queryKey, response);
      toast.success("Ürün tekrar sipariş listesine alındı");
    },
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey });
      inputRef.current?.focus();
    },
  });

  const deleteItemMutation = useMutation({
    mutationFn: (itemId: number) => deleteWarehouseShipmentItem(shipmentId, itemId),
    onMutate: async (itemId) => {
      await queryClient.cancelQueries({ queryKey });
      const previous = queryClient.getQueryData<{ data: WarehouseShipmentState }>(queryKey);

      if (previous?.data) {
        const optimistic = optimisticDeleteItem(previous.data, itemId);
        if (optimistic.applied) {
          queryClient.setQueryData<{ data: WarehouseShipmentState }>(queryKey, { data: optimistic.state });
        }
      }

      return { previous };
    },
    onError: (error, _variables, context) => {
      if (context?.previous) {
        queryClient.setQueryData(queryKey, context.previous);
      }

      const message = maybeApiMessage(error);
      setWarning(message);
      toast.error(message);
    },
    onSuccess: (response) => {
      setWarning(null);
      queryClient.setQueryData(queryKey, response);
      toast.success("Ürün listeden silindi");
    },
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey });
      inputRef.current?.focus();
    },
  });

  const finalizeMutation = useMutation({
    mutationFn: () => finalizeWarehouseShipment(shipmentId),
    onError: (error) => {
      invoiceWindowRef.current?.close();
      invoiceWindowRef.current = null;
      const message = maybeApiMessage(error);
      setWarning(message);
      toast.error(message);
    },
    onSuccess: (response) => {
      setWarning(null);
      queryClient.setQueryData(queryKey, response);
      const nextInvoiceUrl = response.data.shipment.order.id
        ? `/warehouse/orders/${response.data.shipment.order.id}/invoice`
        : invoiceUrl;

      if (invoiceWindowRef.current && nextInvoiceUrl) {
        invoiceWindowRef.current.location.href = nextInvoiceUrl;
        invoiceWindowRef.current = null;
        toast.success("Fatura hazırlandı, çıktı ekranı açıldı");
        return;
      }

      toast.success("Sevkiyat finalize edildi");
    },
    onSettled: () => {
      void queryClient.invalidateQueries({ queryKey });
    },
  });

  const handleFinalizeAndPrint = () => {
    if (!invoiceUrl) {
      toast.error("Fatura çıktısı için sipariş bilgisi bulunamadı");
      return;
    }

    invoiceWindowRef.current = window.open("", "_blank");
    invoiceWindowRef.current?.document.write(
      "<!doctype html><title>Fatura hazırlanıyor</title><body style='font-family:Arial;padding:24px'>Fatura hazırlanıyor...</body>"
    );
    finalizeMutation.mutate();
  };

  const handleScanSubmit = (event?: FormEvent) => {
    event?.preventDefault();

    const current = barcode.trim();
    if (!current) {
      return;
    }

    const qty = 1;
    if (shipmentState) {
      const optimistic = optimisticScan(shipmentState, current, qty);
      if (!optimistic.applied) {
        const message =
          optimistic.reason === "not_found"
            ? "Okutulan barkod bu sevkiyat satırlarında bulunamadı."
            : "Okutulan adet kalan miktarı aşıyor.";
        setWarning(message);
        toast.warning(message);
        inputRef.current?.focus();
        return;
      }
    }

    setWarning(null);
    setBarcode("");
    inputRef.current?.focus();
    scanMutation.mutate({ barcode: current, qty });
  };

  const scanByItem = (item: WarehouseShipmentItemDto, qty = 1) => {
    const code = resolveItemCode(item);
    if (!code) {
      const message = "Bu satır için okutma kodu bulunamadı (SKU/OEM yok).";
      setWarning(message);
      toast.warning(message);
      return;
    }

    if (item.remaining_qty <= 0) {
      const message = "Bu satırda sevk edilecek kalan adet yok.";
      setWarning(message);
      toast.warning(message);
      return;
    }

    const safeQty = Math.max(1, qty);
    if (shipmentState) {
      const optimistic = optimisticScan(shipmentState, code, safeQty);
      if (!optimistic.applied) {
        const message =
          optimistic.reason === "not_found"
            ? "Seçilen satır için barkod bulunamadı."
            : "Okutulan adet kalan miktarı aşıyor.";
        setWarning(message);
        toast.warning(message);
        inputRef.current?.focus();
        return;
      }
    }

    setWarning(null);
    inputRef.current?.focus();
    scanMutation.mutate({ barcode: code, qty: safeQty });
  };

  const scanFullItem = (item: WarehouseShipmentItemDto) => {
    scanByItem(item, item.remaining_qty);
  };

  const returnFullShippedItem = (item: WarehouseShipmentItemDto) => {
    if (isReadOnly || returnItemMutation.isPending || item.shipped_qty <= 0) {
      return;
    }

    setWarning(null);
    returnItemMutation.mutate({ item_id: item.id, qty: item.shipped_qty });
  };

  const deleteShipmentItem = (item: WarehouseShipmentItemDto) => {
    if (isReadOnly || deleteItemMutation.isPending || shipmentItemCount <= 1) {
      return;
    }

    setWarning(null);
    deleteItemMutation.mutate(item.id);
  };

  if (shipmentQuery.isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-28 w-full" />
        <Skeleton className="h-20 w-full" />
        <div className="grid gap-4 lg:grid-cols-2">
          <Skeleton className="h-[420px] w-full" />
          <Skeleton className="h-[420px] w-full" />
        </div>
      </div>
    );
  }

  if (shipmentQuery.isError || !shipmentState) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Sevkiyat bulunamadı</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-red-600">
            {shipmentQuery.error instanceof Error
              ? shipmentQuery.error.message
              : "Sevkiyat verisi alınamadı."}
          </p>
          <Button variant="outline" asChild className="mt-3">
            <Link href="/warehouse">
              <ArrowLeft className="h-4 w-4" /> Depo listesine dön
            </Link>
          </Button>
        </CardContent>
      </Card>
    );
  }

  const shipment = shipmentState.shipment;
  const isReadOnly = ["shipped", "partially_shipped", "cancelled"].includes(
    shipment.status.toLowerCase()
  );
  const shipmentItemCount = new Set([
    ...shipmentState.remaining_items.map((item) => item.id),
    ...shipmentState.shipped_items.map((item) => item.id),
  ]).size;
  const customer = shipment.order.customer;
  const customerLocation = [customer.city, customer.district]
    .map((value) => String(value ?? "").trim())
    .filter(Boolean)
    .join(" / ");

  return (
    <div className="point-sale-screen warehouse-shipment-clean space-y-4 rounded-[24px] border border-[var(--point-border)] bg-[#071018] p-3 text-[#eef8ef] shadow-[0_30px_90px_-54px_rgba(0,0,0,0.95)]" data-point-theme="dark">
      <section className="point-panel point-product-panel rounded-[22px] border p-4">
        <div className="mb-4 grid gap-3">
          <div className="warehouse-summary-card min-w-0 rounded-[18px] border border-emerald-300/35 bg-[linear-gradient(135deg,#1f6b45_0%,#2f7650_55%,#416650_100%)] p-3 shadow-[0_20px_48px_-36px_rgba(31,107,69,0.65),inset_0_1px_0_rgba(255,255,255,0.10)]">
            <div className="grid min-h-[86px] gap-3 xl:grid-cols-[46px_140px_minmax(280px,0.95fr)_minmax(520px,1.55fr)] xl:items-center">
              <span className="flex h-11 w-11 items-center justify-center rounded-[14px] border border-white/30 bg-white/15 text-sm font-black text-white shadow-[0_10px_30px_-16px_rgba(255,255,255,0.65)]">
                <PackageCheck className="h-5 w-5" />
              </span>

              <span className="min-w-0">
                <span className="block text-[10px] font-black uppercase tracking-[0.12em] text-white/75">Cari Kod</span>
                <span className="mt-1 block truncate text-xl font-black text-[#e6f3e9]">{displayText(customer.code)}</span>
              </span>

              <span className="min-w-0">
                <span className="mt-1 block truncate text-2xl font-black leading-tight text-white xl:text-3xl">
                  {displayText(customer.title)}
                </span>
                <span className="mt-1 block truncate text-xs font-bold text-[#cfe1d2]">
                  {shipment.shipment_no} · {formatDateTime(shipment.created_at)}
                </span>
              </span>

              <span className="grid min-w-0 grid-cols-2 gap-2 lg:grid-cols-4">
                <span className="min-w-0 rounded-lg border border-[#72bf82]/45 bg-[#1f6b45]/45 px-2 py-1 text-xs font-black text-[#d9ffe1]">
                  <span className="block text-[9px] uppercase tracking-[0.12em] opacity-80">İl / İlçe</span>
                  <span className="block truncate">{displayText(customerLocation)}</span>
                </span>
                <span className="min-w-0 rounded-lg border border-[#65b7ff]/35 bg-[#0d3b52]/55 px-2 py-1 text-xs font-black text-[#d6f0ff]">
                  <span className="block text-[9px] uppercase tracking-[0.12em] opacity-80">Adres</span>
                  <span className="block truncate">{displayText(customer.address)}</span>
                </span>
                <span className="min-w-0 rounded-lg border border-[#d78cff]/35 bg-[#3a2050]/45 px-2 py-1 text-xs font-black text-[#f4ddff]">
                  <span className="block text-[9px] uppercase tracking-[0.12em] opacity-80">Telefon</span>
                  <span className="block truncate">{displayText(customer.phone)}</span>
                </span>
                <span className="min-w-0 rounded-lg border border-[#faee56]/35 bg-[#4d4310]/45 px-2 py-1 text-xs font-black text-[#fff8a8]">
                  <span className="block text-[9px] uppercase tracking-[0.12em] opacity-80">Sipariş No</span>
                  <span className="block truncate">{displayText(shipment.order.order_no)}</span>
                </span>
              </span>
            </div>
          </div>

        </div>

        <div className="grid gap-4 xl:grid-cols-[minmax(460px,0.95fr)_minmax(460px,1.05fr)] xl:items-end">
          <label className="block">
            <span className="mb-2 flex h-5 items-center text-sm font-semibold text-[var(--point-muted-strong)]">Barkod / Stok Kodu Oku</span>
            <div className="grid h-20 grid-cols-[minmax(0,1fr)_74px] overflow-hidden rounded-[16px] border border-[var(--point-border)] bg-[var(--point-control)]">
              <Input
                ref={inputRef}
                value={barcode}
                autoFocus
                disabled={scanMutation.isPending || isReadOnly}
                onChange={(event) => setBarcode(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === "Enter") {
                    event.preventDefault();
                    handleScanSubmit();
                  }
                }}
                className="h-20 rounded-none border-0 bg-transparent text-xl font-black text-white shadow-none placeholder:text-[var(--point-muted)] focus-visible:ring-0"
                placeholder="Barkod / stok kodu okut"
              />
              <Button
                type="button"
                variant="ghost"
                className="h-20 rounded-none border-l border-[var(--point-border)]"
                onClick={() => handleScanSubmit()}
                disabled={scanMutation.isPending || isReadOnly || !barcode.trim()}
                title="Barkodu işle"
              >
                {scanMutation.isPending ? <Loader2 className="h-6 w-6 animate-spin" /> : <Send className="h-6 w-6" />}
              </Button>
            </div>
          </label>

          <div className="grid grid-cols-[0.52fr_1fr_1fr_1fr] gap-3">
            <div className="flex h-20 w-full flex-col items-center justify-center gap-1.5 rounded-[16px] border border-[#faee56]/35 bg-[#4d4310]/45 px-2 text-center text-[10px] font-black text-[#fff8a8]">
              <span className="uppercase leading-tight tracking-[0.08em]">Sipariş Tutarı</span>
              <span className="text-sm leading-none text-white">{toPlainMoney(shipmentState.totals.gonderilen_tutar)}</span>
            </div>
            <Button type="button" variant="outline" className="point-secondary-button h-20 w-full flex-col gap-1.5 rounded-[16px] text-center text-[11px] font-black" asChild>
              <a href={printUrls.packingSlip} target="_blank" rel="noreferrer">
                <Printer className="h-5 w-5" /> Depo Transfer
              </a>
            </Button>
            <Button type="button" className="point-primary-button h-20 w-full flex-col gap-1.5 rounded-[16px] text-center text-[11px] font-black" asChild>
              <a href={printUrls.label} target="_blank" rel="noreferrer">
                <Printer className="h-5 w-5" /> Kargo Etiketi
              </a>
            </Button>
            <Button
              type="button"
              className="point-yellow-action-button h-20 w-full flex-col gap-1.5 rounded-[16px] text-center text-[11px] font-black"
              onClick={handleFinalizeAndPrint}
              disabled={finalizeMutation.isPending || shipmentState.totals.shipped_qty_total <= 0 || !invoiceUrl}
            >
              {finalizeMutation.isPending ? <Loader2 className="h-5 w-5 animate-spin" /> : <FileText className="h-5 w-5" />}
              Fatura Aktar
            </Button>
          </div>
        </div>
      </section>

      {warning ? (
        <div className="rounded-[16px] border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm font-black text-amber-900">
          <div className="flex items-start gap-2">
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
            <p>{warning}</p>
          </div>
        </div>
      ) : null}

      <section className="point-table overflow-x-auto rounded-[14px] border">
        <div className="px-4 py-3">
          <p className="text-sm font-black uppercase tracking-[0.12em] text-white">Sipariş Bilgileri</p>
        </div>
        <div className="max-h-[30vh] min-w-[1140px] overflow-auto border-t border-[var(--point-border)] bg-[var(--point-control)]">
          <Table className="text-[12px]">
            <TableHeader className="sticky top-0 z-10 bg-[linear-gradient(135deg,#1f6b45_0%,#2f7650_55%,#416650_100%)]">
              <TableRow className="border-b border-emerald-300/35 hover:bg-transparent">
                <TableHead className="h-10 w-[42px] border-r border-emerald-300/25 px-3 text-center text-white">&gt;</TableHead>
                <TableHead className="h-10 w-[130px] border-r border-emerald-300/25 px-4 text-white">Ürün Kodu</TableHead>
                <TableHead className="h-10 min-w-[340px] border-r border-emerald-300/25 px-4 text-white">Ürün Adı</TableHead>
                <TableHead className="h-10 w-[100px] border-r border-emerald-300/25 px-4 text-center text-white">Raf</TableHead>
                <TableHead className="h-10 w-[74px] border-r border-emerald-300/25 px-4 text-center text-white">Birim</TableHead>
                <TableHead className="h-10 w-[86px] border-r border-emerald-300/25 px-4 text-right text-white">Mevcut</TableHead>
                <TableHead className="h-10 w-[86px] border-r border-emerald-300/25 px-4 text-right text-white">Sipariş</TableHead>
                <TableHead className="h-10 w-[86px] border-r border-emerald-300/25 px-4 text-right text-white">Kalan</TableHead>
                <TableHead className="h-10 w-[104px] border-r border-emerald-300/25 px-4 text-right text-white">Fiyat</TableHead>
                <TableHead className="h-10 w-[110px] px-4 text-right text-white">İşlem</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {shipmentState.remaining_items.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={10} className="py-10 text-center text-base font-black text-[var(--point-muted)]">Kalan ürün yok.</TableCell>
                </TableRow>
              ) : (
                shipmentState.remaining_items.map((item) => (
                  <TableRow
                    key={item.id}
                    className="h-12 cursor-pointer border-b border-[var(--point-border)] text-[var(--point-text)] transition-colors hover:bg-[var(--point-control-strong)]"
                    title="Satıra basınca kalan adedin tamamı sevk edilir"
                    onClick={() => {
                      if (!scanMutation.isPending && !isReadOnly && item.remaining_qty > 0) {
                        scanFullItem(item);
                      }
                    }}
                  >
                    <TableCell className="border-r border-[var(--point-border)] px-3 text-center font-black text-[#faee56]">&gt;</TableCell>
                    <TableCell className="border-r border-[var(--point-border)] px-4 text-base font-black">{displayText(item.sku)}</TableCell>
                    <TableCell className="border-r border-[var(--point-border)] px-4 text-sm font-black">{displayText(item.name)}</TableCell>
                    <TableCell className="border-r border-[var(--point-border)] px-4 text-center font-black text-[#e6f3e9]">{resolveShelfAddress(item)}</TableCell>
                    <TableCell className="border-r border-[var(--point-border)] px-4 text-center font-bold">AD</TableCell>
                    <TableCell className="border-r border-[var(--point-border)] px-4 text-right font-bold">{item.logo_stock?.available_total ?? 0}</TableCell>
                    <TableCell className="border-r border-[var(--point-border)] px-4 text-right font-bold">{item.ordered_qty}</TableCell>
                    <TableCell className="border-r border-[var(--point-border)] px-4 text-right text-base font-black text-[#faee56]">{item.remaining_qty}</TableCell>
                    <TableCell className="border-r border-[var(--point-border)] px-4 text-right font-bold">{toPlainMoney(item.unit_price)}</TableCell>
                    <TableCell className="px-4 text-right">
                      <Button
                        type="button"
                        variant="outline"
                        className="h-9 rounded-[10px] border-red-500/45 bg-red-500/10 px-3 text-xs font-black text-red-300 hover:bg-red-500/20 hover:text-red-100"
                        title="Ürünü listeden sil"
                        onClick={(event) => {
                          event.stopPropagation();
                          deleteShipmentItem(item);
                        }}
                        disabled={isReadOnly || deleteItemMutation.isPending || shipmentItemCount <= 1}
                      >
                        {deleteItemMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
                        Sil
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
        <p className="border-t border-[var(--point-border)] px-4 py-2 text-sm font-black text-[var(--point-muted-strong)]">Toplam: {shipmentState.remaining_items.length}</p>
      </section>

      <section className="point-table overflow-x-auto rounded-[14px] border">
        <div className="px-4 py-3">
          <p className="text-sm font-black uppercase tracking-[0.12em] text-white">Sevk Edilen Ürünler</p>
        </div>
        <div className="max-h-[22vh] min-w-[940px] overflow-auto border-t border-[var(--point-border)] bg-[var(--point-control)]">
          <Table className="text-[12px]">
            <TableHeader className="sticky top-0 z-10 bg-[linear-gradient(135deg,#1f6b45_0%,#2f7650_55%,#416650_100%)]">
              <TableRow className="border-b border-emerald-300/35 hover:bg-transparent">
                <TableHead className="h-10 w-[150px] px-4 text-white">Ürün Kodu</TableHead>
                <TableHead className="h-10 min-w-[340px] px-4 text-white">Ürün Adı</TableHead>
                <TableHead className="h-10 w-[100px] px-4 text-center text-white">Birim</TableHead>
                <TableHead className="h-10 w-[130px] px-4 text-right text-white">Sevk Miktarı</TableHead>
                <TableHead className="h-10 w-[110px] px-4 text-right text-white">İşlem</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {shipmentState.shipped_items.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="py-10 text-center text-base font-black text-[var(--point-muted)]">Henüz sevk edilen ürün yok.</TableCell>
                </TableRow>
              ) : (
                shipmentState.shipped_items.map((item) => (
                  <TableRow
                    key={item.id}
                    className="h-12 cursor-pointer border-b border-[var(--point-border)] text-[var(--point-text)] transition-colors hover:bg-[var(--point-control-strong)]"
                    title="Satıra tekrar basınca ürün sipariş listesine geri alınır"
                    onClick={() => returnFullShippedItem(item)}
                  >
                    <TableCell className="px-4 text-base font-black">{displayText(item.sku)}</TableCell>
                    <TableCell className="px-4 text-sm font-black">{displayText(item.name)}</TableCell>
                    <TableCell className="px-4 text-center font-bold">AD</TableCell>
                    <TableCell className="px-4 text-right text-base font-black text-[#faee56]">{item.shipped_qty}</TableCell>
                    <TableCell className="px-4 text-right">
                      <Button
                        type="button"
                        variant="outline"
                        className="h-9 rounded-[10px] border-red-500/45 bg-red-500/10 px-3 text-xs font-black text-red-300 hover:bg-red-500/20 hover:text-red-100"
                        title="Ürünü sevkten kaldır"
                        onClick={(event) => {
                          event.stopPropagation();
                          deleteShipmentItem(item);
                        }}
                        disabled={isReadOnly || deleteItemMutation.isPending || shipmentItemCount <= 1}
                      >
                        {deleteItemMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
                        Sil
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </section>
    </div>
  );
}
