"use client";

import { useSession } from "@/components/auth/session-provider";

export function useAuth() {
  const session = useSession();

  return {
    ...session,
    loading: session.status === "loading",
    isAuthenticated: session.status === "authenticated",
    isGuest: session.status === "guest",
  };
}
