"use client";

import { useEffect, useMemo, useRef } from "react";
import { useParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { Loader2, Printer } from "lucide-react";

import { getWarehouseShipment } from "@/lib/api";
import { Button } from "@/components/ui/button";

function formatDate(value: string | null | undefined) {
  if (!value) {
    return "-";
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat("tr-TR", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(parsed);
}

export default function WarehouseLabelPrintPage() {
  const params = useParams<{ id: string }>();
  const printedRef = useRef(false);

  const shipmentId = useMemo(() => {
    const parsed = Number(params.id);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
  }, [params.id]);

  const shipmentQuery = useQuery({
    queryKey: ["warehouse", "print", "label", shipmentId],
    queryFn: () => {
      if (!shipmentId) {
        throw new Error("Geçersiz sevkiyat ID");
      }

      return getWarehouseShipment(shipmentId);
    },
    enabled: Boolean(shipmentId),
  });

  const state = shipmentQuery.data?.data;

  useEffect(() => {
    if (!state || printedRef.current) {
      return;
    }

    printedRef.current = true;
    window.setTimeout(() => window.print(), 120);
  }, [state]);

  if (shipmentQuery.isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center gap-2 text-sm text-neutral-500">
        <Loader2 className="h-4 w-4 animate-spin" /> Kargo etiketi hazırlanıyor...
      </div>
    );
  }

  if (shipmentQuery.isError || !state) {
    return (
      <div className="mx-auto max-w-md p-6 text-center text-sm text-red-600">
        {(shipmentQuery.error as Error)?.message ?? "Sevkiyat verisi alınamadı."}
      </div>
    );
  }

  const shipment = state.shipment;
  const customer = shipment.order.customer;

  return (
    <main className="label-print-wrap p-4 text-[12px] text-black">
      <style jsx global>{`
        @page {
          size: 100mm 150mm;
          margin: 4mm;
        }

        @media print {
          body {
            background: #fff !important;
          }

          .no-print {
            display: none !important;
          }

          .label-print-wrap {
            padding: 0;
          }
        }
      `}</style>

      <div className="no-print mx-auto mb-3 flex w-full max-w-[100mm] justify-end">
        <Button size="sm" onClick={() => window.print()}>
          <Printer className="h-4 w-4" /> Yazdır
        </Button>
      </div>

      <section className="mx-auto w-[100mm] border border-black bg-white p-3">
        <header className="border-b border-black pb-2">
          <h1 className="text-base font-bold">KARGO ETİKETİ</h1>
          <p className="text-[11px]">{shipment.shipment_no}</p>
        </header>

        <div className="mt-2 space-y-1 text-[12px]">
          <p><strong>Sipariş:</strong> {shipment.order.order_no ?? "-"}</p>
          <p><strong>Müşteri:</strong> {customer.title ?? "-"}</p>
          <p><strong>Kod:</strong> {customer.code ?? "-"}</p>
          <p><strong>Taşıyıcı:</strong> {shipment.carrier_name ?? "-"}</p>
          <p><strong>Takip No:</strong> {shipment.tracking_no ?? "-"}</p>
          <p><strong>Depo:</strong> {shipment.warehouse.name ?? "-"}</p>
          <p><strong>Tarih:</strong> {formatDate(shipment.shipped_at ?? shipment.created_at)}</p>
        </div>

        <div className="mt-3 border-t border-dashed border-neutral-700 pt-2 text-[11px] text-neutral-700">
          <p>Toplam Sevk: {state.totals.shipped_qty_total}</p>
          <p>Gönderilen Tutar: {state.totals.gonderilen_tutar} TRY</p>
        </div>
      </section>
    </main>
  );
}
