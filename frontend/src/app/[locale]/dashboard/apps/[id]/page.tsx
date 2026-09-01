import { ApplicationDetail } from "@/components/application-detail";
export default async function ApplicationPage({ params }: { params: Promise<{ id: string }> }) { return <ApplicationDetail appId={(await params).id} />; }
