import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { WalletView } from "@/components/money/WalletView";
import { getFeatures, isOn } from "@/lib/features";

export const metadata: Metadata = { title: "Skydrop Wallet", robots: { index: false } };

export default async function WalletPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  if (!isOn(await getFeatures(), "wallet")) notFound();
  const sp = await searchParams;
  return <WalletView redeem={sp.redeem === "1"} />;
}
