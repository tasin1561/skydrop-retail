import type { Metadata } from "next";
import { CartView } from "@/components/checkout/CartView";
import "@/components/checkout/checkout.css";

export const metadata: Metadata = {
  title: "Cart",
  description: "Your Skydrop Retail cart, grouped by Indian store, with BDT prices and Skydrop door delivery.",
  robots: { index: false },
};

export default function CartPage() {
  return <CartView />;
}
