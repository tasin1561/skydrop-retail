// Shopping features for the app (docs/API.md "Shopping"): price history, compare stores, restock waitlist /
// pre-orders and product requests. Live API when reachable; demo mode uses a seeded history (same maths as
// apps/web/src/lib/shopping/history.ts) and AsyncStorage for requests.
import AsyncStorage from "@react-native-async-storage/async-storage";
import { http, isDemo, resolveMode } from "@/api/client";
import { buyPriceInr, priceBdt } from "./pricing";
import type { MarketId, Product } from "./types";

export type PriceInsight = { type: "lowest" | "drop"; label: string; days?: number; dropBdt?: number };
export type PricePoint = { date: string; bdt: number; inr: number; inStock: boolean };
export type PriceHistory = {
  productId: string;
  days: number;
  points: PricePoint[];
  lowestBdt: number;
  highestBdt: number;
  averageBdt: number;
  currentBdt: number;
  position: number;
  positionLabel: "lowest" | "low" | "typical" | "high" | "steady";
  badges: PriceInsight[];
  demo?: boolean;
};
export type CompareRow = {
  productId: string;
  market: MarketId;
  priceBdt: number;
  inStock: boolean;
  delivery: { min: number; max: number };
  current: boolean;
  best: boolean;
  product: Product;
};
export type CompareResult = { productId: string; items: CompareRow[]; bestId: string | null; savingBdt: number };
export type PreorderInfo = {
  productId: string;
  inStock: boolean;
  available: boolean;
  waitlist: number | null;
  watching: boolean;
  depositPct?: number;
  depositBdt?: number;
  etaDays?: number;
  note?: string | null;
};
export type RequestStatus = "new" | "sourcing" | "quoted" | "ordered" | "declined";
export type ProductRequest = {
  id: string;
  status: RequestStatus;
  url?: string | null;
  description?: string | null;
  qty: number;
  budgetBdt?: number | null;
  quoteBdt?: number | null;
  quoteNote?: string | null;
  orderId?: string | null;
  payUrl?: string | null;
  createdAt: string;
};

export const PERIODS = [30, 90, 365] as const;

async function live(): Promise<boolean> {
  await resolveMode();
  return !isDemo();
}

// ---------------- demo price history (seeded per product id) ----------------

