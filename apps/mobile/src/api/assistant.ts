// AI shopping assistant client (docs/API.md "AI assistant").
// Live: POST /api/v1/assistant/chat streamed with expo/fetch (ReadableStream body); if streaming is unavailable
// it falls back to the non-streaming variant (?stream=0). Demo mode answers locally from the bundled catalog.
import { fetch as expoFetch } from "expo/fetch";
import * as demo from "@/demo/backend";
import { http, isDemo, resolveMode, tokenStore } from "@/api/client";
import { formatBdt, mrpBdt, priceBdt, weightKg } from "@/lib/pricing";
import type { MarketId, Product } from "@/lib/types";

const RAW_URL = (process.env.EXPO_PUBLIC_API_URL ?? "").trim().replace(/\/+$/, "");
const BASE = RAW_URL ? RAW_URL + "/api/v1" : "";

export type AiProduct = {
  id: string;
  market: MarketId;
  category: string;
  title: string;
  brand: string;
  image: string | null;
  placeholder?: { hue: number; icon: string } | null;
  priceBdt: number;
  mrpBdt: number;
  rating: number;
  reviews: number;
  inStock: boolean;
  weightKg: number;
  restriction?: { action: "block" | "warn" | "surcharge"; message: string } | null;
};

export type AiOrder = { id: string; status: string; total: number; itemCount: number; expectedBy?: string; timeline?: { status: string; at: string }[] };

export type AiEvent =
  | { type: "meta"; data: { conversationId: string; degraded: boolean } }
  | { type: "text"; data: { delta: string } }
  | { type: "tool"; data: { name: string } }
  | { type: "products"; data: { items: AiProduct[] } }
  | { type: "compare"; data: { items: AiProduct[]; rows: { label: string; values: string[] }[] } }
  | { type: "orders"; data: { items: AiOrder[] } }
  | { type: "order"; data: AiOrder }
  | { type: "cart"; data: { product: AiProduct; variant?: string | null; qty: number } }
  | { type: "delivery"; data: { minDays: number; maxDays: number; earliest: string; latest: string; cutoffs: { name: string; orderBy: string; status: string }[] } }
  | { type: "login"; data: { reason: string } }
  | { type: "priceHistory"; data: { currentBdt: number; points: { date: string; bdt: number }[] } }
  | { type: "request"; data: { title: string } }
  | { type: "done"; data: { conversationId: string; messageId: number | null; degraded: boolean; remaining?: number } }
  | { type: "error"; data: { code: string; message: string } };

export type AiCard = Exclude<AiEvent, { type: "meta" | "text" | "tool" | "done" | "error" }>;
export const CARD_TYPES = new Set(["products", "compare", "orders", "order", "cart", "delivery", "login", "priceHistory", "request"]);

export type AiConfig = { enabled: boolean; basic: boolean; greeting: { en: string; bn: string }; prompts: { en: string[]; bn: string[] }; limit: number; remaining: number; demo?: boolean };

export type AiRequest = { conversationId?: string | null; message: string; store?: string; locale?: "en" | "bn"; context?: { productId?: string; cartIds?: string[] } };

export class AiError extends Error {
  constructor(
    public status: number,
    message: string,
    public code?: string,
  ) {
    super(message);
  }
}

export const isBangla = (s: string) => /[ঀ-৿]/.test(s);

function parser() {
  let buf = "";
  return (chunk: string): AiEvent[] => {
    buf += chunk.replace(/\r\n/g, "\n");
    const out: AiEvent[] = [];
    let i: number;
    while ((i = buf.indexOf("\n\n")) >= 0) {
      const raw = buf.slice(0, i);
      buf = buf.slice(i + 2);
      let type = "";
      let data = "";
      for (const line of raw.split("\n")) {
        if (line.startsWith("event:")) type = line.slice(6).trim();
        else if (line.startsWith("data:")) data += line.slice(5).trim();
      }
      try {
        if (type && data) out.push({ type, data: JSON.parse(data) } as AiEvent);
      } catch {
        /* skip */
      }
    }
    return out;
  };
}

