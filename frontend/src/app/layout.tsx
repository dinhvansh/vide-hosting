import type { Metadata } from "next";
import { Inter, JetBrains_Mono } from "next/font/google";
import { headers } from "next/headers";
import "./globals.css";

const inter = Inter({ subsets: ["latin", "vietnamese"], variable: "--font-sans" });
const mono = JetBrains_Mono({ subsets: ["latin", "vietnamese"], variable: "--font-mono" });
export const metadata: Metadata = { metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3001"), title: "Vive Host — Deploy AI applications", description: "Simple deployment infrastructure for modern applications." };
export default async function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) { const locale = (await headers()).get("x-vive-locale") === "en" ? "en" : "vi"; return <html lang={locale} data-scroll-behavior="smooth"><body className={`${inter.variable} ${mono.variable}`}>{children}</body></html>; }
