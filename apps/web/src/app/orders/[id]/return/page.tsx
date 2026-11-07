import type { Metadata } from "next";
import { ReturnWizard } from "@/components/fulfilment/ReturnWizard";

export const metadata: Metadata = { title: "Return an item", robots: { index: false } };

export default async function ReturnPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <ReturnWizard id={decodeURIComponent(id)} />;
}
