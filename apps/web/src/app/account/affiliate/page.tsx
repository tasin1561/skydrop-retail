import type { Metadata } from "next";
import { AffiliateDashboard } from "@/components/growth/AffiliateDashboard";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("meta.affiliateDash"), robots: { index: false } };
}

export default function AffiliateDashboardPage() {
  return <AffiliateDashboard />;
}
