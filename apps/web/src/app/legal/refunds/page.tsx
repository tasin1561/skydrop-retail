import type { Metadata } from "next";
import Link from "next/link";
import { LegalDoc } from "@/components/legal/LegalDoc";

export const metadata: Metadata = {
  title: "Refunds and cancellations",
  description: "When you can cancel, when we refund, and how long refunds take by bKash, Nagad and card.",
};

export default function RefundsPage() {
  return (
    <LegalDoc
      current="/legal/refunds"
      title="Refunds and cancellations"
      intro="Because we buy each item in India specially for you, what we can refund depends on whether we have bought it yet."
      updated="7 November 2026"
      icon="refresh"
      sections={[
        {
          id: "before-purchase",
          title: "Cancel before we buy it: full refund",
          body: (
            <>
              <p>
                Until your order shows <strong>Bought in India</strong>, you can cancel it yourself from <Link href="/orders">your orders</Link> or by
                contacting us. You get back <strong>everything you paid</strong>: item price, shipping and any advance.
              </p>
              <p>We also refund in full, without you asking, if:</p>
              <ul>
                <li>the Indian store cancels or runs out of stock before we can buy;</li>
                <li>the price went up and you choose not to continue at the new price;</li>
                <li>the item turns out to be one we cannot ship to Bangladesh.</li>
              </ul>
            </>
          ),
        },
        {
          id: "after-purchase",
          title: "After we buy it: damaged or wrong items only",
          body: (
            <>
              <p>
                Once we have bought the item it cannot be returned to the Indian store from Bangladesh, so we cannot accept change-of-mind returns,
                wrong size chosen, or &quot;did not like it&quot;.
              </p>
              <p>We will refund or replace an item if it arrives:</p>
              <ul>
                <li>damaged or broken;</li>
                <li>different from what you ordered (wrong product, colour or size from what was on your order);</li>
                <li>with parts missing.</li>
              </ul>
              <p>
                Report it within <strong>3 days of delivery</strong> from the order page (&quot;Get help&quot;) with photos of the item and the
                packaging. Please keep the packaging until we reply. If you can, record a short video while opening the parcel.
              </p>
              <p>
                Depending on the problem we will offer a replacement, a partial refund, or a full refund of the item and its share of shipping. If we
                ask you to send the item back to our Dhaka hub, we pay for that.
              </p>
            </>
          ),
        },
        {
          id: "timelines",
          title: "How long refunds take",
          body: (
            <>
              <p>We start the refund within 1 working day of approving it. The money goes back the way you paid:</p>
              <table className="lg-table">
                <thead>
                  <tr>
                    <th scope="col">Paid with</th>
                    <th scope="col">Refunded to</th>
                    <th scope="col">Usually takes</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>bKash</td>
                    <td>The same bKash account</td>
                    <td>1–3 working days</td>
                  </tr>
                  <tr>
                    <td>Nagad</td>
                    <td>The same Nagad account</td>
                    <td>1–3 working days</td>
                  </tr>
                  <tr>
                    <td>Card (Visa, Mastercard, Amex)</td>
                    <td>The same card</td>
                    <td>7–15 working days, depending on your bank</td>
                  </tr>
                  <tr>
                    <td>50% advance + cash on delivery</td>
                    <td>The advance goes back to the wallet you paid from; cash paid to the rider by bKash or Nagad</td>
                    <td>1–3 working days</td>
                  </tr>
                </tbody>
              </table>
              <p>
                Some refunds need a manual step by our team. Your order will show <strong>Refund pending</strong> until it is done. If you have not
                received it after the time above, <Link href="/contact">contact us</Link> with your order number.
              </p>
            </>
          ),
        },
        {
          id: "points",
          title: "Points and coupons on refunded orders",
          body: (
            <ul>
              <li>Points you used on a cancelled order are given back to your balance straight away.</li>
              <li>Points you would have earned are not credited, and points already earned on a refunded item are taken back.</li>
              <li>A coupon used on a fully cancelled order can be used again if it has not expired.</li>
            </ul>
          ),
        },
        {
          id: "not-refundable",
          title: "What we cannot refund",
          body: (
            <ul>
              <li>Change of mind after purchase, or a wrong size or model that you chose.</li>
              <li>Damage caused after delivery, or normal wear.</li>
              <li>Problems reported more than 3 days after delivery.</li>
              <li>Extra delivery costs when the parcel could not be delivered because the address or phone number was wrong.</li>
            </ul>
          ),
        },
      ]}
    />
  );
}
