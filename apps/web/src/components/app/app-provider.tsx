"use client";

import { SessionProvider } from "@/components/auth/session-provider";
import { CartProvider } from "@/components/cart/cart-provider";
import { ClientErrorRecovery } from "@/components/app/client-error-recovery";
import { PwaServiceWorker } from "@/components/app/pwa-service-worker";

export function AppProvider({ children }: { children: React.ReactNode }) {
  return (
    <SessionProvider>
      <ClientErrorRecovery />
      <PwaServiceWorker />
      <CartProvider>{children}</CartProvider>
    </SessionProvider>
  );
}
