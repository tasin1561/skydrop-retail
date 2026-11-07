import type { Metadata } from "next";
import { CheckoutView } from "@/components/checkout/CheckoutView";
import "@/components/checkout/checkout.css";

export const metadata: Metadata = {
  title: "Checkout",
  description: "Address, Skydrop delivery, payment and a live price check with the Indian store before you order.",
  robots: { index: false },
};

export default function CheckoutPage() {
  return <CheckoutView />;
}
