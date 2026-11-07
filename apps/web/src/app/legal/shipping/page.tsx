import type { Metadata } from "next";
import Link from "next/link";
import { LegalDoc } from "@/components/legal/LegalDoc";
import { PRICING } from "@/lib/pricing";
import { getT } from "@/i18n/server";
import { fmtBdt } from "@/i18n/core";

export async function generateMetadata(): Promise<Metadata> {
  const { lang } = await getT();
  return lang === "bn"
    ? { title: "শিপিং ও কাস্টমস", description: "ইন্ডিয়ান স্টোর থেকে আপনার দরজা পর্যন্ত অর্ডার কীভাবে আসে, কত দিন লাগে, কাস্টমস ও ডিউটি, আর কোন পণ্য পাঠানো যায় না।" }
    : { title: "Shipping and customs", description: "How your order travels from the Indian store to your door, how long it takes, customs and duties, and items we cannot ship." };
}

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

export default async function ShippingPage() {
  const { lang } = await getT();
  if (lang === "bn") return <ShippingBn />;
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

const RESTRICTED_BN: [string, boolean, string][] = [
  ["অস্ত্র, ছুরি, পেপার স্প্রে, এয়ার গান", true, "Skydrop দিয়ে আমদানির অনুমতি নেই।"],
  ["অ্যালকোহল", true, "Skydrop দিয়ে আমদানির অনুমতি নেই।"],
  ["তামাক, সিগারেট, ভেপ, নিকোটিন", true, "Skydrop দিয়ে আমদানির অনুমতি নেই।"],
  ["মাংস ও মাংসজাত পণ্য", true, "Skydrop দিয়ে আমদানির অনুমতি নেই।"],
  ["বীজ, জীবন্ত গাছ, কন্দ", true, "ফাইটোস্যানিটারি সার্টিফিকেট লাগে।"],
  ["অ্যারোসল ও দাহ্য বস্তু (স্প্রে, জ্বালানি, থিনার)", true, "বর্ডার পার করে বহন করা নিরাপদ নয়।"],
  ["পাওয়ার ব্যাংক ও আলাদা লিথিয়াম ব্যাটারি", false, "এয়ার কার্গোর সীমা আছে; সড়কপথে আসতে পারে, কয়েক দিন বেশি লাগতে পারে।"],
  ["ওষুধ ও সাপ্লিমেন্ট", false, "প্রেসক্রিপশন বা DGDA ক্লিয়ারেন্স লাগতে পারে; কাস্টমস আটকে রাখতে পারে।"],
  ["ড্রোন", false, "BTRC-র অনুমতি লাগে; অনুমতি না দেখানো পর্যন্ত কাস্টমস আটকে রাখতে পারে।"],
  ["১ লিটারের বেশি তরল", false, "ভারী আর বাড়তি প্যাকিং লাগে; শিপিং ওজন অনুযায়ী।"],
];

/* বাংলা খসড়া (ইংরেজি সংস্করণের অনুবাদ)। আইনজীবী দিয়ে যাচাই করিয়ে নিতে হবে। */
function ShippingBn() {
  const s = PRICING.shipping;
  const tk = (n: number) => fmtBdt(n, "bn");
  return (
    <LegalDoc
      current="/legal/shipping"
      title="শিপিং ও কাস্টমস"
      intro="ইন্ডিয়ান স্টোর থেকে আপনার দরজা পর্যন্ত অর্ডার কীভাবে আসে, কত দিন লাগে আর খরচ কত।"
      updated="৭ নভেম্বর ২০২৬"
      icon="truck"
      sections={[
        {
          id: "journey",
          title: "যাত্রাপথ",
          body: (
            <ol>
              <li>
                <strong>ইন্ডিয়ান স্টোর → আমাদের কলকাতা হাব</strong> (সাধারণত ১–৩ দিন)। amazon.in, flipkart.com বা bigbasket.com থেকে আপনার পণ্য কিনে
                কলকাতার হাবে আনাই, সেখানে যাচাই করে বর্ডারের জন্য প্যাক করি।
              </li>
              <li>
                <strong>কলকাতা → বাংলাদেশ</strong> (সাধারণত ৩–৬ দিন)। Skydrop বর্ডার পার করে আনে আর বাংলাদেশের কাস্টমস ক্লিয়ার করে।
              </li>
              <li>
                <strong>ঢাকা হাব → আপনার দরজা</strong> (সাধারণত ১–৩ দিন)। Skydrop ৬৪ জেলাতেই ডেলিভারি দেয়, পৌঁছানোর আগে আপনাকে ফোন করে।
              </li>
            </ol>
          ),
        },
        {
          id: "times",
          title: "ডেলিভারির সময়",
          body: (
            <>
              <p>
                বেশিরভাগ অর্ডার অর্ডারের তারিখ থেকে <strong>৭ থেকে ১২ দিনে</strong> পৌঁছায়। আলাদা স্টোরের পণ্য আলাদা পার্সেলে আসে, তাই ভিন্ন দিনে
                পৌঁছাতে পারে।
              </p>
              <p>
                দুই দেশের ঈদ বা অন্যান্য সরকারি ছুটি, কাস্টমস পরিদর্শন, বর্ডার বন্ধ, হরতাল, বন্যা বা ঘূর্ণিঝড়ের সময় দেরি হতে পারে। অর্ডার পেজ আর SMS
                আপডেট আমরা হালনাগাদ রাখব, আর অর্ডারের ট্র্যাকিং কোড দিয়ে{" "}
                <a href="https://track.skydrop.online" rel="noopener">track.skydrop.online</a>-এ পার্সেল দেখতে পারবেন।
              </p>
            </>
          ),
        },
        {
          id: "costs",
          title: "শিপিং চার্জ",
          body: (
            <>
              <p>
                শিপিং অর্ডারের প্রতিটি স্টোরের জন্য আলাদা ধরা হয় আর ওজনের ওপর নির্ভর করে: {tk(s.baseBdt)} সঙ্গে প্রতি কেজি {tk(s.perKgBdt)} (ন্যূনতম
                ০.৫ কেজি), কাছের ৳১০-এ রাউন্ড করা। এক স্টোরের পণ্য মিলে {tk(s.freeAboveBdt)} বা তার বেশি হলে শিপিং <strong>ফ্রি</strong>। পেমেন্টের
                আগেই সঠিক পরিমাণ দেখতে পাবেন।
              </p>
              <p>লিস্টিংয়ে লেখা ওজনের চেয়ে আসল ওজন বেশি হলে পার্থক্যের টাকা আমরা আপনার কাছ থেকে নিই না।</p>
            </>
          ),
        },
        {
          id: "customs",
          title: "কাস্টমস ও ডিউটি: আগেই ধরা আছে",
          body: (
            <>
              <p>
                প্রতিটি পণ্যের টাকার দামে ইমপোর্ট ডিউটি আর কাস্টমস চার্জ আগেই ধরা আছে। ক্লিয়ারেন্স Skydrop সামলায়, তাই কাস্টমস নিয়ে আপনাকে ভাবতে
                হবে না, আর <strong>ডেলিভারির সময় বাড়তি কিছু দিতে হবে না</strong> (৫০% অগ্রিম বেছে নিলে শুধু বাকি টাকাটা)।
              </p>
              <p>
                কাস্টমস পরিদর্শনের জন্য পার্সেল খুলতে পারে। কিছু পণ্যে (নিচে দেখুন) আপনার নামে অনুমতিপত্র বা প্রেসক্রিপশন চাইতে পারে; তেমন হলে আমরা
                আপনার সাথে যোগাযোগ করব।
              </p>
            </>
          ),
        },
        {
          id: "restricted",
          title: "যে পণ্য পাঠানো যায় না, বা আগে যাচাই করতে হয়",
          body: (
            <>
              <p>
                এসব পণ্যের পেজে আর চেকআউটে নোটিশ দেখায়। <strong>পাঠানো যায় না</strong> চিহ্নিত পণ্য অর্ডার করা যায় না। আমাদের আটকানো উচিত ছিল এমন কোনো
                পণ্য কাস্টমস ফিরিয়ে দিলে আমরা পুরো টাকা ফেরত দিই।
              </p>
              <div className="overflow-x-auto">
                <table className="lg-table">
                  <thead>
                    <tr>
                      <th scope="col">পণ্য</th>
                      <th scope="col">অবস্থা</th>
                      <th scope="col">কারণ</th>
                    </tr>
                  </thead>
                  <tbody>
                    {RESTRICTED_BN.map(([item, blocked, why]) => (
                      <tr key={item}>
                        <td>{item}</td>
                        <td className={blocked ? "font-semibold text-bad" : "font-semibold text-warn"}>{blocked ? "পাঠানো যায় না" : "আগে যাচাই"}</td>
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
          title: "ডেলিভারির দিন",
          body: (
            <ul>
              <li>রাইডার অর্ডারে দেওয়া ফোন নম্বরে কল করবেন। ফোন চালু রাখুন।</li>
              <li>সম্ভব হলে রাইডারের সামনেই পার্সেল দেখে নিন। বাইরে থেকে ক্ষতি দেখা গেলে খোলার আগে ছবি তুলুন।</li>
              <li>
                কোনো সমস্যা? অর্ডার পেজ থেকে ৩ দিনের মধ্যে জানান। দেখুন <Link href="/legal/refunds">রিফান্ড নীতি</Link>।
              </li>
            </ul>
          ),
        },
      ]}
    />
  );
}
