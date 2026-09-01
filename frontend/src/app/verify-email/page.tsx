import { VerifyEmail } from "@/components/auth-recovery";

export default async function VerifyEmailPage({ searchParams }: { searchParams: Promise<{ url?: string | string[] }> }) {
  const url = (await searchParams).url;

  return <VerifyEmail verificationUrl={typeof url === "string" ? url : ""} />;
}
