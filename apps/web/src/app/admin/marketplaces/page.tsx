import type { Metadata } from "next";
import { MarketplacesAdmin } from "@/components/account/MarketplacesAdmin";

export const metadata: Metadata = { title: "Marketplaces (admin)", robots: { index: false, follow: false } };

export default function MarketplacesPage() {
  return <MarketplacesAdmin />;
}
