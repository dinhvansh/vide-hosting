import { ApplicationDetail } from "@/components/application-detail";

export default async function ApplicationPage({ params }: PageProps<"/dashboard/apps/[id]">) {
  const { id } = await params;
  return <ApplicationDetail appId={id} />;
}
