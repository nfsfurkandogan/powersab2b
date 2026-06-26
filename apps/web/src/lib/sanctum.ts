function readCookie(name: string): string | null {
  if (typeof document === "undefined") {
    return null;
  }

  const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const match = document.cookie.match(new RegExp(`(?:^|; )${escapedName}=([^;]*)`));

  return match ? decodeURIComponent(match[1]) : null;
}

export function getSanctumHeaders(method: string): Record<string, string> {
  void method;
  const csrfToken = readCookie("XSRF-TOKEN");

  return {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
    ...(csrfToken ? { "X-XSRF-TOKEN": csrfToken } : {}),
  };
}

export function getSanctumCsrfHeaders(): Record<string, string> {
  return {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
}
