import type { Metadata } from "next";
import { CheckoutView } from "@/components/checkout/CheckoutView";
import "@/components/checkout/checkout.css";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("checkout.meta"), description: t("checkout.metaDesc"), robots: { index: false } };
}

export default function CheckoutPage() {
  return <CheckoutView />;
}
