import { ResetPassword } from "@/components/auth-recovery";

export default async function ResetPasswordPage({ searchParams }: { searchParams: Promise<{ token?: string | string[]; email?: string | string[] }> }) {
  const params = await searchParams;

  return <ResetPassword token={typeof params.token === "string" ? params.token : ""} email={typeof params.email === "string" ? params.email : ""} />;
}
