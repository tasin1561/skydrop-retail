import type { Metadata } from "next";
import { MoneySuccess } from "@/components/money/MoneySuccess";

export const metadata: Metadata = { title: "Gift card", robots: { index: false } };

const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);

export default async function GiftCardSuccessPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const sp = await searchParams;
  return <MoneySuccess kind="gift" status={one(sp.status)} orderId={one(sp.order)} />;
}
