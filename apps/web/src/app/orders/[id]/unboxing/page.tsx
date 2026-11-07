import type { Metadata } from "next";
import { UnboxingReview } from "@/components/fulfilment/UnboxingReview";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  return { title: (await getT()).t("meta.unboxingCheck"), robots: { index: false } };
}

export default async function UnboxingPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <UnboxingReview id={decodeURIComponent(id)} />;
}
