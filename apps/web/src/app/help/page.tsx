import type { Metadata } from "next";
import Link from "next/link";
import { Icon } from "@/components/Icon";
import { FaqSearch, type Faq } from "@/components/legal/FaqSearch";

export const metadata: Metadata = {
  title: "Help and FAQ",
  description: "Answers about ordering from Indian stores, payment, delivery times, customs, refunds and points.",
};

const FAQS: Faq[] = [
  { topic: "Ordering", q: "How does Skydrop Retail work?", a: "You pick a product from amazon.in, flipkart.com or bigbasket.com and pay in taka. We buy it in India for you, it goes to our Kolkata hub, and Skydrop brings it across the border to your door." },
  { topic: "Ordering", q: "Are the products genuine?", a: "We buy from the Indian store named on the product page, the same listing you see. We do not swap sellers. Brand warranties may only be valid in India." },
  { topic: "Ordering", q: "Why did the price change at checkout?", a: "Indian store prices change often, so we check every item live before you pay. If a price moved by more than 3% we show you the new price and you choose whether to continue." },
  { topic: "Ordering", q: "Why can't I order some items?", a: "Some things cannot be brought into Bangladesh, like weapons, alcohol, tobacco, meat, seeds and aerosols. Others, like medicines, drones and power banks, show a note because they may need permission or more time." },
  { topic: "Payment", q: "How can I pay?", a: "bKash, Nagad, or a Visa, Mastercard or Amex card. You can also pay 50% in advance and the rest in cash to the rider." },
  { topic: "Payment", q: "Is cash on delivery available?", a: "Partly. Because we buy the item in India before it ships, we need at least 50% in advance. The rest can be paid in cash on delivery." },
  { topic: "Payment", q: "How do coupons work?", a: "Enter the code in your cart or at checkout. One coupon per order. Some coupons need a minimum spend; we tell you how much more to add." },
  { topic: "Delivery", q: "How long does delivery take?", a: "Usually 7 to 12 days: 1 to 3 days to our Kolkata hub, then 3 to 6 days across the border and through customs, then 1 to 3 days to your door. Holidays and customs checks can add time." },
  { topic: "Delivery", q: "Do I pay customs or duty when it arrives?", a: "No. Import duty and customs charges are already in the product price. You only pay the remaining balance at the door if you chose the 50% advance." },
  { topic: "Delivery", q: "How much is shipping?", a: "It depends on weight and is charged per Indian store in your order. Shipping is free when one store's items add up to ৳15,000 or more. You see the exact amount before paying." },
  { topic: "Delivery", q: "How do I track my parcel?", a: "Open Orders to see each step. You also get SMS updates, and you can use the tracking code (starting with SD) on track.skydrop.online." },
  { topic: "Refunds", q: "Can I cancel my order?", a: "Yes, for a full refund, until your order shows 'Bought in India'. After that we can only help with damaged, wrong or incomplete items." },
  { topic: "Refunds", q: "My item arrived damaged or wrong. What now?", a: "Report it within 3 days of delivery from your order page with photos of the item and box. We will replace it or refund it." },
  { topic: "Refunds", q: "How long does a refund take?", a: "bKash and Nagad usually 1 to 3 working days, cards 7 to 15 working days depending on your bank. We start the refund within 1 working day of approving it." },
  { topic: "Points", q: "What are Skydrop Points?", a: "You earn 1 point for every ৳100 spent on items, credited when the order is delivered. 100 points take ৳50 off a later order, up to 20% of the items. Points expire after 12 months." },
  { topic: "Account", q: "How do I sign in?", a: "With your Bangladeshi mobile number and a 6-digit code we send by SMS. There is no password to remember." },
  { topic: "Account", q: "Can I write a review?", a: "Yes, after an item you bought through us has been delivered. Reviews are checked by our team before they appear and are marked as verified purchases." },
];

export default function HelpPage() {
  return (
    <div className="mx-auto max-w-3xl px-4 pb-16 pt-6 sm:px-6">
      <header className="rise">
        <span className="chip-icon h-12 w-12 rounded-2xl">
          <Icon name="help" size={22} />
        </span>
        <h1 className="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">How can we help?</h1>
        <p className="mt-2 text-ink-2">Quick answers about ordering from India, payment, delivery and refunds.</p>
      </header>
      <div className="mt-6">
        <FaqSearch faqs={FAQS} />
      </div>
      <div className="mt-10 grid gap-3 sm:grid-cols-2">
        {[
          { href: "/contact", icon: "phone", t: "Still need help?", d: "WhatsApp, Messenger, phone or email" },
          { href: "/legal/shipping", icon: "truck", t: "Shipping and customs", d: "Times, costs and restricted items" },
          { href: "/legal/refunds", icon: "refresh", t: "Refunds and cancellations", d: "What we refund and how fast" },
          { href: "/orders", icon: "receipt", t: "Your orders", d: "Track, cancel or report a problem" },
        ].map((x) => (
          <Link key={x.href} href={x.href} className="card group flex min-h-16 items-center gap-3 p-4 transition-transform hover:-translate-y-0.5">
            <span className="chip-icon shrink-0">
              <Icon name={x.icon} size={18} />
            </span>
            <span className="min-w-0 flex-1">
              <span className="block text-sm font-bold">{x.t}</span>
              <span className="block text-xs text-ink-2">{x.d}</span>
            </span>
            <Icon name="right" size={16} className="text-ink-3 transition-transform group-hover:translate-x-0.5" />
          </Link>
        ))}
      </div>
    </div>
  );
}