async function headers(stream: boolean) {
  const token = await tokenStore.get();
  return {
    "Content-Type": "application/json",
    Accept: stream ? "text/event-stream" : "application/json",
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
}

async function fail(res: { status: number; text: () => Promise<string> }): Promise<never> {
  const body = await res.text().catch(() => "");
  let json: { message?: string; code?: string; error?: string } = {};
  try {
    json = JSON.parse(body);
  } catch {
    /* not JSON */
  }
  throw new AiError(res.status, json.message ?? "Skydrop AI is unavailable right now.", json.code ?? json.error);
}

/** Sends one message; onEvent receives streamed events (or the replayed events of the non-streaming reply). */
export async function chat(body: AiRequest, onEvent: (e: AiEvent) => void, signal: AbortSignal) {
  await resolveMode();
  const payload = { ...body, client: "mobile" };
  if (isDemo()) return demoChat(payload, onEvent, signal);

  const canStream = typeof TextDecoder !== "undefined";
  if (canStream) {
    let started = false;
    try {
      const res = await expoFetch(BASE + "/assistant/chat", { method: "POST", headers: await headers(true), body: JSON.stringify(payload), signal });
      if (!res.ok) await fail(res);
      if (res.body) {
        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        const parse = parser();
        for (;;) {
          const { value, done } = await reader.read();
          if (done) break;
          for (const e of parse(decoder.decode(value, { stream: true }))) {
            started = true;
            onEvent(e);
          }
        }
        return;
      }
    } catch (e) {
      if (e instanceof AiError || signal.aborted || started) throw e;
      // streaming not supported on this platform/network: fall back to the JSON variant below
    }
  }

  const res = await fetch(BASE + "/assistant/chat?stream=0", { method: "POST", headers: await headers(false), body: JSON.stringify(payload), signal });
  if (!res.ok) await fail(res);
  const r = (await res.json()) as { conversationId: string; messageId: number; text: string; events: AiEvent[]; degraded: boolean; remaining: number };
  onEvent({ type: "meta", data: { conversationId: r.conversationId, degraded: r.degraded } });
  for (const e of r.events) onEvent(e);
  if (r.text) onEvent({ type: "text", data: { delta: r.text } });
  onEvent({ type: "done", data: { conversationId: r.conversationId, messageId: r.messageId, degraded: r.degraded, remaining: r.remaining } });
}

export async function config(): Promise<AiConfig> {
  await resolveMode();
  if (isDemo()) return DEMO_CONFIG;
  return http<AiConfig>("GET", "/assistant/config");
}

export async function feedback(messageId: number, rating: "up" | "down") {
  if (isDemo()) return;
  await http("POST", "/assistant/feedback", { body: { messageId, rating } }).catch(() => {});
}

export async function clear(conversationId: string) {
  if (isDemo()) return;
  await http("DELETE", `/assistant/conversations/${conversationId}`).catch(() => {});
}

// ---------------------------------------------------------------- demo mode

const DEMO_CONFIG: AiConfig = {
  enabled: true,
  basic: false,
  demo: true,
  greeting: {
    en: "Hi! I'm Skydrop AI (demo). Tell me what you're looking for and your budget in taka.",
    bn: "হ্যালো! আমি Skydrop AI (ডেমো)। কী খুঁজছেন আর বাজেট কত টাকা বলুন।",
  },
  prompts: {
    en: ["Best earbuds under ৳3,000", "Gift ideas for mom", "Will it arrive before Eid?", "Where is my order?"],
    bn: ["৩০০০ টাকার মধ্যে ভালো ইয়ারবাড", "মায়ের জন্য গিফট", "ঈদের আগে ডেলিভারি পাব?", "আমার অর্ডার কোথায়?"],
  },
  limit: 50,
  remaining: 50,
};

const STOP = new Set("a an the for me my i want need show find best good under below with and or to of in on is are any some please buy cheap price tk taka bdt which what can you recommend suggest help choose ideas idea".split(" "));
const BN: Record<string, string> = { ইয়ারবাড: "earbuds", হেডফোন: "headphones", ঘড়ি: "watch", জুতা: "shoes", শার্ট: "shirt", চা: "tea", ফোন: "phone", গিফট: "gift" };
const ascii = (s: string) => s.replace(/[০-৯]/g, (d) => String("০১২৩৪৫৬৭৮৯".indexOf(d)));

export function toAiProduct(p: Product): AiProduct {
  const price = priceBdt(p);
  return {
    id: p.id, market: p.market, category: p.category, title: p.title, brand: p.brand, image: p.image, placeholder: p.placeholder ?? null,
    priceBdt: price, mrpBdt: Math.max(price, mrpBdt(p)), rating: p.rating, reviews: p.reviews, inStock: p.stock !== "Out of stock", weightKg: weightKg(p),
  };
}

async function demoChat(body: AiRequest, onEvent: (e: AiEvent) => void, signal: AbortSignal) {
  const wait = (ms: number) => new Promise((r) => setTimeout(r, ms));
  const msg = ascii(body.message).toLowerCase();
  const bn = isBangla(body.message);
  const conversationId = body.conversationId ?? `demo-${Date.now()}`;
  onEvent({ type: "meta", data: { conversationId, degraded: false } });
  let text: string;
  if (/order|track|অর্ডার/.test(msg)) {
    text = bn ? "অর্ডার দেখতে Orders ট্যাবে যান।" : "Open the Orders tab to see your orders. Live tracking in chat needs the API.";
  } else if (/eid|ঈদ|deliver|ডেলিভারি/.test(msg)) {
    const add = (n: number) => new Date(Date.now() + n * 864e5).toISOString().slice(0, 10);
    onEvent({ type: "delivery", data: { minDays: 7, maxDays: 12, earliest: add(7), latest: add(12), cutoffs: [] } });
    text = bn ? "ডেলিভারি ৭-১২ দিন। ছুটির অন্তত ১২ দিন আগে অর্ডার করুন।" : "Delivery takes 7-12 days. For a holiday, order at least 12 days before.";
  } else {
    const max = Number((msg.replace(/,/g, "").match(/(\d{3,7})/) ?? [])[1]) || undefined;
    const words = msg.replace(/[^\p{L}\p{N}\s-]/gu, " ").split(/\s+/).map((w) => BN[w] ?? w).filter((w) => w.length > 1 && !STOP.has(w) && !/^\d+$/.test(w) && !isBangla(w));
    let items: AiProduct[] = [];
    for (const q of [words.join(" "), ...words]) {
      if (!q) continue;
      items = demo.search({ q, store: (body.store as never) ?? "all", max }).items.slice(0, 8).map(toAiProduct);
      if (items.length) break;
    }
    onEvent({ type: "tool", data: { name: "search_products" } });
    await wait(300);
    if (items.length) onEvent({ type: "products", data: { items } });
    text = items.length
      ? bn
        ? `${items.length}টি পণ্য পেলাম। প্রথমটি ${formatBdt(items[0].priceBdt)}।`
        : `I found ${items.length} options${max ? ` under ${formatBdt(max)}` : ""}. A good start: ${items[0].title.slice(0, 60)} at ${formatBdt(items[0].priceBdt)}.`
      : bn
        ? "মিল পাওয়া যায়নি। অন্য নামে খুঁজে দেখুন।"
        : "I couldn't find a match in the sample catalog. Try another product name.";
  }
  for (const w of text.match(/\S+\s*/g) ?? [text]) {
    if (signal.aborted) throw new Error("aborted");
    onEvent({ type: "text", data: { delta: w } });
    await wait(25);
  }
  onEvent({ type: "done", data: { conversationId, messageId: null, degraded: false, remaining: 50 } });
}
