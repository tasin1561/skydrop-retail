// Demo-mode assistant (no API_URL): keyword search over the bundled catalog and a templated, streamed answer,
// so the chat UI works end to end without the Laravel API or a Claude key. Never calls an AI model.
import * as local from "@/lib/catalog";
import { demoDecorate } from "@/lib/cms";
import { formatBdt, mrpBdt, priceBdt, weightKg } from "@/lib/pricing";
import { isStoreMode } from "@/lib/markets";
import type { Product } from "@/lib/types";
import { isBangla, sse, type AssistantConfig, type AssistantProduct, type ChatRequest } from "./types";

export const DEMO_CONFIG: AssistantConfig = {
  enabled: true,
  basic: false,
  demo: true,
  greeting: {
    en: "Hi! I'm Skydrop AI (demo). Tell me what you're looking for and your budget in taka.",
    bn: "হ্যালো! আমি Skydrop AI (ডেমো)। কী খুঁজছেন আর বাজেট কত টাকা বলুন।",
  },
  prompts: {
    en: ["Best earbuds under ৳3,000", "Compare two smartwatches", "Will it arrive before Eid?", "Where is my order?"],
    bn: ["৩০০০ টাকার মধ্যে ভালো ইয়ারবাড", "দুইটা স্মার্টওয়াচ তুলনা করুন", "ঈদের আগে ডেলিভারি পাব?", "আমার অর্ডার কোথায়?"],
  },
  limit: 50,
  remaining: 50,
};

const STOP = new Set(
  "a an the for me my i want need show find best good under below with and or to of in on is are any some please buy cheap price tk taka bdt which what can you do have recommend suggest help choose around than less from compare vs versus between two this that it one".split(
    " ",
  ),
);

const BN_WORDS: Record<string, string> = {
  ইয়ারবাড: "earbuds", ইয়ারফোন: "earphones", হেডফোন: "headphones", ঘড়ি: "watch", স্মার্টওয়াচ: "smartwatch", জুতা: "shoes",
  শার্ট: "shirt", চা: "tea", তেল: "oil", ফোন: "phone", ব্যাগ: "bag", শাড়ি: "saree", চকলেট: "chocolate", ক্রিম: "cream",
};

const BN_DIGITS = "০১২৩৪৫৬৭৮৯";
const toAscii = (s: string) => s.replace(/[০-৯]/g, (d) => String(BN_DIGITS.indexOf(d)));

export function card(p: Product): AssistantProduct {
  const price = priceBdt(p);
  return {
    id: p.id,
    market: p.market,
    category: p.category,
    title: p.title,
    brand: p.brand,
    image: p.image,
    placeholder: p.placeholder ?? null,
    priceBdt: price,
    mrpBdt: Math.max(price, mrpBdt(p)),
    rating: p.rating,
    reviews: p.reviews,
    inStock: p.stock !== "Out of stock",
    weightKg: weightKg(p),
    restriction: p.restriction ?? null,
  };
}

function search(text: string, store: string | undefined, max?: number) {
  const words = toAscii(text)
    .toLowerCase()
    .replace(/[^\p{L}\p{N}\s-]/gu, " ")
    .split(/\s+/)
    .map((w) => BN_WORDS[w] ?? w)
    .filter((w) => w.length > 1 && !STOP.has(w) && !/^\d+$/.test(w) && !isBangla(w));
  const scope = isStoreMode(store) ? store : "all";
  const tries = [words.join(" "), ...[...words].sort((a, b) => b.length - a.length).slice(0, 3)].filter(Boolean);
  for (const q of tries) {
    const r = local.searchProducts({ q, store: scope, max, perPage: 8, sort: "relevance" });
    if (r.items.length) return { q, items: r.items.map((p) => card(demoDecorate(p))) };
  }
  return { q: words.join(" "), items: [] as AssistantProduct[] };
}

function budget(text: string) {
  const m = toAscii(text).replace(/,/g, "").match(/(\d{3,7})/);
  return m ? Number(m[1]) : undefined;
}

function* chunks(s: string) {
  const parts = s.match(/\S+\s*/g) ?? [s];
  for (const p of parts) yield p;
}

