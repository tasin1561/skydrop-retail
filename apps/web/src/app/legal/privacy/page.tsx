import type { Metadata } from "next";
import Link from "next/link";
import { LegalDoc } from "@/components/legal/LegalDoc";
import { SUPPORT } from "@/lib/support";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { lang } = await getT();
  return lang === "bn"
    ? { title: "প্রাইভেসি নীতি", description: "Skydrop Retail কোন ব্যক্তিগত তথ্য নেয়, কেন নেয়, কার সাথে শেয়ার করে, ট্র্যাকিং পিক্সেল আর কত দিন রাখে।" }
    : { title: "Privacy policy", description: "What personal data Skydrop Retail collects, why, who we share it with, tracking pixels and how long we keep it." };
}

export default async function PrivacyPage() {
  const { lang } = await getT();
  if (lang === "bn") return <PrivacyBn />;
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

/* বাংলা খসড়া (ইংরেজি সংস্করণের অনুবাদ)। আইনজীবী দিয়ে যাচাই করিয়ে নিতে হবে। */
function PrivacyBn() {
  return (
    <LegalDoc
      current="/legal/privacy"
      title="প্রাইভেসি নীতি"
      intro="আপনার সম্পর্কে আমরা কী তথ্য নিই, কেন লাগে, কে দেখে আর কত দিন রাখি।"
      updated="৭ নভেম্বর ২০২৬"
      icon="shield"
      sections={[
        {
          id: "summary",
          title: "সংক্ষেপে",
          body: (
            <ul>
              <li>অর্ডার কেনা, পাঠানো আর সাপোর্টের জন্য যা দরকার তা-ই নিই: আপনার নাম, ফোন নম্বর, ডেলিভারি ঠিকানা আর অর্ডারের ইতিহাস।</li>
              <li>আমরা আপনার ব্যক্তিগত তথ্য বিক্রি করি না।</li>
              <li>আপনার তথ্য যায় Skydrop-এর কাছে (ডেলিভারির জন্য) আর পেমেন্ট প্রোভাইডারের কাছে (পেমেন্টের জন্য)। ইন্ডিয়ান স্টোর শুধু আমাদের হাবের ঠিকানা দেখে।</li>
              <li>বিজ্ঞাপন মাপতে আমরা বিজ্ঞাপন ও অ্যানালিটিক্স পিক্সেল ব্যবহার করি। আপনি না বলতে পারেন।</li>
            </ul>
          ),
        },
        {
          id: "what",
          title: "আমরা কী তথ্য নিই",
          body: (
            <>
              <h3>আপনি যা দেন</h3>
              <ul>
                <li>
                  <strong>ফোন নম্বর</strong>: ওয়ান-টাইম কোড দিয়ে সাইন ইন, SMS-এ অর্ডার আপডেট পাঠানো আর রাইডারের কল করার জন্য।
                </li>
                <li>
                  <strong>নাম ও ঠিকানা</strong>: ডেলিভারির জন্য বিভাগ, জেলা, এলাকা আর রাস্তার ঠিকানা। সেভ করা ঠিকানা আপনি মুছে না ফেলা পর্যন্ত ঠিকানার
                  তালিকায় থাকে।
                </li>
                <li>
                  <strong>ইমেইল</strong> (ঐচ্ছিক): ইনভয়েস আর অ্যালার্টের জন্য।
                </li>
                <li>
                  <strong>রিভিউ, ছবির লিংক আর সাপোর্ট মেসেজ</strong> যা আপনি আমাদের পাঠান।
                </li>
              </ul>
              <h3>সাইট ব্যবহারের সময় তৈরি হয়</h3>
              <ul>
                <li>অর্ডার, পেমেন্ট (স্ট্যাটাস, পরিমাণ আর প্রোভাইডারের ট্রানজ্যাকশন আইডি, কখনো আপনার কার্ড নম্বর বা পিন নয়), পয়েন্ট আর কুপন।</li>
                <li>উইশলিস্ট, দাম কমার অ্যালার্ট আর সম্প্রতি দেখা পণ্য।</li>
                <li>কারিগরি তথ্য: IP ঠিকানা, ডিভাইস ও ব্রাউজারের ধরন, দেখা পেজ, আর যে বিজ্ঞাপন বা লিংক আপনাকে এখানে এনেছে।</li>
              </ul>
            </>
          ),
        },
        {
          id: "why",
          title: "কেন ব্যবহার করি",
          body: (
            <ul>
              <li>আপনার অর্ডার নেওয়া, কেনা, পাঠানো ও ডেলিভারি দেওয়া, আর ডেলিভারিতে বাকি টাকা সংগ্রহের জন্য।</li>
              <li>অর্ডার আপডেট, OTP কোড আর আপনার চাওয়া অ্যালার্ট (দাম কমা, স্টকে ফেরা) পাঠাতে।</li>
              <li>প্রতারণা ও অপব্যবহার ঠেকাতে, যেমন বারবার ডেলিভারি না নেওয়া অ্যাকাউন্ট বন্ধ করা।</li>
              <li>আইন অনুযায়ী হিসাব ও ট্যাক্সের রেকর্ড রাখতে।</li>
              <li>বিজ্ঞাপন আর সাইট মাপতে ও উন্নত করতে (নিচে ট্র্যাকিং দেখুন)।</li>
            </ul>
          ),
        },
        {
          id: "sharing",
          title: "কার সাথে শেয়ার করি",
          body: (
            <table className="lg-table">
              <thead>
                <tr>
                  <th scope="col">কে</th>
                  <th scope="col">তারা কী পায়</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Skydrop (ডেলিভারি পার্টনার)</td>
                  <td>নাম, ফোন, ঠিকানা, পার্সেলে কী আছে আর সংগ্রহের টাকার পরিমাণ।</td>
                </tr>
                <tr>
                  <td>bKash, Nagad, SSLCommerz</td>
                  <td>অর্ডার নম্বর আর পরিমাণ; আপনার পেমেন্টের তথ্য তারা নিজেদের প্রাইভেসি নীতি অনুযায়ী সামলায়।</td>
                </tr>
                <tr>
                  <td>SMS ও ইমেইল প্রোভাইডার</td>
                  <td>আপনার ফোন নম্বর বা ইমেইল আর মেসেজের লেখা।</td>
                </tr>
                <tr>
                  <td>ইন্ডিয়ান স্টোর (amazon.in, flipkart.com, bigbasket.com)</td>
                  <td>আপনার সম্পর্কে কিছুই না। আমরা নিজেদের অ্যাকাউন্ট আর কলকাতা হাবের ঠিকানা দিয়ে অর্ডার করি।</td>
                </tr>
                <tr>
                  <td>কাস্টমস কর্তৃপক্ষ</td>
                  <td>আইনে ক্লিয়ারেন্সের জন্য প্রয়োজন হলে প্রাপকের তথ্য আর ইনভয়েস।</td>
                </tr>
                <tr>
                  <td>বিজ্ঞাপন প্ল্যাটফর্ম (Meta, Google)</td>
                  <td>আপনি ট্র্যাকিংয়ের অনুমতি দিলে ইভেন্টের তথ্য আর হ্যাশ করা (এলোমেলো করা) ফোন বা ইমেইল। নিচে দেখুন।</td>
                </tr>
              </tbody>
            </table>
          ),
        },
        {
          id: "tracking",
          title: "কুকিজ, পিক্সেল ও আপনার সম্মতি",
          body: (
            <>
              <p>সাইন ইন রাখা আর কার্ট মনে রাখার জন্য আমরা কিছু কুকি আর লোকাল স্টোরেজ ব্যবহার করি। সাইট চলতে এগুলো দরকার।</p>
              <p>
                আপনার সম্মতি থাকলে আমরা <strong>Meta Pixel</strong>, <strong>Google Analytics / Google Ads</strong> ট্যাগ আর সার্ভার থেকে সার্ভারে
                কনভার্শন রিপোর্টিং (Meta Conversions API) ব্যবহার করি। কোন বিজ্ঞাপন থেকে ভিজিট আর কেনাকাটা হয় তা এতে জানা যায়, ফলে অকার্যকর
                বিজ্ঞাপনে খরচ কমে। এভাবে পাঠানো ফোন নম্বর আর ইমেইল আমাদের সার্ভার ছাড়ার আগেই হ্যাশ করা হয়।
              </p>
              <p>
                যেকোনো সময় সম্মতি না দিতে বা তুলে নিতে পারেন: সাইটে দেখানো কুকি অপশন ব্যবহার করুন, ব্রাউজারের স্টোরেজ মুছে দিন, অথবা আপনার
                অ্যাকাউন্টের সার্ভার-সাইড রিপোর্টিং বন্ধ করতে <Link href="/contact">যোগাযোগ পেজে</Link> বলুন। না বললে আপনার অর্ডারে কোনো প্রভাব পড়বে না।
              </p>
            </>
          ),
        },
        {
          id: "retention",
          title: "কত দিন রাখি",
          body: (
            <table className="lg-table">
              <thead>
                <tr>
                  <th scope="col">তথ্য</th>
                  <th scope="col">যত দিন রাখা হয়</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>অর্ডার, ইনভয়েস, পেমেন্ট</td>
                  <td>ট্যাক্স ও হিসাবের রেকর্ডের জন্য অর্ডারের পর ৬ বছর</td>
                </tr>
                <tr>
                  <td>অ্যাকাউন্ট, সেভ করা ঠিকানা, উইশলিস্ট</td>
                  <td>আপনি মুছে না ফেলা বা অ্যাকাউন্ট বন্ধ করতে না বলা পর্যন্ত</td>
                </tr>
                <tr>
                  <td>ওয়ান-টাইম সাইন-ইন কোড</td>
                  <td>৫ মিনিটে মেয়াদ শেষ; ৩০ দিনের মধ্যে মুছে ফেলা হয়</td>
                </tr>
                <tr>
                  <td>সাপোর্ট মেসেজ ও রিভিউ</td>
                  <td>৩ বছর, অথবা আপনি মুছতে বলা পর্যন্ত</td>
                </tr>
                <tr>
                  <td>ট্র্যাকিং ও অ্যানালিটিক্স ইভেন্ট</td>
                  <td>সর্বোচ্চ ১৪ মাস</td>
                </tr>
                <tr>
                  <td>সার্ভার ও নিরাপত্তা লগ</td>
                  <td>সর্বোচ্চ ৯০ দিন</td>
                </tr>
              </tbody>
            </table>
          ),
        },
        {
          id: "rights",
          title: "আপনার পছন্দ ও অধিকার",
          body: (
            <ul>
              <li>যেকোনো সময় অ্যাকাউন্টে আপনার নাম, ইমেইল আর ঠিকানা দেখুন ও আপডেট করুন।</li>
              <li>আপনার সম্পর্কে আমাদের কাছে থাকা তথ্যের কপি চাইতে বা সংশোধন করতে বলতে পারেন।</li>
              <li>অ্যাকাউন্ট মুছতে বলতে পারেন। যা সম্ভব মুছে ফেলব, শুধু আইনে যা রাখতে হয় (যেমন ইনভয়েস) তা রাখব।</li>
              <li>মেসেজের লিংক থেকে বা আমাদের জানিয়ে মার্কেটিং মেসেজ বন্ধ করুন। অর্ডার আপডেট তবুও আসবে।</li>
            </ul>
          ),
        },
        {
          id: "security",
          title: "নিরাপত্তা",
          body: (
            <p>
              তথ্য এনক্রিপ্টেড সংযোগে (HTTPS) পাঠানো হয়। সাইন-ইন কোড এলোমেলো করে রাখা হয়, কর্মীদের অ্যাক্সেস ভূমিকা অনুযায়ী সীমিত, আর পেমেন্টের তথ্য
              পেমেন্ট প্রোভাইডারের কাছেই থাকে। কোনো সিস্টেমই পুরোপুরি নিরাপদ নয়; কোনো লঙ্ঘন আপনাকে প্রভাবিত করলে অযথা দেরি না করে জানাব।
            </p>
          ),
        },
        {
          id: "children",
          title: "শিশু",
          body: <p>Skydrop Retail ১৮ বছর ও তার বেশি বয়সীদের জন্য। জেনেশুনে আমরা শিশুদের তথ্য নিই না।</p>,
        },
        {
          id: "contact",
          title: "যোগাযোগ",
          body: (
            <p>
              আপনার তথ্য নিয়ে প্রশ্ন বা অনুরোধ: {SUPPORT.channels.find((c) => c.id === "email")?.value}, অথবা লিখুন {SUPPORT.address} ঠিকানায়।
              সব মাধ্যম দেখুন <Link href="/contact">যোগাযোগ পেজে</Link>।
            </p>
          ),
        },
      ]}
    />
  );
}
