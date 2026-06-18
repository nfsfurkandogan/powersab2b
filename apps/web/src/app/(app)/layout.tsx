import { cookies } from "next/headers";
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

export default async function AuthenticatedLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const cookieStore = await cookies();
  const hasSession = hasBackendSessionCookie(cookieStore);

  if (!hasSession) {
    redirect("/login?v=20260605-login-fast");
  }

  return (
    <RequireAuth>
      <AppShell>{children}</AppShell>
    </RequireAuth>
  );
}
