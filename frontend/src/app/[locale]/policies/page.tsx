import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import {
  LegalFooter,
  LegalHeader,
  PolicyContact,
} from "@/components/legal-shell";
import { locales, type Locale } from "@/lib/i18n-config";
import { policies, policySlugs, policyUi } from "@/lib/legal-policies";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string }>;
}): Promise<Metadata> {
  const { locale } = await params;
  if (!locales.includes(locale as Locale)) notFound();
  const language = locale as Locale;
  const ui = policyUi[language];
  return {
    title:
      language === "vi" ? "Chính sách — Vive Host" : "Policies — Vive Host",
    description: ui.description,
    alternates: {
      canonical: `/${language}/policies`,
      languages: {
        vi: "/vi/policies",
        en: "/en/policies",
        "x-default": "/vi/policies",
      },
    },
  };
}

export default async function PoliciesPage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  if (!locales.includes(locale as Locale)) notFound();
  const language = locale as Locale;
  const ui = policyUi[language];

  return (
    <div className="legal-page">
      <LegalHeader locale={language} label={ui.allPolicies} />
      <main className="legal-home">
        <div className="legal-hero">
          <span>{ui.eyebrow}</span>
          <h1>{ui.title}</h1>
          <p>{ui.description}</p>
        </div>
        <section className="policy-grid" aria-label={ui.choose}>
          {policySlugs.map((slug, index) => {
            const policy = policies[language][slug];
            return (
              <Link key={slug} href={`/${language}/policies/${slug}`}>
                <span>0{index + 1}</span>
                <h2>{policy.shortTitle}</h2>
                <p>{policy.summary}</p>
                <small>
                  {ui.updated} · {policy.updated}
                </small>
                <b aria-hidden="true">↗</b>
              </Link>
            );
          })}
        </section>
        <PolicyContact locale={language} />
      </main>
      <LegalFooter locale={language} />
    </div>
  );
}
