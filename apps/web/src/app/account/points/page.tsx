import type { Metadata } from "next";
import { PointsView } from "@/components/account/PointsView";

export const metadata: Metadata = { title: "Skydrop Points", robots: { index: false } };

export default function PointsPage() {
  return <PointsView />;
}
