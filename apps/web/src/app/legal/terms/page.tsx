import type { Metadata } from "next";
import Link from "next/link";
import { LegalDoc } from "@/components/legal/LegalDoc";
import { SUPPORT } from "@/lib/support";

export const metadata: Metadata = {
  title: "Terms of service",
  description: "The terms for buying from Indian stores through Skydrop Retail and having your order delivered in Bangladesh.",
};

export default function TermsPage() {
  return (
    <LegalDoc
      current="/legal/terms"
      title="Terms of service"
      intro="What you agree to when you order through Skydrop Retail, in plain words."
      updated="7 November 2026"
      icon="receipt"
      sections={[
        {
          id: "who-we-are",
          title: "Who we are",
          body: (
            <>
              <p>
                Skydrop Retail is operated by <strong>[Company legal name]</strong>, a company registered in Bangladesh (trade licence
                <strong> [number]</strong>), with its office at {SUPPORT.address}. In these terms, &quot;we&quot; and &quot;us&quot; mean that
                company, and &quot;you&quot; means the person placing the order.
              </p>
              <p>
                We are an independent buying and delivery service. We are <strong>not</strong> amazon.in, flipkart.com or bigbasket.com, and we are
                not their agent or partner. Product names, photos and brands belong to their owners.
              </p>
            </>
          ),
        },
        {
          id: "how-it-works",
          title: "How an order works",
          body: (
            <ol>
              <li>You choose a product listed by an Indian store and pay us the price shown in taka.</li>
              <li>We buy that product from the named Indian store on your behalf and have it delivered to our hub in Kolkata.</li>
              <li>Our delivery partner Skydrop carries it across the border, clears customs and delivers it to the address you gave us.</li>
            </ol>
          ),
        },
        {
          id: "prices",
          title: "Prices and what they include",
          body: (
            <>
              <p>
                The taka price on a product includes the Indian store price, currency conversion, import duty and customs charges, and our service fee.
                Skydrop shipping is added at checkout and depends on weight; it is shown before you pay and is free above the limit shown in the cart.
              </p>
              <p>
                Indian store prices change often. We check every item again with the store just before you place the order. If a price has moved by
                more than 3%, we show you the new price and you decide whether to continue. Smaller changes are absorbed at the checked price.
              </p>
              <p>
                There are <strong>no extra charges at your door</strong>, except the unpaid balance if you chose the 50% advance option.
              </p>
            </>
          ),
        },
        {
          id: "payment",
          title: "Payment",
          body: (
            <>
              <p>
                You can pay in full by bKash, Nagad or card, or pay 50% in advance and the rest in cash to the rider. Payments are processed by the
                payment provider; we never see or store your full card number or wallet PIN.
              </p>
              <p>We only buy your items after the payment (or the advance) has been confirmed.</p>
            </>
          ),
        },
        {
          id: "cancellations",
          title: "Cancellations and refunds",
          body: (
            <p>
              You can cancel for a full refund until we have bought the item in India. After that, we can only refund items that arrive damaged,
              wrong or incomplete. The details, including refund times for bKash, Nagad and cards, are in our{" "}
              <Link href="/legal/refunds">refund policy</Link>.
            </p>
          ),
        },
        {
          id: "delivery",
          title: "Delivery",
          body: (
            <>
              <p>
                Most orders arrive in 7 to 12 days. This is an estimate, not a guarantee: customs checks, public holidays, border closures, strikes and
                weather can cause delays. See the <Link href="/legal/shipping">shipping policy</Link>.
              </p>
              <p>
                Please give a correct address and a phone number that is switched on. If delivery fails because we cannot reach you, Skydrop will try
                again; extra delivery attempts or returns to the hub may be charged at cost.
              </p>
            </>
          ),
        },
        {
          id: "restricted",
          title: "Items we cannot ship",
          body: (
            <p>
              Some items cannot legally or safely be brought into Bangladesh, such as weapons, alcohol, tobacco, meat, seeds and flammable
              products. We block these at checkout. Other items, like medicines, drones and power banks, may need permission or extra time. If an
              item is held or refused by customs for a reason we should have caught, we refund it in full. The list is in the{" "}
              <Link href="/legal/shipping#restricted">shipping policy</Link>.
            </p>
          ),
        },
        {
          id: "warranty",
          title: "Product quality and warranty",
          body: (
            <>
              <p>
                Products come with whatever the manufacturer provides. Many Indian manufacturer warranties are only valid in India, so they may not be
                usable in Bangladesh. Where the listing does not say otherwise, assume there is no local warranty.
              </p>
              <p>Check sizes, voltages (Bangladesh uses 220V and type C/D/G plugs) and compatibility before you order.</p>
            </>
          ),
        },
        {
          id: "points",
          title: "Coupons and Skydrop Points",
          body: (
            <p>
              Coupons and points have no cash value and cannot be exchanged for money. One coupon can be used per order. Points are earned when an
              order is delivered, expire 12 months after they are credited, and are reversed if the order is cancelled or refunded. We may change or
              end promotions at any time, but not for orders already placed.
            </p>
          ),
        },
        {
          id: "account",
          title: "Your account",
          body: (
            <p>
              You sign in with your phone number and a one-time code. Keep your phone secure; orders placed from your account are treated as yours.
              We may suspend accounts used for fraud, abuse or reselling that breaks these terms.
            </p>
          ),
        },
        {
          id: "liability",
          title: "Our responsibility",
          body: (
            <p>
              If something goes wrong because of us, our responsibility is limited to refunding what you paid for the affected item and its share of
              shipping. We are not responsible for indirect losses, such as lost business or missed events because of a delay. Nothing in these terms
              limits rights you have under the Consumer Rights Protection Act, 2009 or other Bangladeshi law that cannot be limited.
            </p>
          ),
        },
        {
          id: "law",
          title: "Law and complaints",
          body: (
            <p>
              These terms are governed by the laws of Bangladesh. If you have a complaint, please contact us first at the{" "}
              <Link href="/contact">contact page</Link>; we aim to answer within 2 working days. Disputes that we cannot settle together will be
              handled by the courts of Dhaka. You can also contact the Directorate of National Consumer Rights Protection.
            </p>
          ),
        },
        {
          id: "changes",
          title: "Changes to these terms",
          body: <p>We may update these terms. The version that applies to an order is the one shown when the order was placed.</p>,
        },
      ]}
    />
  );
}
