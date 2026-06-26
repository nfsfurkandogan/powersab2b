import { cookies, headers } from "next/headers";
import { redirect } from "next/navigation";

import { RequireAuth } from "@/components/auth/require-auth";
import { AppShell } from "@/components/layout/app-shell";

export const dynamic = "force-dynamic";

function hasBackendSessionCookie(cookieStore: Awaited<ReturnType<typeof cookies>>) {
  return cookieStore.getAll().some(({ name }) => {
    if (name === "laravel_session") {
      return true;
    }

    return name.endsWith("-session");
  });
}

function loginRedirectUrl(nextPath?: string) {
  const search = new URLSearchParams();
  search.set("v", "20260605-login-fast");

  if (nextPath && nextPath !== "/") {
    search.set("next", nextPath);
  }

  return `/login?${search.toString()}`;
}

export default async function AuthenticatedLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const cookieStore = await cookies();
  const headerStore = await headers();
  const hasSession = hasBackendSessionCookie(cookieStore);
  const nextPath = headerStore.get("x-invoke-path") ?? headerStore.get("x-matched-path") ?? undefined;

  if (!hasSession) {
    redirect(loginRedirectUrl(nextPath));
  }

  return (
    <RequireAuth>
      <AppShell>{children}</AppShell>
    </RequireAuth>
  );
}
