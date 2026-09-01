import { NextRequest, NextResponse } from "next/server";
import { locales, Locale } from "@/lib/i18n-config";

function preferredLocale(request: NextRequest): Locale {
  const cookieLocale = request.cookies.get("vive_locale")?.value;
  if (locales.includes(cookieLocale as Locale)) return cookieLocale as Locale;
  return request.headers.get("accept-language")?.toLowerCase().startsWith("en") ? "en" : "vi";
}

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const locale = locales.find((item) => pathname === `/${item}` || pathname.startsWith(`/${item}/`));
  if (!locale) {
    const url = request.nextUrl.clone();
    const selected = preferredLocale(request);
    url.pathname = `/${selected}${pathname === "/" ? "" : pathname}`;
    const response = NextResponse.redirect(url);
    response.cookies.set("vive_locale", selected, { path: "/", maxAge: 31536000, sameSite: "lax" });
    return response;
  }
  const requestHeaders = new Headers(request.headers);
  requestHeaders.set("x-vive-locale", locale);
  const response = NextResponse.next({ request: { headers: requestHeaders } });
  response.cookies.set("vive_locale", locale, { path: "/", maxAge: 31536000, sameSite: "lax" });
  return response;
}

export const config = { matcher: ["/((?!api|_next/static|_next/image|favicon.ico|.*\\..*).*)"] };
