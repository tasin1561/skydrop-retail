import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { GiftCardShop } from "@/components/money/GiftCardShop";
import { getFeatures, isOn } from "@/lib/features";

export const metadata: Metadata = {
  title: "Gift cards",
  description: "Send a Skydrop Retail gift card by SMS or email. They spend it on anything from amazon.in, Flipkart and BigBasket, delivered in Bangladesh.",
};

export default async function GiftCardsPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  if (!isOn(await getFeatures(), "gift_cards")) notFound();
  const sp = await searchParams;
  return <GiftCardShop redeem={sp.redeem === "1"} />;
}
