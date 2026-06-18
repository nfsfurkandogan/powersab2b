import { NextResponse, type NextRequest } from "next/server";

const LOGIN_VERSION = "20260605-login-fast";

function hasBackendSessionCookie(request: NextRequest): boolean {
  return request.cookies.getAll().some(({ name }) => {
    if (name === "laravel_session") {
      return true;
    }

    return name.endsWith("-session");
  });
}

export function middleware(request: NextRequest) {
  const { nextUrl } = request;

  if (hasBackendSessionCookie(request)) {
    return NextResponse.next();
  }

  const loginUrl = new URL("/login", nextUrl.origin);
  loginUrl.searchParams.set("v", LOGIN_VERSION);

  const nextPath = `${nextUrl.pathname}${nextUrl.search}`;
  if (nextPath !== "/") {
    loginUrl.searchParams.set("next", nextPath);
  }

  return NextResponse.redirect(loginUrl);
}

export const config = {
  matcher: [
    "/dashboard/:path*",
    "/search/:path*",
    "/moderator/:path*",
    "/catalogs/:path*",
    "/cart/:path*",
    "/customers/:path*",
    "/customer-users/:path*",
    "/ledger/:path*",
    "/notes/:path*",
    "/collections/:path*",
    "/reports/:path*",
    "/extra/:path*",
    "/mal-kabul/:path*",
    "/satinalma/:path*",
    "/new-customer-card/:path*",
    "/orders/:path*",
    "/warehouse/:path*",
    "/returns/:path*",
    "/pos/:path*",
    "/virtual-pos/:path*",
    "/irsaliye-dokum/:path*",
    "/plasiyer/:path*",
  ],
};
