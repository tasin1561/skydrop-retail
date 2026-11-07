import type { Metadata } from "next";
import Link from "next/link";
import { LegalDoc } from "@/components/legal/LegalDoc";
import { SUPPORT } from "@/lib/support";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { lang } = await getT();
  return lang === "bn"
    ? { title: "সেবার শর্তাবলি", description: "Skydrop Retail-এর মাধ্যমে ইন্ডিয়ান স্টোর থেকে কেনা আর বাংলাদেশে ডেলিভারি নেওয়ার শর্তাবলি।" }
    : { title: "Terms of service", description: "The terms for buying from Indian stores through Skydrop Retail and having your order delivered in Bangladesh." };
}

export default async function TermsPage() {
  const { lang } = await getT();
  if (lang === "bn") return <TermsBn />;
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

/* বাংলা খসড়া (ইংরেজি সংস্করণের অনুবাদ)। আইনজীবী দিয়ে যাচাই করিয়ে নিতে হবে। */
function TermsBn() {
  return (
    <LegalDoc
      current="/legal/terms"
      title="সেবার শর্তাবলি"
      intro="Skydrop Retail-এ অর্ডার করলে আপনি কোন কোন বিষয়ে সম্মত হচ্ছেন, সহজ ভাষায়।"
      updated="৭ নভেম্বর ২০২৬"
      icon="receipt"
      sections={[
        {
          id: "who-we-are",
          title: "আমরা কারা",
          body: (
            <>
              <p>
                Skydrop Retail পরিচালনা করে <strong>[কোম্পানির আইনি নাম]</strong>, বাংলাদেশে নিবন্ধিত একটি কোম্পানি (ট্রেড লাইসেন্স
                <strong> [নম্বর]</strong>), অফিস: {SUPPORT.address}। এই শর্তাবলিতে &quot;আমরা&quot; বলতে সেই কোম্পানিকে, আর &quot;আপনি&quot; বলতে
                অর্ডারদাতাকে বোঝানো হয়েছে।
              </p>
              <p>
                আমরা একটি স্বাধীন কেনাকাটা ও ডেলিভারি সেবা। আমরা amazon.in, flipkart.com বা bigbasket.com <strong>নই</strong>, তাদের এজেন্ট বা
                পার্টনারও নই। পণ্যের নাম, ছবি আর ব্র্যান্ড তাদের নিজ নিজ মালিকের।
              </p>
            </>
          ),
        },
        {
          id: "how-it-works",
          title: "অর্ডার কীভাবে হয়",
          body: (
            <ol>
              <li>আপনি কোনো ইন্ডিয়ান স্টোরের লিস্ট করা পণ্য বেছে নিয়ে টাকায় দেখানো দাম আমাদের পরিশোধ করেন।</li>
              <li>আমরা আপনার হয়ে উল্লেখিত ইন্ডিয়ান স্টোর থেকে পণ্যটি কিনে কলকাতায় আমাদের হাবে আনাই।</li>
              <li>আমাদের ডেলিভারি পার্টনার Skydrop বর্ডার পার করে, কাস্টমস ক্লিয়ার করে আপনার দেওয়া ঠিকানায় পৌঁছে দেয়।</li>
            </ol>
          ),
        },
        {
          id: "prices",
          title: "দাম ও তাতে কী কী ধরা আছে",
          body: (
            <>
              <p>
                পণ্যের টাকার দামে ইন্ডিয়ান স্টোরের দাম, মুদ্রা রূপান্তর, ইমপোর্ট ডিউটি ও কাস্টমস চার্জ এবং আমাদের সার্ভিস ফি ধরা আছে। Skydrop শিপিং
                চেকআউটে যোগ হয় আর ওজনের ওপর নির্ভর করে; পেমেন্টের আগেই দেখানো হয়, আর কার্টে দেখানো সীমার বেশি হলে ফ্রি।
              </p>
              <p>
                ইন্ডিয়ান স্টোরে দাম প্রায়ই বদলায়। অর্ডার দেওয়ার ঠিক আগে প্রতিটি পণ্য আমরা স্টোরের সাথে আবার যাচাই করি। দাম ৩%-এর বেশি বদলালে নতুন
                দাম দেখাই, এগোবেন কি না আপনি ঠিক করবেন। এর চেয়ে ছোট পরিবর্তন যাচাই করা দামেই সমন্বয় করা হয়।
              </p>
              <p>
                <strong>ডেলিভারির সময় বাড়তি কোনো চার্জ নেই</strong>, শুধু ৫০% অগ্রিম বেছে নিলে বাকি টাকাটা দিতে হবে।
              </p>
            </>
          ),
        },
        {
          id: "payment",
          title: "পেমেন্ট",
          body: (
            <>
              <p>
                bKash, Nagad বা কার্ডে পুরো টাকা দিতে পারেন, অথবা ৫০% অগ্রিম দিয়ে বাকিটা রাইডারকে ক্যাশে দিতে পারেন। পেমেন্ট প্রসেস করে পেমেন্ট
                প্রোভাইডার; আপনার পুরো কার্ড নম্বর বা ওয়ালেট পিন আমরা কখনো দেখি না, রাখিও না।
              </p>
              <p>পেমেন্ট (বা অগ্রিম) নিশ্চিত হওয়ার পরেই আমরা আপনার পণ্য কিনি।</p>
            </>
          ),
        },
        {
          id: "cancellations",
          title: "বাতিল ও রিফান্ড",
          body: (
            <p>
              ইন্ডিয়ায় পণ্যটি কেনার আগ পর্যন্ত বাতিল করলে পুরো টাকা ফেরত পাবেন। এরপর শুধু ভাঙা, ভুল বা অসম্পূর্ণ পণ্যে রিফান্ড দেওয়া যায়।
              bKash, Nagad আর কার্ডে রিফান্ডের সময়সহ বিস্তারিত আছে আমাদের <Link href="/legal/refunds">রিফান্ড নীতিতে</Link>।
            </p>
          ),
        },
        {
          id: "delivery",
          title: "ডেলিভারি",
          body: (
            <>
              <p>
                বেশিরভাগ অর্ডার ৭ থেকে ১২ দিনে পৌঁছায়। এটি আনুমানিক, নিশ্চয়তা নয়: কাস্টমস চেক, সরকারি ছুটি, বর্ডার বন্ধ, হরতাল আর আবহাওয়ার
                কারণে দেরি হতে পারে। দেখুন <Link href="/legal/shipping">শিপিং নীতি</Link>।
              </p>
              <p>
                সঠিক ঠিকানা আর চালু থাকা ফোন নম্বর দিন। আপনাকে না পাওয়ায় ডেলিভারি ব্যর্থ হলে Skydrop আবার চেষ্টা করবে; বাড়তি ডেলিভারির চেষ্টা বা
                হাবে ফেরত পাঠানোর খরচ প্রকৃত খরচে নেওয়া হতে পারে।
              </p>
            </>
          ),
        },
        {
          id: "restricted",
          title: "যে পণ্য পাঠানো যায় না",
          body: (
            <p>
              অস্ত্র, অ্যালকোহল, তামাক, মাংস, বীজ আর দাহ্য পণ্যের মতো কিছু জিনিস আইনগতভাবে বা নিরাপদে বাংলাদেশে আনা যায় না। চেকআউটে এগুলো আমরা
              আটকে দিই। ওষুধ, ড্রোন আর পাওয়ার ব্যাংকের মতো কিছু পণ্যে অনুমতি বা বাড়তি সময় লাগতে পারে। আমাদের ধরা উচিত ছিল এমন কারণে কাস্টমস কোনো
              পণ্য আটকালে বা ফিরিয়ে দিলে আমরা পুরো টাকা ফেরত দিই। তালিকা আছে <Link href="/legal/shipping#restricted">শিপিং নীতিতে</Link>।
            </p>
          ),
        },
        {
          id: "warranty",
          title: "পণ্যের মান ও ওয়ারেন্টি",
          body: (
            <>
              <p>
                নির্মাতা যা দেয়, পণ্যের সাথে সেটুকুই থাকে। ইন্ডিয়ান নির্মাতাদের অনেক ওয়ারেন্টি শুধু ইন্ডিয়ায় চলে, তাই বাংলাদেশে কাজে না-ও লাগতে
                পারে। লিস্টিংয়ে ভিন্ন কিছু বলা না থাকলে ধরে নিন স্থানীয় ওয়ারেন্টি নেই।
              </p>
              <p>অর্ডারের আগে সাইজ, ভোল্টেজ (বাংলাদেশে ২২০V আর C/D/G টাইপ প্লাগ) আর সামঞ্জস্য দেখে নিন।</p>
            </>
          ),
        },
        {
          id: "points",
          title: "কুপন ও Skydrop পয়েন্ট",
          body: (
            <p>
              কুপন আর পয়েন্টের কোনো নগদ মূল্য নেই, টাকায় বদলানো যায় না। প্রতি অর্ডারে একটি কুপন ব্যবহার করা যায়। অর্ডার ডেলিভারি হলে পয়েন্ট যোগ হয়,
              যোগ হওয়ার ১২ মাস পর মেয়াদ শেষ হয়, আর অর্ডার বাতিল বা রিফান্ড হলে ফেরত নেওয়া হয়। আমরা যেকোনো সময় অফার বদলাতে বা বন্ধ করতে পারি, তবে আগে
              দেওয়া অর্ডারে নয়।
            </p>
          ),
        },
        {
          id: "account",
          title: "আপনার অ্যাকাউন্ট",
          body: (
            <p>
              আপনি ফোন নম্বর আর ওয়ান-টাইম কোড দিয়ে সাইন ইন করেন। ফোন নিরাপদে রাখুন; আপনার অ্যাকাউন্ট থেকে দেওয়া অর্ডার আপনার বলেই গণ্য হবে।
              প্রতারণা, অপব্যবহার বা এই শর্ত ভেঙে রিসেলিংয়ের জন্য ব্যবহৃত অ্যাকাউন্ট আমরা স্থগিত করতে পারি।
            </p>
          ),
        },
        {
          id: "liability",
          title: "আমাদের দায়",
          body: (
            <p>
              আমাদের কারণে কোনো সমস্যা হলে আমাদের দায় সংশ্লিষ্ট পণ্যের জন্য আপনার দেওয়া টাকা আর তার শিপিংয়ের অংশ ফেরত দেওয়া পর্যন্ত সীমিত।
              দেরির কারণে ব্যবসায়িক ক্ষতি বা কোনো অনুষ্ঠান মিস হওয়ার মতো পরোক্ষ ক্ষতির দায় আমাদের নয়। ভোক্তা-অধিকার সংরক্ষণ আইন, ২০০৯ বা
              বাংলাদেশের অন্য আইনে আপনার যে অধিকার সীমিত করা যায় না, এই শর্তাবলি তা সীমিত করে না।
            </p>
          ),
        },
        {
          id: "law",
          title: "আইন ও অভিযোগ",
          body: (
            <p>
              এই শর্তাবলি বাংলাদেশের আইন অনুযায়ী পরিচালিত। কোনো অভিযোগ থাকলে আগে <Link href="/contact">যোগাযোগ পেজে</Link> আমাদের জানান; ২
              কার্যদিবসের মধ্যে উত্তর দেওয়ার চেষ্টা করি। আমরা একসাথে মীমাংসা করতে না পারলে বিরোধ ঢাকার আদালতে নিষ্পত্তি হবে। আপনি জাতীয় ভোক্তা-অধিকার
              সংরক্ষণ অধিদপ্তরেও যোগাযোগ করতে পারেন।
            </p>
          ),
        },
        {
          id: "changes",
          title: "শর্তাবলির পরিবর্তন",
          body: <p>আমরা এই শর্তাবলি হালনাগাদ করতে পারি। কোনো অর্ডারে সেই সংস্করণ প্রযোজ্য হবে, যা অর্ডার দেওয়ার সময় দেখানো হয়েছিল।</p>,
        },
      ]}
    />
  );
}
