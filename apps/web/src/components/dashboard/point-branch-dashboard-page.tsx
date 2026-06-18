"use client";

import Image from "next/image";
import Link from "next/link";
import {
  BookOpen,
  Building2,
  CreditCard,
  HandCoins,
  PackageSearch,
  ReceiptText,
  Search,
  Sparkles,
  UserRound,
  Wallet,
} from "lucide-react";

import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const BRANCH_ACTIONS = [
  {
    title: "Hızlı Satış",
    copy: "Point satış ekranı",
    href: "/pos#point-sales",
    icon: ReceiptText,
    className: "border-emerald-300/25 bg-emerald-300/12 text-emerald-100",
  },
  {
    title: "Müşteri",
    copy: "Batum carilerini seç",
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
    copy: "Batum tahsilatı gir",
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
    copy: "Batum kasa masrafı",
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

function normalizeBranchLabel(value: string | null | undefined): string {
  const trimmed = value?.trim();

  if (!trimmed) {
    return "Batum";
  }

  return trimmed
    .toLocaleLowerCase("tr-TR")
    .replace(/\b\p{L}/gu, (letter) => letter.toLocaleUpperCase("tr-TR"));
}

function formatCustomerLine(customer: ReturnType<typeof useSession>["selectedCustomer"]): string {
  if (!customer) {
    return "Henüz cari seçilmedi";
  }

  return `${customer.code} · ${customer.title}`;
}

export function PointBranchDashboardPage() {
  const { user, selectedCustomer } = useSession();
  const branchLabel = normalizeBranchLabel(user?.branch_name ?? user?.branch_code ?? selectedCustomer?.branch_name ?? selectedCustomer?.branch_code);
  const selectedCustomerLine = formatCustomerLine(selectedCustomer);

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
                {branchLabel} Şubesi
              </h1>
              <p className="mt-3 max-w-[620px] text-base font-semibold leading-7 text-slate-300">
                Satış, müşteri, tahsilat, masraf ve gün sonu işlemlerini buradan yönetin.
              </p>
            </div>

            <div className="mt-6 flex flex-wrap gap-3">
              <Button asChild className="h-12 rounded-[14px] bg-emerald-400 px-5 text-sm font-black text-slate-950 hover:bg-emerald-300">
                <Link href="/pos#point-sales">Hızlı Satışa Git</Link>
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
                    src={BATUM_GALLERY_IMAGES[0].src}
                    alt={BATUM_GALLERY_IMAGES[0].alt}
                    fill
                    priority
                    sizes="(min-width: 1024px) 260px, 100vw"
                    className="object-cover opacity-90"
                  />
                  <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_12%,rgba(52,211,153,0.30),transparent_34%),linear-gradient(180deg,rgba(4,10,16,0.08)_0%,rgba(4,10,16,0.86)_100%)]" />
                  <div className="absolute left-4 top-4 rounded-full border border-white/16 bg-slate-950/60 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.18em] text-emerald-50 backdrop-blur">
                    Batum / Gürcistan
                  </div>
                  <div className="absolute bottom-4 left-4 right-4">
                    <p className="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-100">Şube görsel alanı</p>
                    <h2 className="mt-1 text-2xl font-black leading-tight text-white">Batum sahili, Karadeniz rotası</h2>
                    <p className="mt-2 max-w-[300px] text-sm font-bold leading-5 text-slate-200">
                      Panel girişinde şubenin konumunu ve bölge odağını gerçek Batum görselleriyle gösterir.
                    </p>
                  </div>
                </div>

                <div className="grid min-h-[170px] grid-cols-2 gap-2 sm:min-h-0 sm:grid-cols-1">
                  {BATUM_GALLERY_IMAGES.slice(1).map((image) => (
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
              {BATUM_VISUAL_TAGS.map((tag) => (
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
                  <span className="mt-1 block text-lg font-black text-emerald-100">{branchLabel}</span>
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
              <p className="mt-1 text-sm font-bold text-slate-300">{action.copy}</p>
            </Link>
          );
        })}
      </section>
    </div>
  );
}
