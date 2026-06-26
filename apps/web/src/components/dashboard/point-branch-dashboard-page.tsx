"use client";

import Image from "next/image";
import Link from "next/link";
import {
  BookOpen,
  Building2,
  CreditCard,
  DatabaseZap,
  HandCoins,
  PackageSearch,
  ReceiptText,
  Search,
  Sparkles,
  UserRound,
  Wallet,
} from "lucide-react";
import { useQuery } from "@tanstack/react-query";

import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { getCurrentPosSession, getPosDayEndReport, type PosDayEndReport, type PosLogoSyncSummary } from "@/lib/api";
import { cn } from "@/lib/utils";

const BRANCH_ACTIONS = [
  {
    title: "Hızlı Satış",
    copy: "Point satış ekranı",
    href: "/pos",
    icon: ReceiptText,
    className: "border-emerald-300/25 bg-emerald-300/12 text-emerald-100",
  },
  {
    title: "Müşteri",
    copy: "{branch} carilerini seç",
    href: "/customers",
    icon: UserRound,
    className: "border-violet-300/25 bg-violet-300/12 text-violet-100",
  },
  {
    title: "Ürün Arama",
    copy: "Stok ve fiyat kontrolü",
    href: "/search",
    icon: Search,
    className: "border-sky-300/25 bg-sky-300/12 text-sky-100",
  },
  {
    title: "Tahsilat",
    copy: "{branch} tahsilatı gir",
    href: "/collections",
    icon: HandCoins,
    className: "border-amber-300/25 bg-amber-300/12 text-amber-100",
  },
  {
    title: "Cari Hesap",
    copy: "Hareket ve bakiye",
    href: "/ledger",
    icon: CreditCard,
    className: "border-cyan-300/25 bg-cyan-300/12 text-cyan-100",
  },
  {
    title: "Masraf",
    copy: "{branch} kasa masrafı",
    href: "/pos/expenses",
    icon: Wallet,
    className: "border-blue-300/25 bg-blue-300/12 text-blue-100",
  },
  {
    title: "Gün Sonu",
    copy: "Kasa gün sonu",
    href: "/pos/day-end",
    icon: Building2,
    className: "border-orange-300/25 bg-orange-300/12 text-orange-100",
  },
  {
    title: "Kataloglar",
    copy: "Marka katalogları",
    href: "/catalogs",
    icon: BookOpen,
    className: "border-teal-300/25 bg-teal-300/12 text-teal-100",
  },
] as const;

const BATUM_VISUAL_TAGS = [
  { title: "Sahil", copy: "Karadeniz kıyısı", className: "border-emerald-200/18 bg-emerald-300/10 text-emerald-50" },
  { title: "Şehir", copy: "Batum silüeti", className: "border-red-200/18 bg-red-300/10 text-red-50" },
  { title: "Gece", copy: "Işıklı rota", className: "border-sky-200/18 bg-sky-300/10 text-sky-50" },
] as const;

const BATUM_GALLERY_IMAGES = [
  {
    src: "/brand/powersa-filter-showcase/batum/batum-coast.jpg",
    alt: "Batum sahil hattı ve şehir görünümü",
    label: "Batum sahili",
  },
  {
    src: "/brand/powersa-filter-showcase/batum/batum-skyline.jpg",
    alt: "Batum şehir silüeti",
    label: "Şehir silüeti",
  },
  {
    src: "/brand/powersa-filter-showcase/batum/batum-night.jpg",
    alt: "Batum gece şehir ışıkları",
    label: "Gece rotası",
  },
] as const;

const DEFAULT_BRANCH_GALLERY_IMAGES = [
  {
    src: "/brand/powersa-filter-showcase/optimized/branch-erzurum.jpg",
    alt: "Powersa Erzurum şube görünümü",
    label: "Erzurum merkez",
  },
  {
    src: "/brand/powersa-filter-showcase/optimized/branch-trabzon.jpg",
    alt: "Powersa Trabzon şube görünümü",
    label: "Trabzon şube",
  },
  {
    src: "/brand/powersa-filter-showcase/optimized/branch-samsun.jpg",
    alt: "Powersa Samsun şube görünümü",
    label: "Samsun şube",
  },
] as const;

const DEFAULT_BRANCH_VISUAL_TAGS = [
  { title: "Şube", copy: "Canlı operasyon", className: "border-emerald-200/18 bg-emerald-300/10 text-emerald-50" },
  { title: "Stok", copy: "Logo bağlantılı", className: "border-sky-200/18 bg-sky-300/10 text-sky-50" },
  { title: "Kasa", copy: "Gün sonu takip", className: "border-amber-200/18 bg-amber-300/10 text-amber-50" },
] as const;

