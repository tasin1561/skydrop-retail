import type { Metadata } from "next";
import Link from "next/link";
import { HowItWorks } from "@/components/home/HowItWorks";
import { Icon } from "@/components/Icon";
import { PRICING, formatBdt } from "@/lib/pricing";

export const metadata: Metadata = { title: "How it works" };

const FAQ = [
  ["Who buys the product?", "Skydrop Retail buys the item from the Indian store named on the product page, on your behalf, and has it delivered to Skydrop's Kolkata hub."],
  ["What is included in the price?", "The product price in BDT includes the store price, import duty estimate and our service. Skydrop shipping is added in the cart by weight, per store."],
  ["How long does delivery take?", "Usually 7-12 days to your door: 1-3 days inside India to our hub, then Skydrop's cross-border transit and home delivery."],
  ["What if the price changes?", "We check the live price and stock just before you pay. If anything changed, you see the new price and decide."],
  ["Can I return an item?", "Damaged or wrong items can be reported within 3 days of delivery. Some categories, like groceries and beauty, cannot be returned once opened."],
];

export default function HowPage() {
  return (
    <div className="mx-auto max-w-5xl px-4 pb-24 pt-6 md:pb-10">
      <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">How Skydrop Retail works</h1>
      <p className="mt-2 max-w-2xl text-ink-2">
        Shop products sold in India without an Indian card, address or customs paperwork. One price in taka, delivered to your door.
      </p>
      <div className="mt-6">
        <HowItWorks compact />
      </div>

      <section id="shipping" className="card mt-8 p-5">
        <h2 className="flex items-center gap-2 text-lg font-extrabold">
          <span className="chip-icon">
            <Icon name="truck" size={18} />
          </span>
          Shipping and customs
        </h2>
        <ul className="mt-3 space-y-2 text-sm text-ink-2">
          <li>
            Skydrop shipping per store: {formatBdt(PRICING.shipping.baseBdt)} + {formatBdt(PRICING.shipping.perKgBdt)} per kg, free over{" "}
            {formatBdt(PRICING.shipping.freeAboveBdt)}.
          </li>
          <li>Import duty is estimated into each product price, so there is nothing to pay on delivery for standard items.</li>
          <li>Items from different stores travel separately and may arrive on different days.</li>
        </ul>
      </section>

      <section id="returns" className="mt-8">
        <h2 className="mb-3 text-lg font-extrabold">Questions</h2>
        <div className="space-y-2">
          {FAQ.map(([q, a]) => (
            <details key={q} className="card group p-4 [&_summary::-webkit-details-marker]:hidden">
              <summary className="flex min-h-8 cursor-pointer items-center justify-between gap-3 text-sm font-bold">
                {q}
                <Icon name="plus" size={18} className="shrink-0 text-accent transition-transform group-open:rotate-45" />
              </summary>
              <p className="mt-2 text-sm leading-relaxed text-ink-2">{a}</p>
            </details>
          ))}
        </div>
      </section>

      <Link href="/" className="btn btn-primary mt-8">
        Start shopping <Icon name="arrow" size={18} className="arrow" />
      </Link>
    </div>
  );
}
