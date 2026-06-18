import { RequireAuth } from "@/components/auth/require-auth";

export default function PrintLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return <RequireAuth>{children}</RequireAuth>;
}
