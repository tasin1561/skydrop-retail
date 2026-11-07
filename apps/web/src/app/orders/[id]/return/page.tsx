import type { Metadata } from "next";
import { ReturnWizard } from "@/components/fulfilment/ReturnWizard";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  return { title: (await getT()).t("meta.returnItem"), robots: { index: false } };
}

export default async function ReturnPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <ReturnWizard id={decodeURIComponent(id)} />;
}
