import type { Metadata } from "next";
import { OrderSuccess } from "@/components/checkout/OrderSuccess";
import { TrackPurchase } from "@/components/tracking/TrackPurchase";
import "@/components/checkout/checkout.css";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("success.meta"), robots: { index: false } };
}

export default async function SuccessPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const sp = await searchParams;
  const raw = sp.order;
  const orderId = (Array.isArray(raw) ? raw[0] : raw) ?? "";
  const st = Array.isArray(sp.status) ? sp.status[0] : sp.status;
  const payStatus = st === "failed" || st === "cancelled" ? st : st === "paid" ? "paid" : undefined;
  return (
    <>
      <TrackPurchase orderId={orderId} payStatus={payStatus} />
      <OrderSuccess orderId={orderId} payStatus={payStatus} />
    </>
  );
}
