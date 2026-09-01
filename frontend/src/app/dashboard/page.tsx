import { Dashboard } from "@/components/dashboard";

export default async function DashboardPage({ searchParams }: { searchParams: Promise<{ mode?: string | string[] }> }) {
  const mode = (await searchParams).mode;

  return <Dashboard initialRegister={mode === "register"} />;
}
