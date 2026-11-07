import type { Metadata } from "next";
import { PointsView } from "@/components/account/PointsView";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("meta.points"), robots: { index: false } };
}

export default function PointsPage() {
  return <PointsView />;
}
