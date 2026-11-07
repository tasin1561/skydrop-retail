import type { Metadata } from "next";
import { OrderDetail } from "@/components/account/OrderDetail";
import { getT } from "@/i18n/server";

export async function generateMetadata({ params }: { params: Promise<{ id: string }> }): Promise<Metadata> {
  const { id } = await params;
  const short = decodeURIComponent(id).replace(/^ord[_-]?/i, "").toUpperCase().slice(-8);
  const { t } = await getT();
  return { title: t("meta.order", { id: short }), robots: { index: false } };
}

export default async function OrderPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <OrderDetail id={decodeURIComponent(id)} />;
}
