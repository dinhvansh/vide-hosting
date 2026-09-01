"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useI18n } from "@/lib/i18n";

export function LanguageSwitcher({ compact = false }: { compact?: boolean }) {
  const { locale } = useI18n();
  const pathname = usePathname();
  const search = useSearchParams();
  const router = useRouter();
  const switchTo = (nextLocale: "vi" | "en") => {
    if (nextLocale === locale) return;
    document.cookie = `vive_locale=${nextLocale}; Path=/; Max-Age=31536000; SameSite=Lax`;
    document.documentElement.lang = nextLocale;
    const nextPath = pathname.replace(/^\/(vi|en)(?=\/|$)/, `/${nextLocale}`);
    const hash = window.location.hash;
    router.push(
      `${nextPath}${search.size ? `?${search.toString()}` : ""}${hash}`,
    );
  };
  return <div className={`language-switcher${compact ? " compact" : ""}`} aria-label={locale === "vi" ? "Chọn ngôn ngữ" : "Choose language"}>
    <button type="button" className={locale === "vi" ? "active" : ""} aria-pressed={locale === "vi"} onClick={() => switchTo("vi")}>VI</button>
    <button type="button" className={locale === "en" ? "active" : ""} aria-pressed={locale === "en"} onClick={() => switchTo("en")}>EN</button>
  </div>;
}
