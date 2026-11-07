import type { Metadata } from "next";
import Link from "next/link";
import { LegalDoc } from "@/components/legal/LegalDoc";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { lang } = await getT();
  return lang === "bn"
    ? { title: "রিফান্ড ও বাতিল", description: "কখন বাতিল করা যায়, কখন রিফান্ড হয়, আর bKash, Nagad ও কার্ডে রিফান্ডে কত দিন লাগে।" }
    : { title: "Refunds and cancellations", description: "When you can cancel, when we refund, and how long refunds take by bKash, Nagad and card." };
}

export default async function RefundsPage() {
  const { lang } = await getT();
  if (lang === "bn") return <RefundsBn />;
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

/* বাংলা খসড়া (ইংরেজি সংস্করণের অনুবাদ)। আইনজীবী দিয়ে যাচাই করিয়ে নিতে হবে। */
function RefundsBn() {
  return (
    <LegalDoc
      current="/legal/refunds"
      title="রিফান্ড ও বাতিল"
      intro="প্রতিটি পণ্য আমরা আপনার জন্যই ইন্ডিয়া থেকে কিনি, তাই কী রিফান্ড করা যাবে তা নির্ভর করে পণ্যটি আমরা কিনে ফেলেছি কি না তার ওপর।"
      updated="৭ নভেম্বর ২০২৬"
      icon="refresh"
      sections={[
        {
          id: "before-purchase",
          title: "কেনার আগে বাতিল করলে: পুরো টাকা ফেরত",
          body: (
            <>
              <p>
                অর্ডারে <strong>ইন্ডিয়ায় কেনা হয়েছে</strong> দেখানোর আগ পর্যন্ত আপনি নিজেই <Link href="/orders">আপনার অর্ডার</Link> থেকে বা আমাদের সাথে
                যোগাযোগ করে বাতিল করতে পারবেন। আপনি <strong>যা দিয়েছেন সবই</strong> ফেরত পাবেন: পণ্যের দাম, শিপিং আর যেকোনো অগ্রিম।
              </p>
              <p>আপনি না চাইলেও আমরা পুরো টাকা ফেরত দিই, যদি:</p>
              <ul>
                <li>আমরা কেনার আগেই ইন্ডিয়ান স্টোর অর্ডার বাতিল করে বা স্টক শেষ হয়ে যায়;</li>
                <li>দাম বেড়ে যায় আর আপনি নতুন দামে এগোতে না চান;</li>
                <li>দেখা যায় পণ্যটি বাংলাদেশে পাঠানো সম্ভব নয়।</li>
              </ul>
            </>
          ),
        },
        {
          id: "after-purchase",
          title: "কেনার পর: শুধু ভাঙা বা ভুল পণ্য",
          body: (
            <>
              <p>
                একবার কিনে ফেললে বাংলাদেশ থেকে পণ্যটি ইন্ডিয়ান স্টোরে ফেরত পাঠানো যায় না, তাই মত বদলানো, ভুল সাইজ বেছে নেওয়া বা &quot;পছন্দ হয়নি&quot;
                ধরনের রিটার্ন আমরা নিতে পারি না।
              </p>
              <p>পণ্য যদি এভাবে পৌঁছায়, আমরা রিফান্ড দেব বা বদলে দেব:</p>
              <ul>
                <li>ভাঙা বা ক্ষতিগ্রস্ত;</li>
                <li>অর্ডারের সাথে মেলে না (অর্ডারে যা ছিল তার চেয়ে ভিন্ন পণ্য, রং বা সাইজ);</li>
                <li>কোনো অংশ নেই।</li>
              </ul>
              <p>
                <strong>ডেলিভারির ৩ দিনের মধ্যে</strong> অর্ডার পেজ থেকে (&quot;সাহায্য নিন&quot;) পণ্য আর প্যাকেজিংয়ের ছবিসহ জানান। আমাদের উত্তর
                পাওয়া পর্যন্ত প্যাকেজিং রেখে দিন। সম্ভব হলে পার্সেল খোলার সময় ছোট একটি ভিডিও করে রাখুন।
              </p>
              <p>
                সমস্যা অনুযায়ী আমরা বদলি পণ্য, আংশিক রিফান্ড অথবা পণ্য ও তার শিপিংয়ের অংশসহ পুরো রিফান্ড দেব। পণ্যটি আমাদের ঢাকা হাবে ফেরত পাঠাতে
                বললে সেই খরচ আমরাই দেব।
              </p>
            </>
          ),
        },
        {
          id: "timelines",
          title: "রিফান্ডে কত দিন লাগে",
          body: (
            <>
              <p>অনুমোদনের ১ কার্যদিবসের মধ্যে আমরা রিফান্ড শুরু করি। টাকা যে পদ্ধতিতে দিয়েছিলেন, সেভাবেই ফেরত যায়:</p>
              <table className="lg-table">
                <thead>
                  <tr>
                    <th scope="col">যেভাবে দিয়েছেন</th>
                    <th scope="col">যেখানে ফেরত যাবে</th>
                    <th scope="col">সাধারণত লাগে</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>bKash</td>
                    <td>একই bKash অ্যাকাউন্টে</td>
                    <td>১–৩ কার্যদিবস</td>
                  </tr>
                  <tr>
                    <td>Nagad</td>
                    <td>একই Nagad অ্যাকাউন্টে</td>
                    <td>১–৩ কার্যদিবস</td>
                  </tr>
                  <tr>
                    <td>কার্ড (Visa, Mastercard, Amex)</td>
                    <td>একই কার্ডে</td>
                    <td>ব্যাংকভেদে ৭–১৫ কার্যদিবস</td>
                  </tr>
                  <tr>
                    <td>৫০% অগ্রিম + ক্যাশ অন ডেলিভারি</td>
                    <td>অগ্রিম যে ওয়ালেট থেকে দিয়েছেন সেখানে; রাইডারকে দেওয়া ক্যাশ bKash বা Nagad-এ</td>
                    <td>১–৩ কার্যদিবস</td>
                  </tr>
                </tbody>
              </table>
              <p>
                কিছু রিফান্ডে আমাদের টিমের হাতে একটি ধাপ লাগে। শেষ না হওয়া পর্যন্ত অর্ডারে <strong>রিফান্ড অপেক্ষমাণ</strong> দেখাবে। ওপরের সময়ের পরও না
                পেলে অর্ডার নম্বরসহ <Link href="/contact">আমাদের সাথে যোগাযোগ করুন</Link>।
              </p>
            </>
          ),
        },
        {
          id: "points",
          title: "রিফান্ড হওয়া অর্ডারে পয়েন্ট ও কুপন",
          body: (
            <ul>
              <li>বাতিল অর্ডারে ব্যবহার করা পয়েন্ট সঙ্গে সঙ্গে আপনার ব্যালেন্সে ফেরত আসে।</li>
              <li>যে পয়েন্ট পাওয়ার কথা ছিল তা যোগ হয় না, আর রিফান্ড হওয়া পণ্যে আগে পাওয়া পয়েন্ট ফেরত নেওয়া হয়।</li>
              <li>পুরোপুরি বাতিল অর্ডারে ব্যবহৃত কুপন মেয়াদ থাকলে আবার ব্যবহার করা যাবে।</li>
            </ul>
          ),
        },
        {
          id: "not-refundable",
          title: "যা আমরা রিফান্ড করতে পারি না",
          body: (
            <ul>
              <li>কেনার পর মত বদলানো, অথবা আপনার বেছে নেওয়া ভুল সাইজ বা মডেল।</li>
              <li>ডেলিভারির পরে হওয়া ক্ষতি, বা স্বাভাবিক ব্যবহারজনিত ক্ষয়।</li>
              <li>ডেলিভারির ৩ দিন পর জানানো সমস্যা।</li>
              <li>ভুল ঠিকানা বা ফোন নম্বরের কারণে পার্সেল পৌঁছানো না গেলে বাড়তি ডেলিভারি খরচ।</li>
            </ul>
          ),
        },
      ]}
    />
  );
}
