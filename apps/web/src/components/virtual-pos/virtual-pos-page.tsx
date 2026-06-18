"use client";

import { type FormEvent, useMemo, useState } from "react";
import { CheckCircle2, CreditCard, LockKeyhole, ReceiptText, ShieldCheck, UserRound } from "lucide-react";
import { toast } from "sonner";

import { useSession } from "@/components/auth/session-provider";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { cn } from "@/lib/utils";

const INSTALLMENT_OPTIONS = ["1", "2", "3", "4", "5", "6"] as const;

function onlyDigits(value: string) {
  return value.replace(/\D/g, "");
}

function formatCardNumber(value: string) {
  return onlyDigits(value)
    .slice(0, 16)
    .replace(/(\d{4})(?=\d)/g, "$1 ")
    .trim();
}

function formatExpiry(value: string) {
  const digits = onlyDigits(value).slice(0, 4);
  if (digits.length <= 2) {
    return digits;
  }

  return `${digits.slice(0, 2)}/${digits.slice(2)}`;
}

function formatAmountInput(value: string) {
  const normalized = value.replace(/[^\d,.]/g, "");
  const separatorIndex = normalized.indexOf(",");
  const integerRaw = separatorIndex === -1 ? normalized : normalized.slice(0, separatorIndex);
  const decimalRaw = separatorIndex === -1 ? "" : normalized.slice(separatorIndex + 1).replace(/\D/g, "");
  const integerDigits = integerRaw.replace(/[^\d]/g, "");
  const formattedInteger = integerDigits ? Number(integerDigits).toLocaleString("tr-TR") : "";

  if (separatorIndex !== -1) {
    return `${formattedInteger},${decimalRaw.slice(0, 2)}`;
  }

  return formattedInteger;
}

function parseAmount(value: string) {
  const parsed = Number(value.replace(/\./g, "").replace(",", "."));
  return Number.isFinite(parsed) ? parsed : 0;
}

function formatMoney(value: number) {
  return new Intl.NumberFormat("tr-TR", {
    style: "currency",
    currency: "TRY",
    minimumFractionDigits: 2,
  }).format(value);
}

function isValidCardNumber(value: string) {
  const digits = onlyDigits(value);
  if (digits.length < 13 || digits.length > 16) {
    return false;
  }

  let sum = 0;
  let doubleDigit = false;

  for (let index = digits.length - 1; index >= 0; index -= 1) {
    let digit = Number(digits[index]);
    if (doubleDigit) {
      digit *= 2;
      if (digit > 9) {
        digit -= 9;
      }
    }
    sum += digit;
    doubleDigit = !doubleDigit;
  }

  return sum % 10 === 0;
}

function isValidExpiry(value: string) {
  const [monthValue, yearValue] = value.split("/");
  const month = Number(monthValue);
  const year = Number(yearValue);

  if (!Number.isInteger(month) || month < 1 || month > 12 || !Number.isInteger(year) || yearValue.length !== 2) {
    return false;
  }

  const now = new Date();
  const currentYear = now.getFullYear() % 100;
  const currentMonth = now.getMonth() + 1;

  return year > currentYear || (year === currentYear && month >= currentMonth);
}

function maskedCard(value: string) {
  const digits = onlyDigits(value);
  if (digits.length < 4) {
    return "**** **** ****";
  }

  return `**** **** **** ${digits.slice(-4)}`;
}

