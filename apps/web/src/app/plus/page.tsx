import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { PlusLanding } from "@/components/money/PlusLanding";
import { getFeatures, isOn } from "@/lib/features";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("meta.plus"), description: t("meta.plusDesc") };
}

export default async function PlusPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  if (!isOn(await getFeatures(), "membership")) notFound();
  const sp = await searchParams;
  const renew = Array.isArray(sp.renew) ? sp.renew[0] : sp.renew;
  return <PlusLanding renew={renew} />;
}
