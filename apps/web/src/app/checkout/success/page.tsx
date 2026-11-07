import type { Metadata } from "next";
import { OrderSuccess } from "@/components/checkout/OrderSuccess";
import "@/components/checkout/checkout.css";

export const metadata: Metadata = {
  title: "Order placed",
  robots: { index: false },
};

export default async function SuccessPage({ searchParams }: { searchParams: Promise<Record<string, string | string[] | undefined>> }) {
  const sp = await searchParams;
  const raw = sp.order;
  const orderId = (Array.isArray(raw) ? raw[0] : raw) ?? "";
  const st = Array.isArray(sp.status) ? sp.status[0] : sp.status;
  return <OrderSuccess orderId={orderId} payStatus={st === "failed" || st === "cancelled" ? st : st === "paid" ? "paid" : undefined} />;
}