export function VirtualPosPage() {
  const { selectedCustomer, user } = useSession();
  const [cardHolder, setCardHolder] = useState("");
  const [cardNumber, setCardNumber] = useState("");
  const [expiry, setExpiry] = useState("");
  const [cvv, setCvv] = useState("");
  const [amount, setAmount] = useState("");
  const [installment, setInstallment] = useState<(typeof INSTALLMENT_OPTIONS)[number]>("1");
  const [description, setDescription] = useState("");
  const [lastPreview, setLastPreview] = useState<{
    amount: number;
    card: string;
    installment: string;
  } | null>(null);

  const numericAmount = useMemo(() => parseAmount(amount), [amount]);
  const validationMessage = useMemo(() => {
    if (!selectedCustomer) {
      return "Cari seçimi zorunlu.";
    }
    if (!cardHolder.trim()) {
      return "Kart sahibi zorunlu.";
    }
    if (!isValidCardNumber(cardNumber)) {
      return "Kart numarasını kontrol edin.";
    }
    if (!isValidExpiry(expiry)) {
      return "Son kullanma tarihini kontrol edin.";
    }
    if (onlyDigits(cvv).length < 3) {
      return "CVV zorunlu.";
    }
    if (numericAmount <= 0) {
      return "Tutar 0'dan büyük olmalı.";
    }

    return null;
  }, [cardHolder, cardNumber, cvv, expiry, numericAmount, selectedCustomer]);
  const canSubmit = validationMessage === null;

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!canSubmit) {
      toast.error(validationMessage ?? "Formu kontrol edin.");
      return;
    }

    setLastPreview({
      amount: numericAmount,
      card: maskedCard(cardNumber),
      installment,
    });
    toast.info("Sanal Pos sağlayıcısı bağlanınca provizyon bu ekrandan başlatılacak.");
  };

  return (
    <div className="space-y-4">
      <section className="rounded-[18px] border border-[var(--brand-border)] bg-[linear-gradient(135deg,var(--surface)_0%,var(--surface-soft)_100%)] px-5 py-5 shadow-[0_24px_54px_-44px_rgba(18,40,26,0.55)]">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              <span className="inline-flex h-10 w-10 items-center justify-center rounded-[14px] bg-[var(--brand-primary)] text-[var(--primary-foreground)]">
                <CreditCard className="h-5 w-5" />
              </span>
              <div>
                <h2 className="text-2xl font-black tracking-tight text-[var(--brand-primary-strong)]">Sanal Pos</h2>
                <p className="mt-1 text-sm font-semibold text-[var(--muted-foreground)]">Kartlı ödeme provizyon ekranı</p>
              </div>
            </div>
          </div>
          <Badge variant="outline" className="w-fit border-amber-300/45 bg-amber-300/10 text-amber-700">
            Entegrasyon Bekliyor
          </Badge>
        </div>
      </section>

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
        <Card className="rounded-[18px]">
          <CardHeader className="border-b border-[var(--brand-border)]">
            <CardTitle className="flex items-center gap-2 text-base font-black text-[var(--brand-primary-strong)]">
              <LockKeyhole className="h-4 w-4" /> Kart Bilgileri
            </CardTitle>
          </CardHeader>
          <CardContent className="p-5">
            <form className="grid gap-4" onSubmit={handleSubmit}>
              <div className="grid gap-4 md:grid-cols-2">
                <label className="space-y-1.5">
                  <span className="text-xs font-black uppercase tracking-[0.1em] text-[var(--muted-foreground)]">Cari</span>
                  <Input value={selectedCustomer ? `${selectedCustomer.code} - ${selectedCustomer.title}` : ""} disabled />
                </label>
                <label className="space-y-1.5">
                  <span className="text-xs font-black uppercase tracking-[0.1em] text-[var(--muted-foreground)]">Kullanıcı</span>
                  <Input value={user?.name ?? ""} disabled />
                </label>
              </div>

              <label className="space-y-1.5">
                <span className="text-xs font-black uppercase tracking-[0.1em] text-[var(--muted-foreground)]">Kart Sahibi</span>
                <Input
                  value={cardHolder}
                  onChange={(event) => setCardHolder(event.target.value.toLocaleUpperCase("tr-TR"))}
                  placeholder="AD SOYAD"
                  autoComplete="cc-name"
                />
              </label>

              <label className="space-y-1.5">
                <span className="text-xs font-black uppercase tracking-[0.1em] text-[var(--muted-foreground)]">Kart Numarası</span>
                <Input
                  value={cardNumber}
                  onChange={(event) => setCardNumber(formatCardNumber(event.target.value))}
                  placeholder="0000 0000 0000 0000"
                  inputMode="numeric"
                  autoComplete="cc-number"
                />
              </label>

              <div className="grid gap-4 md:grid-cols-3">
                <label className="space-y-1.5">
                  <span className="text-xs font-black uppercase tracking-[0.1em] text-[var(--muted-foreground)]">SKT</span>
                  <Input
                    value={expiry}
                    onChange={(event) => setExpiry(formatExpiry(event.target.value))}
                    placeholder="AA/YY"
                    inputMode="numeric"
                    autoComplete="cc-exp"
                  />
                </label>
                <label className="space-y-1.5">
                  <span className="text-xs font-black uppercase tracking-[0.1em] text-[var(--muted-foreground)]">CVV</span>
                  <Input
                    value={cvv}
                    onChange={(event) => setCvv(onlyDigits(event.target.value).slice(0, 4))}
                    placeholder="000"
                    inputMode="numeric"
                    autoComplete="cc-csc"
                    type="password"
                  />
                </label>
                <label className="space-y-1.5">
                  <span className="text-xs font-black uppercase tracking-[0.1em] text-[var(--muted-foreground)]">Taksit</span>
                  <Select value={installment} onValueChange={(value) => setInstallment(value as typeof installment)}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {INSTALLMENT_OPTIONS.map((option) => (
                        <SelectItem key={option} value={option}>
                          {option === "1" ? "Peşin" : `${option} Taksit`}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </label>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <label className="space-y-1.5">
                  <span className="text-xs font-black uppercase tracking-[0.1em] text-[var(--muted-foreground)]">Tutar</span>
                  <Input
                    value={amount}
                    onChange={(event) => setAmount(formatAmountInput(event.target.value))}
                    placeholder="0,00"
                    inputMode="decimal"
                  />
                </label>
                <label className="space-y-1.5">
                  <span className="text-xs font-black uppercase tracking-[0.1em] text-[var(--muted-foreground)]">Açıklama</span>
                  <Input
                    value={description}
                    onChange={(event) => setDescription(event.target.value)}
                    placeholder="Sipariş / tahsilat notu"
                  />
                </label>
              </div>

              <div className="flex flex-col gap-3 rounded-[14px] border border-[var(--brand-border)] bg-[var(--surface-soft)] px-4 py-3 md:flex-row md:items-center md:justify-between">
                <p className={cn("text-sm font-bold", validationMessage ? "text-[var(--muted-foreground)]" : "text-emerald-600")}>
                  {validationMessage ?? "Ödeme bilgileri hazır."}
                </p>
                <Button type="submit" className="gap-2 md:w-[190px]" disabled={!canSubmit}>
                  <CreditCard className="h-4 w-4" /> Ödemeyi Başlat
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <aside className="space-y-4">
          <Card className="rounded-[18px]">
            <CardHeader className="border-b border-[var(--brand-border)]">
              <CardTitle className="flex items-center gap-2 text-base font-black text-[var(--brand-primary-strong)]">
                <ReceiptText className="h-4 w-4" /> Ödeme Özeti
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 p-5">
              <div className="rounded-[14px] border border-[var(--brand-border)] bg-[var(--surface-soft)] p-4">
                <p className="text-[11px] font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">Cari</p>
                <p className="mt-1 line-clamp-2 text-sm font-black text-[var(--brand-primary-strong)]">
                  {selectedCustomer?.title ?? "Cari seçilmedi"}
                </p>
                <p className="mt-1 text-xs font-semibold text-[var(--muted-foreground)]">{selectedCustomer?.code ?? "-"}</p>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="rounded-[14px] border border-[var(--brand-border)] bg-[var(--surface-soft)] p-4">
                  <p className="text-[11px] font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">Tutar</p>
                  <p className="mt-1 text-lg font-black text-[var(--brand-primary-strong)]">{formatMoney(numericAmount)}</p>
                </div>
                <div className="rounded-[14px] border border-[var(--brand-border)] bg-[var(--surface-soft)] p-4">
                  <p className="text-[11px] font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">Taksit</p>
                  <p className="mt-1 text-lg font-black text-[var(--brand-primary-strong)]">{installment === "1" ? "Peşin" : installment}</p>
                </div>
              </div>

              {lastPreview ? (
                <div className="rounded-[14px] border border-emerald-300/45 bg-emerald-300/10 p-4 text-emerald-700">
                  <div className="flex items-center gap-2 text-sm font-black">
                    <CheckCircle2 className="h-4 w-4" /> Provizyon Ön Kontrolü
                  </div>
                  <p className="mt-2 text-sm font-bold">{lastPreview.card}</p>
                  <p className="mt-1 text-sm font-bold">{formatMoney(lastPreview.amount)}</p>
                </div>
              ) : null}
            </CardContent>
          </Card>

          <Card className="rounded-[18px]">
            <CardContent className="space-y-3 p-5">
              <div className="flex items-start gap-3">
                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[12px] bg-[var(--brand-primary-soft)] text-[var(--brand-primary)]">
                  <ShieldCheck className="h-4 w-4" />
                </span>
                <div>
                  <p className="text-sm font-black text-[var(--brand-primary-strong)]">Güvenli Akış</p>
                  <p className="mt-1 text-sm font-semibold text-[var(--muted-foreground)]">
                    Kart verisi kalıcı olarak saklanmaz; sağlayıcı entegrasyonu token/provizyon akışıyla bağlanmalıdır.
                  </p>
                </div>
              </div>
              <div className="flex items-start gap-3">
                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[12px] bg-[var(--brand-primary-soft)] text-[var(--brand-primary)]">
                  <UserRound className="h-4 w-4" />
                </span>
                <div>
                  <p className="text-sm font-black text-[var(--brand-primary-strong)]">Cari Bağlantısı</p>
                  <p className="mt-1 text-sm font-semibold text-[var(--muted-foreground)]">
                    Ödeme, seçili cari üzerinden provizyon ve tahsilat kaydına bağlanacak şekilde hazırlandı.
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </aside>
      </div>
    </div>
  );
}
