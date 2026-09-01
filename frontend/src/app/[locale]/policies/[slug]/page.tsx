import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import {
  LegalFooter,
  LegalHeader,
  PolicyContact,
} from "@/components/legal-shell";
import { locales, type Locale } from "@/lib/i18n-config";
import {
  isPolicySlug,
  policies,
  policySlugs,
  policyUi,
} from "@/lib/legal-policies";

export function generateStaticParams() {
  return policySlugs.map((slug) => ({ slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ locale: string; slug: string }>;
}): Promise<Metadata> {
  const { locale, slug } = await params;
  if (!locales.includes(locale as Locale) || !isPolicySlug(slug)) notFound();
  const policy = policies[locale as Locale][slug];
  return {
    title: `${policy.title} — Vive Host`,
    description: policy.summary,
    alternates: {
      canonical: `/${locale}/policies/${slug}`,
      languages: {
        vi: `/vi/policies/${slug}`,
        en: `/en/policies/${slug}`,
        "x-default": `/vi/policies/${slug}`,
      },
    },
  };
}

export default async function PolicyPage({
  params,
}: {
  params: Promise<{ locale: string; slug: string }>;
}) {
  const { locale, slug } = await params;
  if (!locales.includes(locale as Locale) || !isPolicySlug(slug)) notFound();
  const language = locale as Locale;
  const ui = policyUi[language];
  const policy = policies[language][slug];

  return (
    <div className="legal-page">
      <LegalHeader locale={language} label={ui.allPolicies} />
      <main className="policy-layout">
        <aside className="policy-nav">
          <small>{ui.allPolicies}</small>
          <nav>
            {policySlugs.map((item) => (
              <Link
                key={item}
                className={item === slug ? "active" : ""}
                href={`/${language}/policies/${item}`}
              >
                {policies[language][item].shortTitle}
              </Link>
            ))}
          </nav>
        </aside>
        <article className="policy-document">
          <header>
            <span>{ui.eyebrow}</span>
            <h1>{policy.title}</h1>
            <p>{policy.summary}</p>
            <small>
              {ui.updated}: {policy.updated}
            </small>
          </header>
          <div className="policy-copy">
            {policy.sections.map((section) => (
              <section key={section.title}>
                <h2>{section.title}</h2>
                {section.paragraphs?.map((paragraph) => (
                  <p key={paragraph}>{paragraph}</p>
                ))}
                {section.bullets && (
                  <ul>
                    {section.bullets.map((bullet) => (
                      <li key={bullet}>{bullet}</li>
                    ))}
                  </ul>
                )}
              </section>
            ))}
          </div>
          <PolicyContact locale={language} />
        </article>
      </main>
      <LegalFooter locale={language} />
    </div>
  );
}
