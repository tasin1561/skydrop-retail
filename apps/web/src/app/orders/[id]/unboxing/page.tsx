import type { Metadata } from "next";
import { UnboxingReview } from "@/components/fulfilment/UnboxingReview";

export const metadata: Metadata = { title: "Unboxing check", robots: { index: false } };

export default async function UnboxingPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <UnboxingReview id={decodeURIComponent(id)} />;
}
