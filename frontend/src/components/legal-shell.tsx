import Link from "next/link";
import { LanguageSwitcher } from "./language-switcher";
import type { Locale } from "@/lib/i18n-config";
import { policyUi } from "@/lib/legal-policies";

export function LegalHeader({
  locale,
  label,
}: {
  locale: Locale;
  label: string;
}) {
  return (
    <header className="legal-header">
      <Link className="brand" href={`/${locale}`}>
        <span>V</span> Vive Host
      </Link>
      <Link href={`/${locale}/policies`}>{label}</Link>
      <LanguageSwitcher compact />
    </header>
  );
}

export function PolicyContact({ locale }: { locale: Locale }) {
  const ui = policyUi[locale];
  return (
    <aside className="policy-contact">
      <div>
        <small>{ui.contact}</small>
        <h2>{ui.contactDescription}</h2>
      </div>
      <a href="mailto:support@vive.host">
        support@vive.host <span>↗</span>
      </a>
    </aside>
  );
}

export function LegalFooter({ locale }: { locale: Locale }) {
  return (
    <footer className="legal-footer">
      <span>© 2026 Vive Host</span>
      <Link href={`/${locale}`}>{policyUi[locale].backHome}</Link>
    </footer>
  );
}
