import type { Metadata } from "next";
import Link from "next/link";
import { LegalDoc } from "@/components/legal/LegalDoc";
import { SUPPORT } from "@/lib/support";

export const metadata: Metadata = {
  title: "Privacy policy",
  description: "What personal data Skydrop Retail collects, why, who we share it with, tracking pixels and how long we keep it.",
};

export default function PrivacyPage() {
  return (
    <LegalDoc
      current="/legal/privacy"
      title="Privacy policy"
      intro="What we collect about you, why we need it, who sees it and how long we keep it."
      updated="7 November 2026"
      icon="shield"
      sections={[
        {
          id: "summary",
          title: "The short version",
          body: (
            <ul>
              <li>We collect what we need to buy, ship and support your order: your name, phone number, delivery addresses and order history.</li>
              <li>We do not sell your personal data.</li>
              <li>Your details go to Skydrop (for delivery) and to payment providers (for payment). The Indian stores only see our hub address.</li>
              <li>We use advertising and analytics pixels to measure our ads. You can say no to them.</li>
            </ul>
          ),
        },
        {
          id: "what",
          title: "What we collect",
          body: (
            <>
              <h3>You give us</h3>
              <ul>
                <li>
                  <strong>Phone number</strong>: to sign you in with a one-time code, to send order updates by SMS, and so the rider can call you.
                </li>
                <li>
                  <strong>Name and addresses</strong>: division, district, area and street address for delivery. Addresses you save are kept in your
                  address book until you delete them.
                </li>
                <li>
                  <strong>Email</strong> (optional): for invoices and alerts.
                </li>
                <li>
                  <strong>Reviews, photos links and support messages</strong> you send us.
                </li>
              </ul>
              <h3>Created when you use the site</h3>
              <ul>
                <li>Orders, payments (status, amount and the provider&apos;s transaction ID, never your card number or PIN), points and coupons.</li>
                <li>Wishlists, price-drop alerts and recently viewed products.</li>
                <li>Technical data: IP address, device and browser type, pages visited, and the ad or link that brought you here.</li>
              </ul>
            </>
          ),
        },
        {
          id: "why",
          title: "Why we use it",
          body: (
            <ul>
              <li>To take, buy, ship and deliver your order, and to collect any balance due on delivery.</li>
              <li>To send order updates, OTP codes, and alerts you asked for (price drops, back in stock).</li>
              <li>To prevent fraud and abuse, for example blocking accounts that repeatedly refuse deliveries.</li>
              <li>To keep accounting and tax records that the law requires.</li>
              <li>To measure and improve our ads and the site (see tracking below).</li>
            </ul>
          ),
        },
        {
          id: "sharing",
          title: "Who we share it with",
          body: (
            <table className="lg-table">
              <thead>
                <tr>
                  <th scope="col">Who</th>
                  <th scope="col">What they get</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Skydrop (delivery partner)</td>
                  <td>Name, phone, address, parcel contents and any amount to collect.</td>
                </tr>
                <tr>
                  <td>bKash, Nagad, SSLCommerz</td>
                  <td>Order number and amount; they handle your payment details under their own privacy policies.</td>
                </tr>
                <tr>
                  <td>SMS and email providers</td>
                  <td>Your phone number or email and the message text.</td>
                </tr>
                <tr>
                  <td>Indian stores (amazon.in, flipkart.com, bigbasket.com)</td>
                  <td>Nothing about you. We order with our own account and our Kolkata hub address.</td>
                </tr>
                <tr>
                  <td>Customs authorities</td>
                  <td>Consignee details and invoice when the law requires it for clearance.</td>
                </tr>
                <tr>
                  <td>Advertising platforms (Meta, Google)</td>
                  <td>Event data and hashed (scrambled) phone or email, if you allow tracking. See below.</td>
                </tr>
              </tbody>
            </table>
          ),
        },
        {
          id: "tracking",
          title: "Cookies, pixels and your consent",
          body: (
            <>
              <p>
                We use a few cookies and local storage to keep you signed in and remember your cart. These are needed for the site to work.
              </p>
              <p>
                With your consent, we also use the <strong>Meta Pixel</strong>, <strong>Google Analytics / Google Ads</strong> tags and
                server-to-server conversion reporting (Meta Conversions API). They tell us which ads lead to visits and purchases, so we spend less on
                ads that do not work. Phone numbers and emails sent this way are hashed before they leave our servers.
              </p>
              <p>
                You can refuse or withdraw consent at any time: use the cookie choice on the site when it is shown, clear your browser storage, or ask
                us at the <Link href="/contact">contact page</Link> to stop server-side reporting for your account. Saying no does not affect your
                orders.
              </p>
            </>
          ),
        },
        {
          id: "retention",
          title: "How long we keep it",
          body: (
            <table className="lg-table">
              <thead>
                <tr>
                  <th scope="col">Data</th>
                  <th scope="col">Kept for</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Orders, invoices, payments</td>
                  <td>6 years after the order, for tax and accounting records</td>
                </tr>
                <tr>
                  <td>Account, saved addresses, wishlist</td>
                  <td>Until you delete them or ask us to close your account</td>
                </tr>
                <tr>
                  <td>One-time sign-in codes</td>
                  <td>Expire after 5 minutes; deleted within 30 days</td>
                </tr>
                <tr>
                  <td>Support messages and reviews</td>
                  <td>3 years, or until you ask us to delete them</td>
                </tr>
                <tr>
                  <td>Tracking and analytics events</td>
                  <td>Up to 14 months</td>
                </tr>
                <tr>
                  <td>Server and security logs</td>
                  <td>Up to 90 days</td>
                </tr>
              </tbody>
            </table>
          ),
        },
        {
          id: "rights",
          title: "Your choices and rights",
          body: (
            <ul>
              <li>See and update your name, email and addresses in your account at any time.</li>
              <li>Ask for a copy of the data we hold about you, or ask us to correct it.</li>
              <li>Ask us to delete your account. We will remove what we can and keep only what the law makes us keep (such as invoices).</li>
              <li>Turn off marketing messages using the link in the message or by contacting us. Order updates will still be sent.</li>
            </ul>
          ),
        },
        {
          id: "security",
          title: "Security",
          body: (
            <p>
              Data is sent over encrypted connections (HTTPS). Sign-in codes are stored scrambled, staff access is limited by role, and payment
              details stay with the payment providers. No system is perfectly secure; if a breach affects you, we will tell you without undue delay.
            </p>
          ),
        },
        {
          id: "children",
          title: "Children",
          body: <p>Skydrop Retail is for people aged 18 and over. We do not knowingly collect data from children.</p>,
        },
        {
          id: "contact",
          title: "Contact",
          body: (
            <p>
              Questions or requests about your data: {SUPPORT.channels.find((c) => c.id === "email")?.value}, or write to us at {SUPPORT.address}.
              See all channels on the <Link href="/contact">contact page</Link>.
            </p>
          ),
        },
      ]}
    />
  );
}
