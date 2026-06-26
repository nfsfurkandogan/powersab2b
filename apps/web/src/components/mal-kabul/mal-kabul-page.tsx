"use client";

import { useMemo, useState } from "react";
import {
  Barcode,
  CheckCircle2,
  ClipboardCheck,
  ClipboardList,
  DatabaseZap,
  Loader2,
  PackageCheck,
  Plus,
  RotateCcw,
  Save,
  Trash2,
  Truck,
} from "lucide-react";
import { toast } from "sonner";
import { useMutation } from "@tanstack/react-query";

import { createPurchaseReceipt, type PurchaseReceiptRecord } from "@/lib/api";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";

type ReceiptLine = {
  id: string;
  productCode: string;
  productName: string;
  expectedQuantity: number;
  acceptedQuantity: number;
  note: string;
};

type DraftLine = Omit<ReceiptLine, "id">;

const emptyLine: DraftLine = {
  productCode: "",
  productName: "",
  expectedQuantity: 1,
  acceptedQuantity: 1,
  note: "",
};

const panelClass =
  "border-[var(--brand-border)] bg-[linear-gradient(180deg,var(--surface)_0%,var(--surface-soft)_100%)] shadow-[0_18px_34px_-28px_rgba(33,52,22,0.28)]";

function nextLineId() {
  return `line-${Date.now()}-${Math.round(Math.random() * 10000)}`;
}

function toSafeQuantity(value: string) {
  const quantity = Number(value);

  if (!Number.isFinite(quantity) || quantity < 0) {
    return 0;
  }

  return Math.round(quantity);
}

