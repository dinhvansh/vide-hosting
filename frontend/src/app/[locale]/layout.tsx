import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { I18nProvider } from "@/lib/i18n";
import { locales, Locale } from "@/lib/i18n-config";

export function generateStaticParams() { return locales.map((locale) => ({ locale })); }

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }): Promise<Metadata> {
  const { locale } = await params;
  if (!locales.includes(locale as Locale)) notFound();
  const english = locale === "en";
  return {
    title: english ? "Vive Host — Deploy AI applications" : "Vive Host — Deploy ứng dụng AI",
    description: english ? "Deploy applications from GitHub with automatic HTTPS, databases, logs, and safe resource isolation." : "Deploy ứng dụng từ GitHub với HTTPS, database, logs và giới hạn tài nguyên an toàn.",
    alternates: { canonical: `/${locale}`, languages: { vi: "/vi", en: "/en", "x-default": "/vi" } },
    openGraph: { locale: english ? "en_US" : "vi_VN", alternateLocale: [english ? "vi_VN" : "en_US"] },
  };
}

export default async function LocaleLayout({ children, params }: { children: React.ReactNode; params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!locales.includes(locale as Locale)) notFound();
  return <I18nProvider locale={locale as Locale}>{children}</I18nProvider>;
}
