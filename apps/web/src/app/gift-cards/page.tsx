import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { GiftCardShop } from "@/components/money/GiftCardShop";
import { getFeatures, isOn } from "@/lib/features";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("meta.giftCards"), description: t("meta.giftCardsDesc") };
}

export default async function GiftCardsPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  if (!isOn(await getFeatures(), "gift_cards")) notFound();
  const sp = await searchParams;
  return <GiftCardShop redeem={sp.redeem === "1"} />;
}
