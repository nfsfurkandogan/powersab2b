"use client";

import { useEffect, useMemo, useRef } from "react";
import { useParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { Loader2, Printer } from "lucide-react";

import { Button } from "@/components/ui/button";
import { getOrderDetail, type OrderDetailResponse } from "@/lib/api";

type PrintOrder = OrderDetailResponse["order"];
type PrintOrderItem = PrintOrder["items"][number] & {
  raf?: string | null;
  raf_adresi?: string | null;
  rafAdresi?: string | null;
  shelf_address?: string | null;
  location?: string | null;
};

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
    second: "2-digit",
  }).format(parsed);
}

function formatShippingMethod(value: string | null | undefined): string {
  const labels: Record<string, string> = {
    kargo: "KARGO",
    depo_teslim: "DEPO TESLİM",
    kendi_arac: "KENDİ ARAÇ",
  };

  if (!value) {
    return "-";
  }

  return labels[value] ?? value.toUpperCase();
}

function compactText(value: string | number | null | undefined): string {
  const normalized = String(value ?? "").trim();
  return normalized.length > 0 ? normalized : "-";
}

function upperText(value: string | number | null | undefined): string {
  return compactText(value).toLocaleUpperCase("tr-TR");
}

function buildBarcodeDataUri(value: string): string {
  const seed = value || "POWERSA";
  const bars: string[] = [];
  let x = 0;

  for (let index = 0; index < seed.length; index += 1) {
    const code = seed.charCodeAt(index);
    const width = 1 + (code % 3);
    const gap = 1 + ((code >> 2) % 2);
    const height = 30 + ((code >> 1) % 12);

    bars.push(`<rect x="${x}" y="${42 - height}" width="${width}" height="${height}" fill="#111"/>`);
    x += width + gap;
  }

  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="220" height="48" viewBox="0 0 220 48" shape-rendering="crispEdges">
    <rect width="220" height="48" fill="#fff"/>
    ${bars.join("")}
    <text x="110" y="46" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="7" font-weight="700" fill="#111">${seed}</text>
  </svg>`;

  return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
}

function getShelfAddress(item: PrintOrderItem): string {
  return compactText(item.raf ?? item.raf_adresi ?? item.rafAdresi ?? item.shelf_address ?? item.location);
}

function HeaderInfo({
  order,
  barcodeDataUri,
  customerLocation,
  senderName,
  printedAt,
}: {
  order: PrintOrder;
  barcodeDataUri: string;
  customerLocation: string;
  senderName: string | null | undefined;
  printedAt: string;
}) {
  const addressLine = [order.customer?.phone, order.customer?.address].filter(Boolean).join(" / ");

  return (
    <header className="print-block">
      <div className="grid grid-cols-[1fr_72mm] items-start gap-[8mm]">
        <div className="pt-[13mm] text-[10.5px] font-bold leading-[1.28]">
          <InfoLine label="Kodu" value={upperText(order.customer?.code)} />
          <InfoLine label="Ünvanı" value={upperText(order.customer?.title)} />
          <InfoLine label="Adres" value={upperText(addressLine || order.customer?.address)} />
        </div>

        <div className="flex flex-col items-end text-[10.5px] font-bold leading-tight">
          <img className="barcode-image h-[17mm] w-[58mm]" src={barcodeDataUri} alt="Sipariş barkodu" />
          <span className="mt-[2mm]">{printedAt}</span>
        </div>
      </div>

      <div className="relative -mt-[18mm] grid grid-cols-[1fr_70mm_1fr] items-start gap-[6mm]">
        <div />
        <h1 className="pt-[4mm] text-center text-[22px] font-black tracking-[0.02em] text-[#303030]">
          SİPARİŞ FORMU
        </h1>
        <div />
      </div>

      <div className="mt-[5mm] grid grid-cols-[1fr_72mm] gap-[8mm] text-[10.5px] font-bold leading-[1.28]">
        <InfoLine label="İl - İlçe" value={upperText(customerLocation)} />
        <div>
          <InfoLine label="Sipariş No" value={order.order_no} />
          <InfoLine label="Sipariş Tarihi" value={formatDateTime(order.ordered_at)} />
          <InfoLine label="Plasiyer" value={upperText(order.salesperson?.name)} />
          <InfoLine label="Gönderen" value={upperText(senderName)} />
          <InfoLine label="Gönderi Şekli" value={formatShippingMethod(order.shipping_method ?? order.origin?.shipping_method)} />
        </div>
      </div>

      <div className="mt-[3mm] border-b border-[#ca6e6e]" />
    </header>
  );
}

function InfoLine({ label, value }: { label: string; value: string }) {
  return (
    <p className="grid grid-cols-[22mm_1fr] gap-[2mm]">
      <span>{label}</span>
      <span>{value}</span>
    </p>
  );
}

function OrderNote({ note }: { note: string }) {
  return (
    <section className="print-block mt-[9mm] flex min-h-[18mm] items-start gap-[3mm] text-[11px]">
      <p className="shrink-0 font-black">
        Sipariş Notu
      </p>
      <p className="whitespace-pre-line font-semibold">{note.trim() ? note : "-"}</p>
    </section>
  );
}

function PrintableTable({ items, totalQuantity }: { items: PrintOrderItem[]; totalQuantity: number }) {
  return (
    <section className="mt-[1mm]">
      <table className="order-table w-full border-collapse text-[9px] leading-tight">
        <thead>
          <tr>
            <th className="w-[7mm] text-center">Sr</th>
            <th className="w-[35mm] text-left">Ürün Kodu</th>
            <th className="text-left">Ürün Adı</th>
            <th className="w-[24mm] text-center">Üretici</th>
            <th className="w-[28mm] text-center">Raf Adresi</th>
            <th className="w-[17mm] text-center">Miktar</th>
            <th className="w-[14mm] text-center" />
            <th className="w-[14mm] text-center">Stok</th>
          </tr>
        </thead>
        <tbody>
          {items.length === 0 ? (
            <tr>
              <td colSpan={8} className="px-[2mm] py-[10mm] text-center">
                Sipariş kalemi bulunamadı.
              </td>
            </tr>
          ) : (
            items.map((item, index) => {
              const availableStock = item.logo_stock?.available_total;
              const stockIsMissing = (availableStock ?? 0) < item.quantity;

              return (
                <tr key={item.id} className={stockIsMissing ? "stock-missing" : undefined}>
                  <td className="text-center font-bold">{index + 1}</td>
                  <td className="font-bold">{upperText(item.sku)}</td>
                  <td className="font-semibold">{upperText(item.name)}</td>
                  <td className="text-center">{compactText(item.brand)}</td>
                  <td className="text-center font-semibold">{getShelfAddress(item)}</td>
                  <td className="text-center font-black">{item.quantity}</td>
                  <td />
                  <td className={stockIsMissing ? "text-center font-black" : "text-center font-semibold"}>
                    {availableStock ?? "-"}
                  </td>
                </tr>
              );
            })
          )}
          <tr className="total-row">
            <td colSpan={5} className="text-right font-black">Toplam:</td>
            <td className="text-center font-black">{totalQuantity}</td>
            <td />
            <td />
          </tr>
        </tbody>
      </table>
    </section>
  );
}

function BottomNotes() {
  return (
    <footer className="print-block mt-[9mm] grid grid-cols-[1fr_64mm] gap-[12mm] text-[10px] text-[#2f2f2f]">
      <div className="min-h-[32mm] rounded-[1mm] border border-[#d8b0b0]" />
      <div className="min-h-[32mm] rounded-[1mm] border border-[#d8b0b0]" />
      <p className="col-span-2 text-right text-[10px] font-bold">1/1</p>
    </footer>
  );
}

export default function WarehouseOrderPrintPage() {
  const params = useParams<{ id: string }>();
  const printedRef = useRef(false);

  const orderId = useMemo(() => {
    const parsed = Number(params.id);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
  }, [params.id]);

  const orderQuery = useQuery({
    queryKey: ["warehouse", "print", "order", orderId],
    queryFn: () => {
      if (!orderId) {
        throw new Error("Geçersiz sipariş ID");
      }

      return getOrderDetail(orderId);
    },
    enabled: Boolean(orderId),
  });

  const order = orderQuery.data?.order;

  useEffect(() => {
    if (!order || printedRef.current) {
      return;
    }

    printedRef.current = true;
    window.setTimeout(() => window.print(), 160);
  }, [order]);

  if (orderQuery.isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center gap-2 text-sm text-neutral-500">
        <Loader2 className="h-4 w-4 animate-spin" /> Sipariş formu hazırlanıyor...
      </div>
    );
  }

  if (orderQuery.isError || !order) {
    return (
      <div className="mx-auto max-w-md p-6 text-center text-sm text-red-600">
        {(orderQuery.error as Error)?.message ?? "Sipariş verisi alınamadı."}
      </div>
    );
  }

  const customer = order.customer;
  const totalQuantity = order.items.reduce((sum, item) => sum + item.quantity, 0);
  const destination = [customer?.city, customer?.district].filter(Boolean).join(" / ");
  const note = order.origin?.note ?? order.note ?? "";
  const senderName = order.salesperson?.name ?? order.created_by?.name;
  const barcodeDataUri = buildBarcodeDataUri(order.order_no);
  const customerAddress = String(customer?.address ?? "").trim();
  const customerLocation = destination || customerAddress || "Adres bilgisi yok";
  const printedAt = formatDateTime(new Date().toISOString());

  return (
    <main className="min-h-screen bg-[#565656] px-4 py-5 text-[#222]">
      <style jsx global>{`
        @page {
          size: A4 portrait;
          margin: 8mm;
        }

        html,
        body {
          background: #565656;
        }

        .order-form-sheet {
          width: 210mm;
          min-height: 297mm;
          font-family:
            Arial, Helvetica, sans-serif;
          color: #222;
          -webkit-print-color-adjust: exact;
          print-color-adjust: exact;
        }

        .order-table th,
        .order-table td {
          border: 1px solid #535353;
          padding: 1.15mm 1.3mm;
          vertical-align: middle;
        }

        .order-table thead {
          display: table-header-group;
        }

        .order-table thead th {
          background: #f4f0e7;
          color: #222222;
          font-weight: 900;
          font-size: 10px;
          padding-top: 1.8mm;
          padding-bottom: 1.8mm;
        }

        .order-table tbody tr:nth-child(even):not(.total-row) {
          background: #fffdf8;
        }

        .order-table tbody tr {
          break-inside: avoid;
          page-break-inside: avoid;
        }

        .order-table .stock-missing {
          background: #fff8ef !important;
        }

        .total-row {
          background: #f4f0e7;
        }

        .barcode-image {
          image-rendering: pixelated;
        }

        .print-block {
          break-inside: avoid;
        }

        @media print {
          html,
          body {
            margin: 0 !important;
            width: 297mm;
            overflow: visible !important;
            background: #ffffff !important;
          }

          .no-print {
            display: none !important;
          }

          main {
            padding: 0 !important;
            background: #ffffff !important;
          }

          .order-form-sheet {
            width: auto !important;
            min-height: auto !important;
            box-shadow: none !important;
          }
        }
      `}</style>

      <div className="no-print mx-auto mb-3 flex w-full max-w-[210mm] justify-end">
        <Button className="bg-[#004c2e] text-white hover:bg-[#063b27]" size="sm" onClick={() => window.print()}>
          <Printer className="h-4 w-4" /> Yazdır / PDF Kaydet
        </Button>
      </div>

      <section className="order-form-sheet mx-auto bg-[#fff8e8] px-[8mm] py-[8mm] shadow-2xl">
        <HeaderInfo
          order={order}
          barcodeDataUri={barcodeDataUri}
          customerLocation={customerLocation}
          senderName={senderName}
          printedAt={printedAt}
        />
        <OrderNote note={note} />
        <PrintableTable items={order.items as PrintOrderItem[]} totalQuantity={totalQuantity} />
        <BottomNotes />
      </section>
    </main>
  );
}