function normalizeBranchLabel(value: string | null | undefined): string {
  const trimmed = value?.trim();

  if (!trimmed) {
    return "Şube";
  }

  return trimmed
    .toLocaleLowerCase("tr-TR")
    .replace(/\b\p{L}/gu, (letter) => letter.toLocaleUpperCase("tr-TR"));
}

function formatBranchDisplayName(branchLabel: string): string {
  const normalized = branchLabel.toLocaleUpperCase("tr-TR");

  if (normalized.includes("ŞUBE")) {
    return branchLabel;
  }

  if (normalized.includes("BATUM")) {
    return `${branchLabel} Şubesi`;
  }

  if (normalized.includes("MERKEZ")) {
    return `${branchLabel} Şubesi`;
  }

  return `${branchLabel} Merkez Şubesi`;
}

function getBranchProfile(branchLabel: string) {
  const normalized = branchLabel.toLocaleUpperCase("tr-TR");
  const branchDisplayName = formatBranchDisplayName(branchLabel);

  if (normalized.includes("BATUM")) {
    return {
      location: "Batum / Gürcistan",
      headline: "Batum sahili, Karadeniz rotası",
      copy: "Panel girişinde Batum şubesinin konumunu ve bölge odağını gerçek Batum görselleriyle gösterir.",
      images: BATUM_GALLERY_IMAGES,
      tags: BATUM_VISUAL_TAGS,
    };
  }

  const branchImage = normalized.includes("TRABZON")
    ? DEFAULT_BRANCH_GALLERY_IMAGES[1]
    : normalized.includes("SAMSUN")
      ? DEFAULT_BRANCH_GALLERY_IMAGES[2]
      : DEFAULT_BRANCH_GALLERY_IMAGES[0];
  const secondaryImages = DEFAULT_BRANCH_GALLERY_IMAGES.filter((image) => image.src !== branchImage.src).slice(0, 2);

  return {
    location: `${branchLabel} / Türkiye`,
    headline: `${branchDisplayName} operasyon merkezi`,
    copy: "Satış, cari, tahsilat, kasa ve gün sonu akışları şube kullanıcısına göre çalışır.",
    images: [branchImage, ...secondaryImages],
    tags: DEFAULT_BRANCH_VISUAL_TAGS,
  };
}

function formatCustomerLine(customer: ReturnType<typeof useSession>["selectedCustomer"]): string {
  if (!customer) {
    return "Henüz cari seçilmedi";
  }

  return `${customer.code} · ${customer.title}`;
}

function formatMoney(value: string | number | null | undefined, currency = "TL"): string {
  const amount = Number(value ?? 0);

  return `${currency} ${new Intl.NumberFormat("tr-TR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0)}`;
}

function syncTotal(summary: PosLogoSyncSummary | null | undefined, key: keyof PosLogoSyncSummary): number {
  return Number(summary?.[key] ?? 0);
}

function syncLabel(report: PosDayEndReport | null | undefined): string {
  if (!report) {
    return "Canlı rapor bekleniyor";
  }

  const sync = report.logo_sync;
  const failed = syncTotal(sync?.sales, "failed") + syncTotal(sync?.expenses, "failed") + syncTotal(sync?.collections, "failed");
  const pending =
    syncTotal(sync?.sales, "queued") +
    syncTotal(sync?.sales, "processing") +
    syncTotal(sync?.expenses, "queued") +
    syncTotal(sync?.expenses, "processing") +
    syncTotal(sync?.collections, "queued") +
    syncTotal(sync?.collections, "processing");

  if (failed > 0) {
    return `${failed} Logo kaydı hata bekliyor`;
  }

  if (pending > 0) {
    return `${pending} Logo kaydı sırada`;
  }

  return "Logo yazma kuyruğu temiz";
}

