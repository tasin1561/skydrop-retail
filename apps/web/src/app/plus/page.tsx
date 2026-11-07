import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { PlusLanding } from "@/components/money/PlusLanding";
import { getFeatures, isOn } from "@/lib/features";

export const metadata: Metadata = {
  title: "Skydrop Plus",
  description: "Free shipping from India on every order, extra discounts, double points and early flash-sale access with Skydrop Plus.",
};

export default async function PlusPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  if (!isOn(await getFeatures(), "membership")) notFound();
  const sp = await searchParams;
  const renew = Array.isArray(sp.renew) ? sp.renew[0] : sp.renew;
  return <PlusLanding renew={renew} />;
}
