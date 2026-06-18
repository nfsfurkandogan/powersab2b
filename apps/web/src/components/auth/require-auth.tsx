"use client";

import { useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import { Loader2 } from "lucide-react";

import { useAuth } from "@/hooks/use-auth";

export function RequireAuth({ children }: { children: React.ReactNode }) {
  const { isGuest, loading } = useAuth();
  const pathname = usePathname();
  const router = useRouter();

  useEffect(() => {
    if (isGuest) {
      const search = new URLSearchParams();
      search.set("v", "20260605-login-fast");

      if (pathname) {
        search.set("next", pathname);
      }

      router.replace(`/login?${search.toString()}`);
    }
  }, [isGuest, pathname, router]);

  if (loading || isGuest) {
    return (
      <div className="flex min-h-screen items-center justify-center text-[var(--muted-foreground)]">
        <Loader2 className="mr-2 h-5 w-5 animate-spin" />
        Oturum kontrol ediliyor...
      </div>
    );
  }

  return <>{children}</>;
}
