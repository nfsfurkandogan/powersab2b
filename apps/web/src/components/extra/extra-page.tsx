"use client";

import Link from "next/link";
import { useCallback, useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowRight,
  BookMarked,
  Building2,
  CreditCard,
  LifeBuoy,
  Mail,
  MapPin,
  Phone,
  UserRound,
  type LucideIcon,
} from "lucide-react";

import { useSession } from "@/components/auth/session-provider";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { getProductFilterOptions } from "@/lib/api";

const BANK_ACCOUNTS = [
  {
    bank: "Ziraat Bankası",
    branch: "0112",
    accountNo: "97607896",
    iban: "TR410001002772976078965006",
  },
  {
    bank: "Bank Of Georgia",
    branch: "-",
    accountNo: "-",
    iban: "-",
  },
  {
    bank: "TBC Bank",
    branch: "-",
    accountNo: "-",
    iban: "-",
  },
] as const;

const QUICK_LINKS = [
  {
    href: "/catalogs",
    title: "Kataloglar",
    icon: BookMarked,
    permissionKey: "catalogs",
  },
  {
    href: "/customers",
    title: "Cari Seçimi",
    icon: UserRound,
    permissionKey: "customers",
  },
  {
    href: "/returns",
    title: "İade / Arıza",
    icon: LifeBuoy,
    permissionKey: "returns",
  },
  {
    href: "/collections",
    title: "Tahsilat",
    icon: CreditCard,
    permissionKey: "collections",
  },
] as const;

const SUPPORT_LINKS = [
  {
    href: "/customers",
    title: "Müşteri Seç",
    permissionKey: "customers",
  },
  {
    href: "/cart",
    title: "Sepete Git",
    permissionKey: "cart",
  },
  {
    href: "/orders",
    title: "Siparişler",
    permissionKey: "orders",
  },
] as const;

const panelClass =
  "border-[var(--brand-border)] bg-[linear-gradient(180deg,var(--surface)_0%,var(--surface-soft)_100%)] shadow-[0_18px_34px_-28px_rgba(33,52,22,0.28)]";

const tileClass =
  "rounded-[24px] border border-[var(--brand-border)] bg-[linear-gradient(135deg,var(--surface)_0%,color-mix(in_oklab,var(--surface-soft)_88%,transparent)_100%)] p-5 shadow-[0_14px_28px_-24px_rgba(0,0,0,0.18)]";

function InfoTile({
  icon: Icon,
  label,
  value,
  sub,
}: {
  icon: LucideIcon;
  label: string;
  value: string;
  sub?: string;
}) {
  return (
    <div className={tileClass}>
      <span className="inline-flex h-12 w-12 items-center justify-center rounded-[16px] border border-[var(--brand-border)] bg-[var(--brand-primary-soft)] text-[var(--brand-primary)]">
        <Icon className="h-6 w-6" />
      </span>
      <p className="mt-5 text-sm font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">{label}</p>
      <p className="mt-2 line-clamp-2 text-2xl font-black leading-tight text-[var(--brand-primary-strong)]">{value}</p>
      {sub ? <p className="mt-2 line-clamp-1 text-sm font-semibold text-[var(--muted-foreground)]">{sub}</p> : null}
    </div>
  );
}