/** Streams a canned answer as SSE (same events as the API). */
export function demoStream(body: ChatRequest, signedIn: boolean): ReadableStream<Uint8Array> {
  const enc = new TextEncoder();
  const msg = body.message.trim();
  const bn = isBangla(msg) || body.locale === "bn";
  const lower = toAscii(msg).toLowerCase();
  const conversationId = body.conversationId || crypto.randomUUID();
  const events: [string, unknown][] = [];
  let text = "";

  if (/order|track|অর্ডার|ট্র্যাক|parcel/.test(lower)) {
    text = signedIn
      ? bn
        ? "ডেমো মোডে অর্ডার ট্র্যাকিং নেই। আপনার অর্ডারগুলো দেখতে My orders পেজে যান।"
        : "Order tracking needs the live API. In the demo, open **My orders** to see your orders."
      : bn
        ? "আপনার অর্ডার দেখতে আগে সাইন ইন করুন।"
        : "Please sign in so I can look up your orders.";
    if (!signedIn) events.push(["login", { reason: "orders" }]);
  } else if (/eid|ঈদ|holiday|deliver|ডেলিভারি|কবে|when.*(arrive|get)/.test(lower)) {
    const d = new Date();
    const add = (n: number) => new Date(d.getTime() + n * 864e5).toISOString().slice(0, 10);
    text = bn
      ? "ভারত থেকে কিনে Skydrop দরজায় পৌঁছে দেয় ৭-১২ দিনে। ছুটির আগে চাইলে অন্তত ১২ দিন আগে অর্ডার করুন।"
      : "We buy in India and Skydrop delivers to your door in **7-12 days**. For a holiday, order at least 12 days before.";
    events.push(["delivery", { minDays: 7, maxDays: 12, earliest: add(7), latest: add(12), cutoffs: [] }]);
  } else if (/hello|^hi\b|hey|হ্যালো|সালাম/.test(lower) && msg.length < 20) {
    text = bn ? DEMO_CONFIG.greeting.bn : DEMO_CONFIG.greeting.en;
  } else {
    const max = budget(msg);
    const compare = /compare|vs|versus|তুলনা/.test(lower);
    const { items } = search(msg, body.store, max);
    if (!items.length) {
      text = bn
        ? "এর সাথে মিলে এমন কিছু আমাদের ক্যাটালগে পাইনি। অন্য নামে খুঁজে দেখুন।"
        : "I couldn't find a match in our catalog. Try another product name or a bigger budget.";
    } else if (compare && items.length >= 2) {
      const [a, b] = items;
      text = bn
        ? `দুটোর তুলনা নিচে দিলাম। ${a.rating >= b.rating ? a.brand || "প্রথমটি" : b.brand || "দ্বিতীয়টি"} রেটিংয়ে এগিয়ে।`
        : `Here's a side-by-side. **${a.rating >= b.rating ? a.title.slice(0, 40) : b.title.slice(0, 40)}** has the better rating.`;
      events.push([
        "compare",
        {
          items: [a, b],
          rows: [
            { label: "Price", values: [formatBdt(a.priceBdt), formatBdt(b.priceBdt)] },
            { label: "Rating", values: [`${a.rating} (${a.reviews})`, `${b.rating} (${b.reviews})`] },
            { label: "Brand", values: [a.brand || "—", b.brand || "—"] },
          ],
        },
      ]);
    } else {
      const top = items[0];
      text = bn
        ? `${items.length}টি পণ্য পেলাম${max ? ` ৳${max.toLocaleString("en-IN")} এর মধ্যে` : ""}। শুরু করার জন্য ${top.brand || "প্রথমটি"} ভালো: ${formatBdt(top.priceBdt)}, রেটিং ${top.rating}।`
        : `I found ${items.length} options${max ? ` under ${formatBdt(max)}` : ""}. A good start is **${top.title.slice(0, 60)}** at ${formatBdt(top.priceBdt)} (rated ${top.rating}). All prices include import charges; delivery takes 7-12 days.`;
      events.push(["products", { items, total: items.length }]);
    }
  }

  return new ReadableStream({
    async start(ctrl) {
      const send = (t: string, d: unknown) => ctrl.enqueue(enc.encode(sse(t, d)));
      const wait = (ms: number) => new Promise((r) => setTimeout(r, ms));
      send("meta", { conversationId, degraded: false });
      if (events.some(([t]) => t === "products" || t === "compare")) {
        send("tool", { name: "search_products" });
        await wait(350);
      }
      for (const [t, d] of events) send(t, d);
      for (const c of chunks(text)) {
        send("text", { delta: c });
        await wait(28);
      }
      send("done", { conversationId, messageId: null, degraded: false, remaining: DEMO_CONFIG.remaining });
      ctrl.close();
    },
  });
}
