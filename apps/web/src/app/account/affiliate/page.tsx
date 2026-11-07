import type { Metadata } from "next";
import { AffiliateDashboard } from "@/components/growth/AffiliateDashboard";

export const metadata: Metadata = { title: "Affiliate dashboard", robots: { index: false } };

export default function AffiliateDashboardPage() {
  return <AffiliateDashboard />;
}