export function MalKabulPage() {
  const [documentNo, setDocumentNo] = useState("");
  const [supplier, setSupplier] = useState("");
  const [warehouse, setWarehouse] = useState("");
  const [receivedAt, setReceivedAt] = useState(() => new Date().toISOString().slice(0, 10));
  const [note, setNote] = useState("");
  const [draftLine, setDraftLine] = useState<DraftLine>(emptyLine);
  const [lines, setLines] = useState<ReceiptLine[]>([]);
  const [lastReceipt, setLastReceipt] = useState<PurchaseReceiptRecord | null>(null);

  const totals = useMemo(
    () =>
      lines.reduce(
        (accumulator, line) => {
          accumulator.expected += line.expectedQuantity;
          accumulator.accepted += line.acceptedQuantity;
          accumulator.difference += line.expectedQuantity - line.acceptedQuantity;

          return accumulator;
        },
        { expected: 0, accepted: 0, difference: 0 }
      ),
    [lines]
  );

  const addLine = () => {
    const productCode = draftLine.productCode.trim();
    const productName = draftLine.productName.trim();

    if (!productCode && !productName) {
      toast.error("Ürün kodu veya ürün adı girin.");
      return;
    }

    setLines((current) => [
      ...current,
      {
        ...draftLine,
        id: nextLineId(),
        productCode,
        productName: productName || productCode,
        expectedQuantity: Math.max(1, draftLine.expectedQuantity),
        acceptedQuantity: Math.max(0, draftLine.acceptedQuantity),
        note: draftLine.note.trim(),
      },
    ]);
    setDraftLine(emptyLine);
  };

  const removeLine = (id: string) => {
    setLines((current) => current.filter((line) => line.id !== id));
  };

  const resetDraft = () => {
    setDocumentNo("");
    setSupplier("");
    setWarehouse("");
    setReceivedAt(new Date().toISOString().slice(0, 10));
    setNote("");
    setDraftLine(emptyLine);
    setLines([]);
  };

  const saveReceiptMutation = useMutation({
    mutationFn: () =>
      createPurchaseReceipt({
        document_no: documentNo.trim() || null,
        supplier_name: supplier.trim() || null,
        warehouse_code: warehouse.trim() || null,
        warehouse_name: warehouse.trim() || null,
        received_at: receivedAt,
        note: note.trim() || null,
        items: lines.map((line) => ({
          product_code: line.productCode || null,
          product_name: line.productName,
          expected_quantity: line.expectedQuantity,
          accepted_quantity: line.acceptedQuantity,
          note: line.note || null,
        })),
      }),
    onSuccess: (response) => {
      setLastReceipt(response.data);
      toast.success(`${response.data.receipt_no} Logo kuyruğuna alındı.`);
      resetDraft();
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : "Mal kabul kaydedilemedi.");
    },
  });

  const prepareReceipt = () => {
    if (lines.length === 0) {
      toast.error("Mal kabul için en az bir ürün satırı ekleyin.");
      return;
    }

    saveReceiptMutation.mutate();
  };

  return (
    <div className="space-y-4">
      <div className="grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
        <Card className={panelClass}>
          <CardHeader>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <CardTitle className="flex items-center gap-3 text-2xl font-black text-[var(--brand-primary-strong)]">
                <PackageCheck className="h-7 w-7 text-[var(--brand-primary)]" />
                Satınalma / Mal Kabul
              </CardTitle>
              <Badge variant="secondary" className="w-fit px-3 py-1 text-xs font-black">
                Logo Kuyruğu
              </Badge>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-3 md:grid-cols-2">
              <label className="space-y-2">
                <span className="text-xs font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">İrsaliye No</span>
                <Input value={documentNo} onChange={(event) => setDocumentNo(event.target.value)} placeholder="IRS-000001" />
              </label>
              <label className="space-y-2">
                <span className="text-xs font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">Tedarikçi</span>
                <Input value={supplier} onChange={(event) => setSupplier(event.target.value)} placeholder="Tedarikçi adı" />
              </label>
              <label className="space-y-2">
                <span className="text-xs font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">Depo / Ambar</span>
                <Input value={warehouse} onChange={(event) => setWarehouse(event.target.value)} placeholder="Merkez depo" />
              </label>
              <label className="space-y-2">
                <span className="text-xs font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">Kabul Tarihi</span>
                <Input type="date" value={receivedAt} onChange={(event) => setReceivedAt(event.target.value)} />
              </label>
            </div>

            <label className="space-y-2">
              <span className="text-xs font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">Not</span>
              <Textarea value={note} onChange={(event) => setNote(event.target.value)} placeholder="Belge veya teslimat notu" />
            </label>

            <div className="rounded-2xl border border-emerald-200/60 bg-emerald-50/80 p-3 text-sm text-emerald-950">
              <div className="flex items-start gap-2">
                <DatabaseZap className="mt-0.5 h-4 w-4 shrink-0" />
                <p className="font-bold">
                  Kaydettiğiniz mal kabul B2B veritabanına yazılır ve Logo GO Wings köprüsü için
                  <span className="font-black"> purchase-receipts</span> kuyruğuna alınır.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-1">
          <div className="rounded-[24px] border border-[var(--brand-border)] bg-[var(--surface)] p-5 shadow-[0_14px_28px_-24px_rgba(0,0,0,0.18)]">
            <ClipboardList className="h-7 w-7 text-[var(--brand-primary)]" />
            <p className="mt-4 text-xs font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">Beklenen</p>
            <p className="mt-2 text-3xl font-black text-[var(--brand-primary-strong)]">{totals.expected}</p>
          </div>
          <div className="rounded-[24px] border border-[var(--brand-border)] bg-[var(--surface)] p-5 shadow-[0_14px_28px_-24px_rgba(0,0,0,0.18)]">
            <ClipboardCheck className="h-7 w-7 text-[var(--brand-primary)]" />
            <p className="mt-4 text-xs font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">Kabul</p>
            <p className="mt-2 text-3xl font-black text-[var(--brand-primary-strong)]">{totals.accepted}</p>
          </div>
          <div className="rounded-[24px] border border-[var(--brand-border)] bg-[var(--surface)] p-5 shadow-[0_14px_28px_-24px_rgba(0,0,0,0.18)]">
            <Truck className="h-7 w-7 text-[var(--brand-primary)]" />
            <p className="mt-4 text-xs font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">Fark</p>
            <p className="mt-2 text-3xl font-black text-[var(--brand-primary-strong)]">{totals.difference}</p>
          </div>
        </div>
      </div>

      <Card className={panelClass}>
        <CardHeader>
          <CardTitle className="flex items-center gap-3 text-xl font-black text-[var(--brand-primary-strong)]">
            <Barcode className="h-6 w-6 text-[var(--brand-primary)]" />
            Ürün Satırları
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-3 lg:grid-cols-[1fr_1.35fr_0.7fr_0.7fr_1fr_auto]">
            <Input
              value={draftLine.productCode}
              onChange={(event) => setDraftLine((current) => ({ ...current, productCode: event.target.value }))}
              placeholder="Stok kodu / barkod"
            />
            <Input
              value={draftLine.productName}
              onChange={(event) => setDraftLine((current) => ({ ...current, productName: event.target.value }))}
              placeholder="Ürün adı"
            />
            <Input
              min={1}
              type="number"
              value={draftLine.expectedQuantity}
              onChange={(event) => setDraftLine((current) => ({ ...current, expectedQuantity: toSafeQuantity(event.target.value) }))}
              placeholder="Beklenen"
            />
            <Input
              min={0}
              type="number"
              value={draftLine.acceptedQuantity}
              onChange={(event) => setDraftLine((current) => ({ ...current, acceptedQuantity: toSafeQuantity(event.target.value) }))}
              placeholder="Kabul"
            />
            <Input
              value={draftLine.note}
              onChange={(event) => setDraftLine((current) => ({ ...current, note: event.target.value }))}
              placeholder="Satır notu"
            />
            <Button type="button" className="h-10 rounded-xl px-4 font-black" onClick={addLine}>
              <Plus className="h-4 w-4" />
              Ekle
            </Button>
          </div>

          <div className="overflow-hidden rounded-[22px] border border-[var(--brand-border)] bg-[var(--surface)]">
            <div className="grid grid-cols-[1fr_1.4fr_0.7fr_0.7fr_0.8fr_44px] gap-3 border-b border-[var(--brand-border)] bg-[var(--surface-soft)] px-4 py-3 text-xs font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)] max-lg:hidden">
              <span>Kod</span>
              <span>Ürün</span>
              <span className="text-right">Beklenen</span>
              <span className="text-right">Kabul</span>
              <span>Durum</span>
              <span />
            </div>

            {lines.length > 0 ? (
              <div className="divide-y divide-[var(--brand-border)]">
                {lines.map((line) => {
                  const isMissing = line.acceptedQuantity < line.expectedQuantity;

                  return (
                    <div key={line.id} className="grid gap-3 px-4 py-4 lg:grid-cols-[1fr_1.4fr_0.7fr_0.7fr_0.8fr_44px] lg:items-center">
                      <p className="truncate text-sm font-black text-[var(--brand-primary-strong)]">{line.productCode || "-"}</p>
                      <div className="min-w-0">
                        <p className="truncate text-sm font-black text-[var(--foreground)]">{line.productName}</p>
                        {line.note ? <p className="mt-1 truncate text-xs font-semibold text-[var(--muted-foreground)]">{line.note}</p> : null}
                      </div>
                      <p className="text-sm font-black text-[var(--foreground)] lg:text-right">{line.expectedQuantity}</p>
                      <p className="text-sm font-black text-[var(--foreground)] lg:text-right">{line.acceptedQuantity}</p>
                      <Badge variant={isMissing ? "outline" : "secondary"} className="w-fit font-black">
                        {isMissing ? "Eksik" : "Tam"}
                      </Badge>
                      <Button type="button" size="icon" variant="ghost" className="h-9 w-9 rounded-xl" onClick={() => removeLine(line.id)}>
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  );
                })}
              </div>
            ) : (
              <div className="px-4 py-10 text-center">
                <PackageCheck className="mx-auto h-10 w-10 text-[var(--brand-primary)]" />
                <p className="mt-4 text-lg font-black text-[var(--brand-primary-strong)]">Henüz ürün satırı yok</p>
              </div>
            )}
          </div>

          <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
            <Button type="button" variant="outline" className="h-11 rounded-xl px-5 font-black" onClick={resetDraft}>
              <RotateCcw className="h-4 w-4" />
              Temizle
            </Button>
            <Button
              type="button"
              className="h-11 rounded-xl px-5 font-black"
              disabled={saveReceiptMutation.isPending}
              onClick={prepareReceipt}
            >
              {saveReceiptMutation.isPending ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Save className="h-4 w-4" />
              )}
              Kaydet ve Logo Kuyruğuna Al
            </Button>
          </div>
        </CardContent>
      </Card>

      {lastReceipt ? (
        <Card className="border-emerald-200 bg-emerald-50/90">
          <CardContent className="flex flex-col gap-3 p-4 text-emerald-950 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-start gap-3">
              <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" />
              <div>
                <p className="text-sm font-black">{lastReceipt.receipt_no}</p>
                <p className="text-xs font-bold">
                  Logo durumu: {lastReceipt.logo_sync_status ?? lastReceipt.status}
                  {lastReceipt.logo_external_ref ? ` · Ref: ${lastReceipt.logo_external_ref}` : ""}
                </p>
              </div>
            </div>
            <Badge className="w-fit bg-emerald-700 text-white">Kuyrukta</Badge>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
