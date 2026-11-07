// AI assistant wire types (docs/API.md "AI assistant"). Shared by the web panel, the proxy and the demo responder.
import type { MarketId, Restriction } from "@/lib/types";

export type AssistantProduct = {
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
  restriction?: Restriction | null;
};

export type OrderCardData = {
  id: string;
  status: string;
  createdAt?: string;
  total: number;
  paymentStatus?: string;
  trackingCode?: string;
  itemCount: number;
  items: { title: string; image: string | null; qty: number }[];
  expectedBy?: string;
  timeline?: { status: string; at: string }[];
};

export type DeliveryData = {
  minDays: number;
  maxDays: number;
  earliest: string;
  latest: string;
  cutoffs: { name: string; date: string; orderBy: string; status: "on_time" | "maybe" | "too_late"; note?: string }[];
  note?: string;
  productId?: string;
  title?: string;
  restriction?: Restriction | null;
  inStock?: boolean;
};

/** Structured events. "text" carries deltas; the rest are cards rendered inline. */
export type AssistantEvent =
  | { type: "meta"; data: { conversationId: string; degraded: boolean } }
  | { type: "text"; data: { delta: string } }
  | { type: "tool"; data: { name: string } }
  | { type: "products"; data: { query?: string; total?: number; items: AssistantProduct[] } }
  | { type: "compare"; data: { items: AssistantProduct[]; rows: { label: string; values: string[] }[] } }
  | { type: "orders"; data: { items: OrderCardData[] } }
  | { type: "order"; data: OrderCardData }
  | { type: "cart"; data: { product: AssistantProduct; variant?: string | null; qty: number } }
  | { type: "delivery"; data: DeliveryData }
  | { type: "priceHistory"; data: { productId: string; currentBdt: number; points: { date: string; bdt: number }[] } }
  | { type: "login"; data: { reason: string } }
  | { type: "request"; data: { id: number | string; title: string } }
  | { type: "done"; data: { conversationId: string; messageId: number | null; degraded: boolean; remaining?: number } }
  | { type: "error"; data: { code: string; message: string } };

export type CardEvent = Exclude<AssistantEvent, { type: "meta" | "text" | "tool" | "done" | "error" }>;

export type ChatRequest = {
  conversationId?: string | null;
  message: string;
  store?: string;
  locale?: "en" | "bn";
  client?: "web" | "mobile";
  context?: { productId?: string; cartIds?: string[] };
};

export type AssistantConfig = {
  enabled: boolean;
  basic: boolean;
  greeting: { en: string; bn: string };
  prompts: { en: string[]; bn: string[] };
  limit: number;
  remaining: number;
  signedIn?: boolean;
  demo?: boolean;
};

export const CARD_TYPES = new Set(["products", "compare", "orders", "order", "cart", "delivery", "priceHistory", "login", "request"]);

/** Incremental SSE parser: feed text chunks, get complete events back. */
export function sseParser() {
  let buf = "";
  return (chunk: string): AssistantEvent[] => {
    buf += chunk.replace(/\r\n/g, "\n");
    const out: AssistantEvent[] = [];
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
      if (!type || !data) continue;
      try {
        out.push({ type, data: JSON.parse(data) } as AssistantEvent);
      } catch {
        /* skip malformed */
      }
    }
    return out;
  };
}

export function sse(type: string, data: unknown) {
  return `event: ${type}\ndata: ${JSON.stringify(data)}\n\n`;
}

export const isBangla = (s: string) => /[ঀ-৿]/.test(s);
