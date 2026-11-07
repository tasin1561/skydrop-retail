import type { Metadata } from "next";
import { OrderDetail } from "@/components/account/OrderDetail";

export async function generateMetadata({ params }: { params: Promise<{ id: string }> }): Promise<Metadata> {
  const { id } = await params;
  const short = decodeURIComponent(id).replace(/^ord[_-]?/i, "").toUpperCase().slice(-8);
  return { title: `Order #${short}`, robots: { index: false } };
}

export default async function OrderPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <OrderDetail id={decodeURIComponent(id)} />;
}
