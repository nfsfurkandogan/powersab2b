"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { Loader2 } from "lucide-react";

import { useAuth } from "@/hooks/use-auth";

export function GuestOnly({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, user } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (isAuthenticated) {
      const roleSlugs = Array.isArray(user?.roles) ? user.roles.map((role) => role.slug) : [];
      const hasRole = (slug: string) => roleSlugs.includes(slug);
      const isWarehouseOnly =
        hasRole("warehouse") && !hasRole("admin") && !hasRole("dealer_admin") && !hasRole("salesperson");
      const isSalesperson = hasRole("salesperson");
      const isDealerAdmin = hasRole("dealer_admin");
      const isModeratorOnly =
        hasRole("moderator") &&
        !hasRole("admin") &&
        !hasRole("dealer_admin") &&
        !hasRole("salesperson") &&
        !hasRole("point") &&
        !hasRole("cashier") &&
        !hasRole("warehouse");
      const isPointOnly =
        (hasRole("point") || hasRole("cashier")) &&
        !hasRole("admin") &&
        !hasRole("dealer_admin") &&
        !hasRole("salesperson") &&
        !hasRole("warehouse");

      if (isWarehouseOnly) {
        router.replace("/warehouse");
        return;
      }

      if (isSalesperson) {
        router.replace("/customers");
        return;
      }

      if (isModeratorOnly) {
        router.replace("/moderator/users");
        return;
      }

      if (isPointOnly) {
        router.replace("/pos");
        return;
      }

      if (isDealerAdmin) {
        router.replace("/dashboard");
        return;
      }

      router.replace("/dashboard");
    }
  }, [isAuthenticated, router, user]);

  if (isAuthenticated) {
    return (
      <div className="flex min-h-screen items-center justify-center text-[var(--muted-foreground)]">
        <Loader2 className="mr-2 h-5 w-5 animate-spin" />
        Yönlendiriliyor...
      </div>
    );
  }

  return <>{children}</>;
}