export function ExtraPage() {
  const { user, selectedCustomer } = useSession();
  const roleSlugs = useMemo(() => user?.roles.map((role) => role.slug) ?? [], [user?.roles]);
  const menuPermissions = useMemo(() => new Set(user?.menu_permissions ?? []), [user?.menu_permissions]);
  const canAccess = useCallback(
    (permissionKey: string) =>
      roleSlugs.includes("admin") || menuPermissions.size === 0 || menuPermissions.has(permissionKey),
    [menuPermissions, roleSlugs]
  );
  const quickLinks = useMemo(
    () => QUICK_LINKS.filter((item) => canAccess(item.permissionKey)),
    [canAccess]
  );
  const supportLinks = useMemo(
    () => SUPPORT_LINKS.filter((item) => canAccess(item.permissionKey)),
    [canAccess]
  );

  const filterOptionsQuery = useQuery({
    queryKey: ["extra", "brands"],
    queryFn: () => getProductFilterOptions({ scope: "brands" }),
    enabled: Boolean(user),
    staleTime: 300_000,
  });

  const brandPreview = useMemo(
    () => (filterOptionsQuery.data?.brands ?? []).slice(0, 18),
    [filterOptionsQuery.data?.brands]
  );
  const brandCount = filterOptionsQuery.data?.brands.length ?? 0;

  return (
    <div className="space-y-4">
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {quickLinks.map((item) => {
          const Icon = item.icon;

          return (
            <Link
              key={item.href}
              href={item.href}
              className="group flex min-h-[154px] flex-col justify-between rounded-[26px] border border-[var(--brand-border)] bg-[linear-gradient(135deg,var(--surface)_0%,var(--surface-soft)_100%)] p-5 shadow-[0_18px_34px_-28px_rgba(33,52,22,0.3)] transition hover:border-[var(--brand-primary)]/70 hover:-translate-y-0.5"
            >
              <span className="inline-flex h-14 w-14 items-center justify-center rounded-[18px] border border-[var(--brand-border)] bg-[var(--brand-primary-soft)] text-[var(--brand-primary)]">
                <Icon className="h-7 w-7" />
              </span>
              <span className="mt-5 flex items-center justify-between gap-3 text-2xl font-black tracking-tight text-[var(--brand-primary-strong)]">
                {item.title}
                <ArrowRight className="h-5 w-5 shrink-0 text-[var(--brand-primary)] transition group-hover:translate-x-1" />
              </span>
            </Link>
          );
        })}
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <InfoTile icon={UserRound} label="Kullanıcı" value={user?.name ?? "-"} sub={user?.email ?? undefined} />
        <InfoTile
          icon={Building2}
          label="Cari"
          value={selectedCustomer?.code ?? "Seçili değil"}
          sub={selectedCustomer?.title ?? undefined}
        />
        <InfoTile icon={Building2} label="Bayi" value={user?.dealer?.name ?? "Tanımlı değil"} sub={user?.dealer?.code ?? undefined} />
        <InfoTile icon={BookMarked} label="Marka" value={`${brandCount}`} sub="Katalog" />
      </div>

      <div className="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
        <Card className={panelClass}>
          <CardHeader>
            <CardTitle className="flex items-center gap-3 text-2xl font-black text-[var(--brand-primary-strong)]">
              <BookMarked className="h-6 w-6 text-[var(--brand-primary)]" />
              Markalar
            </CardTitle>
          </CardHeader>
          <CardContent>
            {filterOptionsQuery.isLoading ? (
              <div className="grid gap-3 md:grid-cols-3">
                {Array.from({ length: 12 }).map((_, index) => (
                  <Skeleton key={`brand-skeleton-${index}`} className="h-16 w-full rounded-[18px]" />
                ))}
              </div>
            ) : (
              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {brandPreview.map((brand) => (
                  <div key={brand.id} className="rounded-[18px] border border-[var(--brand-border)] bg-[var(--surface)] px-4 py-5 text-lg font-black text-[var(--brand-primary-strong)]">
                    {brand.name}
                  </div>
                ))}
                {filterOptionsQuery.data && filterOptionsQuery.data.brands.length > brandPreview.length ? (
                  <div className="rounded-[18px] border border-[var(--brand-border)] bg-[var(--brand-primary-soft)] px-4 py-5 text-lg font-black text-[var(--brand-primary)]">
                    +{filterOptionsQuery.data.brands.length - brandPreview.length} marka daha
                  </div>
                ) : null}
                {!filterOptionsQuery.data?.brands.length ? (
                  <div className="rounded-[18px] border border-dashed border-[var(--brand-border)] bg-[var(--surface)] px-4 py-8 text-center text-xl font-black text-[var(--brand-primary-strong)] sm:col-span-2 lg:col-span-3">
                    Marka yok
                  </div>
                ) : null}
              </div>
            )}
          </CardContent>
        </Card>

        <Card className={panelClass}>
          <CardHeader>
            <CardTitle className="flex items-center gap-3 text-2xl font-black text-[var(--brand-primary-strong)]">
              <CreditCard className="h-6 w-6 text-[var(--brand-primary)]" />
              Banka
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {BANK_ACCOUNTS.map((account) => (
              <div
                key={account.bank}
                className="rounded-[20px] border border-[var(--brand-border)] bg-[var(--surface)] p-5"
              >
                <p className="text-xl font-black text-[var(--brand-primary-strong)]">{account.bank}</p>
                <p className="mt-3 break-all text-base font-bold text-[var(--muted-foreground)]">{account.iban}</p>
              </div>
            ))}
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <Card className={panelClass}>
          <CardContent className="p-5">
            <Phone className="h-7 w-7 text-[var(--brand-primary)]" />
            <p className="mt-5 text-sm font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">Telefon</p>
            <p className="mt-2 text-xl font-black text-[var(--brand-primary-strong)]">{user?.dealer?.phone ?? user?.phone ?? "-"}</p>
          </CardContent>
        </Card>
        <Card className={panelClass}>
          <CardContent className="p-5">
            <Mail className="h-7 w-7 text-[var(--brand-primary)]" />
            <p className="mt-5 text-sm font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">E-posta</p>
            <p className="mt-2 truncate text-xl font-black text-[var(--brand-primary-strong)]">{user?.dealer?.email ?? user?.email ?? "-"}</p>
            <Button asChild className="mt-5 h-12 w-full rounded-2xl text-base font-black" variant="outline">
              <a href={`mailto:${user?.dealer?.email ?? user?.email ?? "info@powersa.test"}`}>Mail Aç</a>
            </Button>
          </CardContent>
        </Card>
        <Card className={panelClass}>
          <CardContent className="p-5">
            <MapPin className="h-7 w-7 text-[var(--brand-primary)]" />
            <p className="mt-5 text-sm font-black uppercase tracking-[0.12em] text-[var(--muted-foreground)]">Adres</p>
            <p className="mt-2 line-clamp-3 text-lg font-black leading-snug text-[var(--brand-primary-strong)]">{user?.dealer?.address ?? "-"}</p>
          </CardContent>
        </Card>
      </div>

      <Card className={panelClass}>
        <CardHeader>
          <CardTitle className="flex items-center gap-3 text-2xl font-black text-[var(--brand-primary-strong)]">
            <LifeBuoy className="h-6 w-6 text-[var(--brand-primary)]" />
            Destek
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid gap-3 md:grid-cols-3">
            {supportLinks.map((item) => (
              <Link key={item.href} href={item.href} className="rounded-[20px] border border-[var(--brand-border)] bg-[var(--surface)] p-5 text-xl font-black text-[var(--brand-primary-strong)] transition hover:border-[var(--brand-primary)]">
                {item.title}
              </Link>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
