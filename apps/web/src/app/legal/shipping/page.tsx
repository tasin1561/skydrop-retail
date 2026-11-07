import type { Metadata } from "next";
import Link from "next/link";
import { LegalDoc } from "@/components/legal/LegalDoc";
import { PRICING } from "@/lib/pricing";

export const metadata: Metadata = {
  title: "Shipping and customs",
  description: "How your order travels from the Indian store to your door, how long it takes, customs and duties, and items we cannot ship.",
};

const fmt = (n: number) => "৳" + n.toLocaleString("en-IN");

const RESTRICTED: [string, "Cannot ship" | "Check first", string][] = [
  ["Weapons, knives, pepper spray, air guns", "Cannot ship", "Not allowed to import through Skydrop."],
  ["Alcohol", "Cannot ship", "Not allowed to import through Skydrop."],
  ["Tobacco, cigarettes, vapes, nicotine", "Cannot ship", "Not allowed to import through Skydrop."],
  ["Meat and meat products", "Cannot ship", "Not allowed to import through Skydrop."],
  ["Seeds, live plants, bulbs", "Cannot ship", "Need phytosanitary certificates."],
  ["Aerosols and flammables (sprays, fuel, thinners)", "Cannot ship", "Not safe to carry across the border."],
  ["Power banks and loose lithium batteries", "Check first", "Air-cargo limits; may travel by road and take a few days longer."],
  ["Medicines and supplements", "Check first", "May need a prescription or DGDA clearance; customs can hold them."],
  ["Drones", "Check first", "Need BTRC permission; customs may hold until you show it."],
  ["Liquids over 1 litre", "Check first", "Heavy and need extra packing; shipping is by weight."],
];

export default function ShippingPage() {
  const s = PRICING.shipping;
  return (
    <LegalDoc
      current="/legal/shipping"
      title="Shipping and customs"
      intro="How your order gets from an Indian store to your door, how long it takes and what it costs."
      updated="7 November 2026"
      icon="truck"
      sections={[
        {
          id: "journey",
          title: "The journey",
          body: (
            <ol>
              <li>
                <strong>Indian store → our Kolkata hub</strong> (usually 1–3 days). We buy your item from amazon.in, flipkart.com or bigbasket.com
                and have it delivered to our hub in Kolkata, where we check it and pack it for the border.
              </li>
              <li>
                <strong>Kolkata → Bangladesh</strong> (usually 3–6 days). Skydrop carries it across the border and clears Bangladesh customs.
              </li>
              <li>
                <strong>Dhaka hub → your door</strong> (usually 1–3 days). Skydrop delivers to all 64 districts and calls you before arriving.
              </li>
            </ol>
            ),
        },
        {
          id: "times",
          title: "Delivery times",
          body: (
            <>
              <p>
                Most orders arrive in <strong>7 to 12 days</strong> from the order date. Items from different stores travel as separate parcels and
                may arrive on different days.
              </p>
              <p>
                Delays can happen around Eid and other public holidays in either country, during customs inspections, border closures, strikes, floods
                or cyclones. We will keep your order page and SMS updates current, and you can follow the parcel at{" "}
                <a href="https://track.skydrop.online" rel="noopener">track.skydrop.online</a> with the tracking code on your order.
              </p>
            </>
          ),
        },
        {
          id: "costs",
          title: "Shipping charges",
          body: (
            <>
              <p>
                Shipping is charged per store in your order and depends on weight: {fmt(s.baseBdt)} plus {fmt(s.perKgBdt)} per kg (minimum 0.5 kg),
                rounded up to the nearest ৳10. Shipping is <strong>free</strong> on a store&apos;s items when they add up to {fmt(s.freeAboveBdt)} or
                more. You always see the exact amount before you pay.
              </p>
              <p>If the actual weight is higher than the listing said, we do not charge you the difference.</p>
            </>
          ),
        },
        {
          id: "customs",
          title: "Customs and duties: already included",
          body: (
            <>
              <p>
                The taka price on each product already includes import duty and customs charges. Skydrop handles clearance, so you do not deal with
                customs and you <strong>pay nothing extra at your door</strong> (apart from the balance if you chose the 50% advance).
              </p>
              <p>
                Customs may open parcels to inspect them. For some items (see below) they may ask for a permission or prescription in your name; we
                will contact you if that happens.
              </p>
            </>
          ),
        },
        {
          id: "restricted",
          title: "Items we cannot ship, or need to check",
          body: (
            <>
              <p>
                Product pages and checkout show a notice on these items. Items marked <strong>Cannot ship</strong> cannot be ordered. If customs
                refuses an item that we should have caught, we refund it in full.
              </p>
              <div className="overflow-x-auto">
                <table className="lg-table">
                  <thead>
                    <tr>
                      <th scope="col">Item</th>
                      <th scope="col">Status</th>
                      <th scope="col">Why</th>
                    </tr>
                  </thead>
                  <tbody>
                    {RESTRICTED.map(([item, status, why]) => (
                      <tr key={item}>
                        <td>{item}</td>
                        <td className={status === "Cannot ship" ? "font-semibold text-bad" : "font-semibold text-warn"}>{status}</td>
                        <td>{why}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          ),
        },
        {
          id: "delivery-day",
          title: "On delivery day",
          body: (
            <ul>
              <li>The rider calls the phone number on your order. Please keep it switched on.</li>
              <li>Check the parcel in front of the rider if you can. If it is visibly damaged, take photos before opening.</li>
              <li>
                Something wrong? Report it within 3 days from your order page. See the <Link href="/legal/refunds">refund policy</Link>.
              </li>
            </ul>
          ),
        },
      ]}
    />
  );
}
