import type { Metadata } from "next";
import { CartView } from "@/components/checkout/CartView";
import "@/components/checkout/checkout.css";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("nav.cart"), description: t("cartp.metaDesc"), robots: { index: false } };
}

export default function CartPage() {
  return <CartView />;
}
