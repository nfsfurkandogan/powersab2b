"use client";

import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

const LOGO_SYNC_DATE_FORMATTER = new Intl.DateTimeFormat("tr-TR", {
  dateStyle: "medium",
  timeStyle: "short",
});

export type LogoSyncFields = {
  logo_sync_status?: string | null;
  logo_sync_error?: string | null;
  logo_external_ref?: string | null;
  logo_last_synced_at?: string | null;
};

function formatLogoSyncDate(value: string | null | undefined): string | null {
  if (!value) {
    return null;
  }

  const parsed = new Date(value);

  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return LOGO_SYNC_DATE_FORMATTER.format(parsed);
}

export function getLogoSyncMeta(status: string | null | undefined): { label: string; className: string } {
  const normalized = (status ?? "").trim().toLowerCase();

  if (normalized === "synced") {
    return {
      label: "Logo işlendi",
      className: "border-emerald-200 bg-emerald-50 text-emerald-700",
    };
  }

  if (normalized === "failed" || normalized === "publish_failed") {
    return {
      label: "Logo hata",
      className: "border-rose-200 bg-rose-50 text-rose-700",
    };
  }

  if (normalized === "processing") {
    return {
      label: "Logo işleniyor",
      className: "border-blue-200 bg-blue-50 text-blue-700",
    };
  }

  if (normalized === "queued" || normalized === "pending") {
    return {
      label: "Logo bekliyor",
      className: "border-amber-200 bg-amber-50 text-amber-700",
    };
  }

  if (normalized.length > 0) {
    return {
      label: `Logo: ${status}`,
      className: "border-[var(--brand-border)] bg-[var(--surface-soft)] text-[var(--muted-foreground)]",
    };
  }

  return {
    label: "Logo aktarım yok",
    className: "border-[var(--brand-border)] bg-[var(--surface-soft)] text-[var(--muted-foreground)]",
  };
}

export function LogoSyncBadge({
  status,
  className,
}: {
  status: string | null | undefined;
  className?: string;
}) {
  const meta = getLogoSyncMeta(status);

  return (
    <Badge variant="outline" className={cn("font-semibold", meta.className, className)}>
      {meta.label}
    </Badge>
  );
}

export function LogoSyncInline({
  status,
  error,
  externalRef,
  lastSyncedAt,
  className,
  label = "Logo Aktarım",
  showLabel = true,
}: {
  status: string | null | undefined;
  error?: string | null;
  externalRef?: string | null;
  lastSyncedAt?: string | null;
  className?: string;
  label?: string;
  showLabel?: boolean;
}) {
  const formattedDate = formatLogoSyncDate(lastSyncedAt);

  return (
    <div className={cn("space-y-1", className)}>
      {showLabel ? (
        <p className="text-xs font-bold uppercase tracking-[0.08em] text-[var(--muted-foreground)]">
          {label}
        </p>
      ) : null}
      <div className="flex flex-wrap items-center gap-2">
        <LogoSyncBadge status={status} />
        {externalRef ? (
          <span className="rounded-full border border-[var(--brand-border)] px-2.5 py-0.5 text-xs font-semibold text-[var(--muted-foreground)]">
            {externalRef}
          </span>
        ) : null}
      </div>
      {formattedDate ? (
        <p className="text-xs font-medium text-[var(--muted-foreground)]">Son aktarım: {formattedDate}</p>
      ) : null}
      {error?.trim() ? <p className="text-xs font-semibold text-rose-700">{error}</p> : null}
    </div>
  );
}
