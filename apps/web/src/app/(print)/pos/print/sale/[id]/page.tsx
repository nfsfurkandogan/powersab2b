"use client";

import { useEffect, useMemo, useRef } from "react";
import { useParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { Loader2, Printer } from "lucide-react";

import { getPosSale } from "@/lib/api";
import { Button } from "@/components/ui/button";

function formatMoney(value: string | number) {
  const amount = typeof value === "number" ? value : Number(value);
  return new Intl.NumberFormat("tr-TR", {
    style: "currency",
    currency: "TRY",
    minimumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0);
}

export default function PosSalePrintPage() {
  const params = useParams<{ id: string }>();
  const printedRef = useRef(false);

  const saleId = useMemo(() => {
    const parsed = Number(params.id);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
  }, [params.id]);

  const saleQuery = useQuery({
    queryKey: ["pos", "print", "sale", saleId],
    queryFn: () => {
      if (!saleId) {
        throw new Error("Geçersiz satış ID");
      }

      return getPosSale(saleId);
    },
    enabled: Boolean(saleId),
  });

  const sale = saleQuery.data?.data;

  useEffect(() => {
    if (!sale || printedRef.current) {
      return;
    }

    printedRef.current = true;
    window.setTimeout(() => window.print(), 120);
  }, [sale]);

  if (saleQuery.isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center gap-2 text-sm text-neutral-500">
        <Loader2 className="h-4 w-4 animate-spin" /> Yazdırma şablonu hazırlanıyor...
      </div>
    );
  }

  if (saleQuery.isError || !sale) {
    return (
      <div className="mx-auto max-w-md p-6 text-center text-sm text-red-600">
        {(saleQuery.error as Error)?.message ?? "Satış verisi alınamadı."}
      </div>
    );
  }

  return (
    <main className="print-wrap p-4 text-[12px] text-black">
      <style jsx global>{`
        @page {
          margin: 8mm;
        }

        @media print {
          body {
            background: #fff !important;
          }

          .no-print {
            display: none !important;
          }

          .print-wrap {
            width: 80mm;
            margin: 0 auto;
            padding: 0;
          }
        }
      `}</style>

      <div className="no-print mx-auto mb-4 flex max-w-[80mm] justify-end">
        <Button size="sm" onClick={() => window.print()}>
          <Printer className="h-4 w-4" /> Yazdır
        </Button>
      </div>

      <section className="mx-auto max-w-[80mm] border border-dashed border-neutral-400 p-3">
        <header className="border-b border-dashed border-neutral-400 pb-2 text-center">
          <h1 className="text-sm font-bold">POWERSA POS</h1>
          <p>{sale.document_type === "invoice" ? "SATIS FATURASI" : "SATIS FISI"}</p>
        </header>

        <div className="mt-2 space-y-1">
          <p>
            <strong>Fis No:</strong> {sale.receipt_no}
          </p>
          <p>
            <strong>Tarih:</strong> {new Date(sale.created_at).toLocaleString("tr-TR")}
          </p>
          <p>
            <strong>Satis Tipi:</strong> {sale.sale_type.toUpperCase()}
          </p>
          <p>
            <strong>Cari:</strong> {sale.customer.title ?? sale.customer.code ?? "-"}
          </p>
          <p>
            <strong>Durum:</strong> {sale.status.toUpperCase()}
          </p>
        </div>

        <table className="mt-3 w-full border-collapse">
          <thead>
            <tr className="border-y border-dashed border-neutral-400 text-left">
              <th className="py-1">Urun</th>
              <th className="py-1 text-right">Adet</th>
              <th className="py-1 text-right">Tutar</th>
            </tr>
          </thead>
          <tbody>
            {sale.items.map((item) => (
              <tr key={item.id} className="align-top">
                <td className="py-1 pr-2">
                  <p className="font-medium">{item.name ?? "-"}</p>
                  <p className="text-[10px] text-neutral-600">{item.sku ?? "-"}</p>
                </td>
                <td className="py-1 text-right">{item.qty}</td>
                <td className="py-1 text-right">{formatMoney(item.line_total)}</td>
              </tr>
            ))}
          </tbody>
        </table>

        <div className="mt-3 border-t border-dashed border-neutral-400 pt-2">
          <p className="flex items-center justify-between">
            <span>Ara Toplam</span>
            <span>{formatMoney(sale.subtotal)}</span>
          </p>
          <p className="flex items-center justify-between">
            <span>Iskonto</span>
            <span>{formatMoney(sale.discount_total)}</span>
          </p>
          <p className="flex items-center justify-between">
            <span>KDV</span>
            <span>{formatMoney(sale.vat_total)}</span>
          </p>
          <p className="mt-1 flex items-center justify-between text-[13px] font-bold">
            <span>GENEL TOPLAM</span>
            <span>{formatMoney(sale.grand_total)}</span>
          </p>
        </div>

        <footer className="mt-3 border-t border-dashed border-neutral-400 pt-2 text-center text-[10px] text-neutral-600">
          {sale.status === "cancelled" ? "IPTAL SATIS" : "Tesekkur ederiz"}
        </footer>
      </section>
    </main>
  );
}
