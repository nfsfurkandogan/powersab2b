import { cn } from "@/lib/utils";

type NfsSoftCreditProps = {
  className?: string;
  dark?: boolean;
};

export function NfsSoftCredit({ className, dark = false }: NfsSoftCreditProps) {
  return (
    <div
      className={cn(
        "text-center text-xs font-semibold",
        dark ? "text-white/60" : "text-[var(--muted-foreground)]",
        className
      )}
    >
      Designed by{" "}
      <a
        href="https://nfssoft.com"
        target="_blank"
        rel="noreferrer"
        className={cn(
          "font-black underline-offset-4 transition hover:underline",
          dark
            ? "text-white/85 hover:text-white"
            : "text-[var(--brand-primary-strong)] hover:text-[var(--brand-primary)]"
        )}
      >
        NfsSoft
      </a>
    </div>
  );
}