export function PointBranchDashboardPage() {
  const { user, selectedCustomer } = useSession();
  const branchLabel = normalizeBranchLabel(user?.branch_name ?? user?.branch_code ?? selectedCustomer?.branch_name ?? selectedCustomer?.branch_code);
  const branchDisplayName = formatBranchDisplayName(branchLabel);
  const selectedCustomerLine = formatCustomerLine(selectedCustomer);
  const branchProfile = getBranchProfile(branchLabel);
  const isBatumBranch = branchLabel.toLocaleUpperCase("tr-TR").includes("BATUM");
  const currencyLabel = isBatumBranch ? "GEL" : "TL";

  const currentSessionQuery = useQuery({
    queryKey: ["dashboard", "point-pos-session"],
    queryFn: () => getCurrentPosSession(),
    refetchInterval: 20_000,
    staleTime: 10_000,
  });

  const currentSession = currentSessionQuery.data?.data ?? null;
  const dayEndQuery = useQuery({
    queryKey: ["dashboard", "point-day-end", currentSession?.id ?? null],
    queryFn: () => getPosDayEndReport({ pos_session_id: currentSession?.id ?? undefined }),
    enabled: Boolean(currentSession?.id),
    refetchInterval: 20_000,
    staleTime: 10_000,
  });
  const dayEndReport = dayEndQuery.data?.data ?? null;
  const liveMetrics = [
    {
      title: "Canlı Satış",
      value: dayEndReport ? formatMoney(dayEndReport.summary.grand_total, currencyLabel) : "-",
      copy: dayEndReport ? `${dayEndReport.summary.paid_count} kayıt` : currentSessionQuery.isLoading ? "Kasa okunuyor" : "Açık kasa yok",
    },
    {
      title: "Nakit Kasa",
      value: dayEndReport ? formatMoney(dayEndReport.summary.expected_cash, currencyLabel) : "-",
      copy: currentSession?.cashbox?.code ? `${currentSession.cashbox.code} / ${currentSession.cashbox.name ?? "Kasa"}` : "Kasa seçilmedi",
    },
    {
      title: "Masraf",
      value: dayEndReport ? formatMoney(dayEndReport.expenses.total_amount, currencyLabel) : "-",
      copy: dayEndReport ? `${dayEndReport.expenses.count} kayıt` : "Canlı rapor bekleniyor",
    },
    {
      title: "Logo Sync",
      value: dayEndReport
        ? String(
            syncTotal(dayEndReport.logo_sync.sales, "synced") +
              syncTotal(dayEndReport.logo_sync.expenses, "synced") +
              syncTotal(dayEndReport.logo_sync.collections, "synced")
          )
        : "-",
      copy: syncLabel(dayEndReport),
    },
  ];

  return (
    <div className="mx-auto flex w-full max-w-[1480px] flex-col gap-4 text-slate-100">
      <section className="overflow-hidden rounded-[24px] border border-emerald-300/18 bg-[linear-gradient(135deg,rgba(9,21,29,0.96)_0%,rgba(12,38,30,0.94)_56%,rgba(18,24,34,0.96)_100%)] shadow-[0_24px_70px_-58px_rgba(0,0,0,0.9)]">
        <div className="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_420px] lg:items-stretch lg:p-6">
          <div className="flex min-h-[220px] flex-col justify-between rounded-[20px] border border-white/8 bg-white/[0.035] p-5">
            <div>
              <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-200/20 bg-emerald-300/10 px-3 py-1.5 text-[11px] font-black uppercase tracking-[0.16em] text-emerald-100">
                <Sparkles className="h-3.5 w-3.5" />
                Burası bizim şubemiz
              </div>
              <h1 className="max-w-[720px] text-4xl font-black leading-[1.04] text-white md:text-5xl">
                {branchDisplayName}
              </h1>
              <p className="mt-3 max-w-[620px] text-base font-semibold leading-7 text-slate-300">
                Satış, müşteri, tahsilat, masraf ve gün sonu işlemlerini buradan yönetin.
              </p>
            </div>

            <div className="mt-5 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
              {liveMetrics.map((metric) => (
                <div key={metric.title} className="rounded-[16px] border border-white/8 bg-black/16 p-3">
                  <span className="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.14em] text-emerald-100">
                    {metric.title === "Logo Sync" ? <DatabaseZap className="h-3.5 w-3.5" /> : null}
                    {metric.title}
                  </span>
                  <strong className="mt-2 block text-xl font-black text-white">{metric.value}</strong>
                  <span className="mt-1 block text-[11px] font-bold leading-4 text-slate-300">{metric.copy}</span>
                </div>
              ))}
            </div>

            <div className="mt-6 flex flex-wrap gap-3">
              <Button asChild className="h-12 rounded-[14px] bg-emerald-400 px-5 text-sm font-black text-slate-950 hover:bg-emerald-300">
                <Link href="/pos">Hızlı Satışa Git</Link>
              </Button>
              <Button
                asChild
                variant="outline"
                className="h-12 rounded-[14px] border-white/12 bg-white/6 px-5 text-sm font-black text-white hover:bg-white/10 hover:text-white"
              >
                <Link href="/customers">Cari Seç</Link>
              </Button>
            </div>
          </div>

          <div className="grid gap-3">
            <div className="relative overflow-hidden rounded-[20px] border border-emerald-200/15 bg-[#08131b] p-2">
              <div className="grid min-h-[248px] gap-2 sm:grid-cols-[1.35fr_0.85fr]">
                <div className="relative min-h-[248px] overflow-hidden rounded-[16px] border border-white/10 bg-slate-950">
                  <Image
                    src={branchProfile.images[0].src}
                    alt={branchProfile.images[0].alt}
                    fill
                    priority
                    sizes="(min-width: 1024px) 260px, 100vw"
                    className="object-cover opacity-90"
                  />
                  <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_12%,rgba(52,211,153,0.30),transparent_34%),linear-gradient(180deg,rgba(4,10,16,0.08)_0%,rgba(4,10,16,0.86)_100%)]" />
                  <div className="absolute left-4 top-4 rounded-full border border-white/16 bg-slate-950/60 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.18em] text-emerald-50 backdrop-blur">
                    {branchProfile.location}
                  </div>
                  <div className="absolute bottom-4 left-4 right-4">
                    <p className="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-100">Şube görsel alanı</p>
                    <h2 className="mt-1 text-2xl font-black leading-tight text-white">{branchProfile.headline}</h2>
                    <p className="mt-2 max-w-[300px] text-sm font-bold leading-5 text-slate-200">
                      {branchProfile.copy}
                    </p>
                  </div>
                </div>

                <div className="grid min-h-[170px] grid-cols-2 gap-2 sm:min-h-0 sm:grid-cols-1">
                  {branchProfile.images.slice(1).map((image) => (
                    <div key={image.src} className="relative min-h-[118px] overflow-hidden rounded-[16px] border border-white/10 bg-slate-950">
                      <Image src={image.src} alt={image.alt} fill sizes="(min-width: 1024px) 150px, 50vw" className="object-cover opacity-85" />
                      <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(4,10,16,0.04)_0%,rgba(4,10,16,0.72)_100%)]" />
                      <span className="absolute bottom-3 left-3 right-3 text-[11px] font-black uppercase tracking-[0.14em] text-white">
                        {image.label}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-3 lg:grid-cols-3">
              {branchProfile.tags.map((tag) => (
                <div key={tag.title} className={cn("rounded-[16px] border p-3", tag.className)}>
                  <span className="block text-sm font-black">{tag.title}</span>
                  <span className="mt-1 block text-[11px] font-bold text-slate-300">{tag.copy}</span>
                </div>
              ))}
            </div>

            <div className="rounded-[20px] border border-white/8 bg-[#08131b]/70 p-5">
              <span className="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Seçili Cari</span>
              <p className="mt-3 text-2xl font-black leading-tight text-white">{selectedCustomerLine}</p>
              <div className="mt-5 grid gap-3 text-sm font-bold text-slate-300">
                <div className="rounded-[16px] border border-white/8 bg-white/[0.035] p-4">
                  <span className="block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Şube</span>
                  <span className="mt-1 block text-lg font-black text-emerald-100">{branchDisplayName}</span>
                </div>
                <div className="rounded-[16px] border border-white/8 bg-white/[0.035] p-4">
                  <span className="block text-[10px] font-black uppercase tracking-[0.14em] text-slate-500">Panel</span>
                  <span className="mt-1 block text-lg font-black text-slate-100">Point B2B</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {BRANCH_ACTIONS.map((action) => {
          const Icon = action.icon;

          return (
            <Link
              key={action.href}
              href={action.href}
              className={cn(
                "group min-h-[132px] rounded-[18px] border p-4 transition hover:-translate-y-0.5 hover:bg-white/[0.07]",
                action.className
              )}
            >
              <div className="flex items-start justify-between gap-3">
                <span className="flex h-11 w-11 items-center justify-center rounded-[14px] border border-current/20 bg-black/18">
                  <Icon className="h-5 w-5" />
                </span>
                <PackageSearch className="h-4 w-4 opacity-0 transition group-hover:opacity-70" />
              </div>
              <h2 className="mt-5 text-xl font-black text-white">{action.title}</h2>
              <p className="mt-1 text-sm font-bold text-slate-300">{action.copy.replace("{branch}", branchDisplayName)}</p>
            </Link>
          );
        })}
      </section>
    </div>
  );
}