function hash(s: string) {
  let h = 2166136261;
  for (let i = 0; i < s.length; i++) h = Math.imul(h ^ s.charCodeAt(i), 16777619);
  return h >>> 0;
}
export function seeded(seed: string) {
  let a = hash(seed);
  return () => {
    a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ (t + t);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}
const iso = (d: Date) => d.toISOString().slice(0, 10);

type PriceInput = Pick<Product, "id" | "priceInr" | "category" | "effectivePriceInr">;

function demoPoints(p: PriceInput, days: number): PricePoint[] {
  const rnd = seeded("ph:" + p.id);
  const cur = buyPriceInr(p);
  const inr: number[] = new Array(365);
  inr[364] = cur;
  let v = cur;
  const dropAt = rnd() < 0.35 ? 2 + Math.floor(rnd() * 5) : -1;
  const dropTo = cur * (1.05 + rnd() * 0.1);
  for (let i = 363; i >= 0; i--) {
    const back = 364 - i;
    if (back === dropAt) v = dropTo;
    else if (rnd() < 0.07) v = v * (1 + (rnd() * 0.16 - 0.05));
    v = Math.min(cur * 1.3, Math.max(cur * 0.86, v));
    inr[i] = Math.round(v);
  }
  const slice = inr.slice(-days);
  const today = new Date();
  today.setUTCHours(0, 0, 0, 0);
  return slice.map((x, i) => ({
    date: iso(new Date(today.getTime() - (slice.length - 1 - i) * 86_400_000)),
    inr: x,
    bdt: i === slice.length - 1 ? priceBdt(p) : priceBdt({ priceInr: x, category: p.category }),
    inStock: true,
  }));
}

export function badges(points: PricePoint[]): PriceInsight[] {
  if (points.length < 2) return [];
  const current = points[points.length - 1].bdt;
  const out: PriceInsight[] = [];
  const weekAgo = iso(new Date(Date.now() - 7 * 86_400_000));
  let ref: number | null = null;
  for (const pt of points) if (pt.date <= weekAgo) ref = pt.bdt;
  ref ??= points[Math.max(0, points.length - 8)].bdt;
  const drop = ref - current;
  if (drop >= Math.max(20, ref * 0.02)) out.push({ type: "drop", label: `Price dropped ৳${drop.toLocaleString("en-IN")} this week`, dropBdt: drop });
  const last30 = points.slice(-30).map((x) => x.bdt);
  if (last30.length >= 7 && Math.max(...last30) > current && current <= Math.min(...last30)) out.unshift({ type: "lowest", label: "Lowest price in 30 days", days: 30 });
  return out;
}

function summarize(id: string, days: number, points: PricePoint[]): PriceHistory {
  const vals = points.map((x) => x.bdt);
  const low = Math.min(...vals);
  const high = Math.max(...vals);
  const current = vals[vals.length - 1];
  const position = high > low ? (current - low) / (high - low) : 0;
  return {
    productId: id,
    days,
    points,
    lowestBdt: low,
    highestBdt: high,
    averageBdt: Math.round(vals.reduce((s, v) => s + v, 0) / vals.length),
    currentBdt: current,
    position,
    positionLabel: high <= low ? "steady" : position <= 0.05 ? "lowest" : position <= 0.35 ? "low" : position < 0.75 ? "typical" : "high",
    badges: badges(points),
    demo: true,
  };
}

// ---------------- API ----------------

const enc = encodeURIComponent;

export const shopping = {
  async priceHistory(p: PriceInput, days: number): Promise<PriceHistory> {
    if (await live()) return http<PriceHistory>("GET", `/products/${enc(p.id)}/price-history`, { query: { days } });
    return summarize(p.id, days, demoPoints(p, days));
  },
  async compare(id: string): Promise<CompareResult | null> {
    if (!(await live())) return null; // the demo catalog has one listing per product
    return http<CompareResult>("GET", `/products/${enc(id)}/compare`);
  },
  async preorder(p: PriceInput, inStock: boolean): Promise<PreorderInfo> {
    if (await live()) return http<PreorderInfo>("GET", `/products/${enc(p.id)}/preorder`);
    const price = priceBdt(p);
    return {
      productId: p.id,
      inStock,
      available: !inStock,
      waitlist: 3 + Math.floor(seeded("wl:" + p.id)() * 48),
      watching: false,
      ...(!inStock ? { depositPct: 30, depositBdt: Math.ceil(price * 0.3), etaDays: 21 } : {}),
    };
  },
  placePreorder(body: { productId: string; qty: number; addressId: number; payment: "bkash" | "nagad" | "card"; returnUrl: string }) {
    return http<{ order: { id: string }; payment: { status: string; redirectUrl?: string } }>("POST", "/preorders", { body });
  },
  async createRequest(body: { url?: string; description?: string; photoUrl?: string; qty?: number; budgetBdt?: number; name?: string; phone?: string; source: "app" }): Promise<ProductRequest> {
    if (await live()) return (await http<{ request: ProductRequest }>("POST", "/product-requests", { body })).request;
    const r: ProductRequest = { id: "D" + Date.now().toString(36).toUpperCase(), status: "new", url: body.url, description: body.description, qty: body.qty ?? 1, budgetBdt: body.budgetBdt, createdAt: new Date().toISOString() };
    const list = JSON.parse((await AsyncStorage.getItem("sr.requests")) ?? "[]") as ProductRequest[];
    await AsyncStorage.setItem("sr.requests", JSON.stringify([r, ...list].slice(0, 50)));
    return r;
  },
  async myRequests(): Promise<ProductRequest[]> {
    if (await live()) return (await http<{ items: ProductRequest[] }>("GET", "/product-requests")).items;
    return JSON.parse((await AsyncStorage.getItem("sr.requests")) ?? "[]") as ProductRequest[];
  },
};
