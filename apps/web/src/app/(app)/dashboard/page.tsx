"use client";

import dynamic from "next/dynamic";
import { Loader2 } from "lucide-react";

import { useAuth } from "@/hooks/use-auth";

const ControlPanelPage = dynamic(() =>
  import("@/components/dashboard/control-panel-page").then((module) => module.ControlPanelPage),
  { loading: () => <DashboardLoading /> }
);

const CustomerDashboardPage = dynamic(() =>
  import("@/components/dashboard/customer-dashboard-page").then((module) => module.CustomerDashboardPage),
  { loading: () => <DashboardLoading /> }
);

const PointBranchDashboardPage = dynamic(() =>
  import("@/components/dashboard/point-branch-dashboard-page").then((module) => module.PointBranchDashboardPage),
  { loading: () => <DashboardLoading /> }
);

const PlasiyerHomePage = dynamic(() =>
  import("@/components/plasiyer/plasiyer-home-page").then((module) => module.PlasiyerHomePage),
  { loading: () => <DashboardLoading /> }
);

const ADMIN_DASHBOARD_USERS = new Set(["satinalma", "satis", "muhasebe", "mudur.erzurum"]);

function DashboardLoading({ label = "Dashboard hazırlanıyor..." }: { label?: string }) {
  return (
    <div className="flex min-h-[40vh] items-center justify-center text-[var(--muted-foreground)]">
      <Loader2 className="mr-2 h-5 w-5 animate-spin" />
      {label}
    </div>
  );
}

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

function hasAdminDashboardUser(username?: string) {
  return ADMIN_DASHBOARD_USERS.has(username?.trim().toLowerCase() ?? "");
}

export default function DashboardRoutePage() {
  const { loading, user } = useAuth();
  const roleSlugs = Array.isArray(user?.roles) ? user.roles.map((role) => role.slug) : [];
  const isAdminDashboardUser = hasAdminDashboardUser(user?.username);

  if (loading) {
    return <DashboardLoading />;
  }

  if (isAdminDashboardUser || roleSlugs.includes("admin")) {
    return <ControlPanelPage />;
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

  return <DashboardLoading label="Yönlendiriliyor..." />;
}
