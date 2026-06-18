"use client";

import { Loader2 } from "lucide-react";

import { ControlPanelPage } from "@/components/dashboard/control-panel-page";
import { CustomerDashboardPage } from "@/components/dashboard/customer-dashboard-page";
import { PointBranchDashboardPage } from "@/components/dashboard/point-branch-dashboard-page";
import { PlasiyerHomePage } from "@/components/plasiyer/plasiyer-home-page";
import { useAuth } from "@/hooks/use-auth";

function hasDealerAdminDashboardRole(roleSlugs: string[]) {
  return (
    roleSlugs.includes("dealer_admin") &&
    !roleSlugs.includes("admin") &&
    !roleSlugs.includes("salesperson")
  );
}

function hasPointBranchDashboardRole(roleSlugs: string[]) {
  return (
    (roleSlugs.includes("point") || roleSlugs.includes("cashier")) &&
    !roleSlugs.includes("admin") &&
    !roleSlugs.includes("dealer_admin") &&
    !roleSlugs.includes("salesperson") &&
    !roleSlugs.includes("warehouse")
  );
}

export default function DashboardRoutePage() {
  const { loading, user } = useAuth();
  const roleSlugs = user?.roles.map((role) => role.slug) ?? [];

  if (loading) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center text-[var(--muted-foreground)]">
        <Loader2 className="mr-2 h-5 w-5 animate-spin" />
        Dashboard hazırlanıyor...
      </div>
    );
  }

  if (hasDealerAdminDashboardRole(roleSlugs) || roleSlugs.includes("customer")) {
    return <CustomerDashboardPage />;
  }

  if (roleSlugs.includes("salesperson")) {
    return <PlasiyerHomePage />;
  }

  if (hasPointBranchDashboardRole(roleSlugs)) {
    return <PointBranchDashboardPage />;
  }

  if (roleSlugs.includes("admin")) {
    return <ControlPanelPage />;
  }

  return (
    <div className="flex min-h-[40vh] items-center justify-center text-[var(--muted-foreground)]">
      <Loader2 className="mr-2 h-5 w-5 animate-spin" />
      Yönlendiriliyor...
    </div>
  );
}
