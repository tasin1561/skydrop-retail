// Price history maths shared by the chart, card badges and the demo fallback.
// Demo history is deterministic per product id (seeded), so the same product always shows the same chart.
import { buyPriceInr, priceBdt } from "../pricing";
import type { Product } from "../types";
import type { PriceHistory, PriceInsight, PricePoint } from "./types";

export const PERIODS = [30, 90, 365] as const;
export type Period = (typeof PERIODS)[number];

function hash(s: string) {
  let h = 2166136261;
  for (let i = 0; i < s.length; i++) h = Math.imul(h ^ s.charCodeAt(i), 16777619);
  return h >>> 0;
}

/** mulberry32: small, fast seeded PRNG. */
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

/** 365 days of plausible INR prices ending at today's buy price (deterministic per product id). */
function demoInr(p: PriceInput): number[] {
  const rnd = seeded("ph:" + p.id);
  const cur = buyPriceInr(p);
  const out: number[] = new Array(365);
  out[364] = cur;
  let v = cur;
  // A recent drop for roughly a third of products, so the "price dropped" badge shows up in the demo.
  const dropAt = rnd() < 0.35 ? 2 + Math.floor(rnd() * 5) : -1;
  const dropTo = cur * (1.05 + rnd() * 0.1);
  for (let i = 363; i >= 0; i--) {
    const back = 364 - i;
    if (back === dropAt) v = dropTo;
    else if (rnd() < 0.07) v = v * (1 + (rnd() * 0.16 - 0.05)); // older prices drift a little higher
    v = Math.min(cur * 1.3, Math.max(cur * 0.86, v));
    out[i] = Math.round(v);
  }
  return out;
}

export function demoPoints(p: PriceInput, days: number): PricePoint[] {
  const inr = demoInr(p).slice(-days);
  const today = new Date();
  today.setUTCHours(0, 0, 0, 0);
  return inr.map((v, i) => {
    const d = new Date(today.getTime() - (inr.length - 1 - i) * 86_400_000);
    const last = i === inr.length - 1;
    return { date: iso(d), inr: v, bdt: last ? priceBdt({ ...p, flashSale: null, restriction: null }) : priceBdt({ priceInr: v, category: p.category }), inStock: true };
  });
}

/** Same rules as App\Services\Shopping\PriceHistoryService::badges. */
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
  if (last30.length >= 7 && Math.max(...last30) > current && current <= Math.min(...last30)) {
    out.unshift({ type: "lowest", label: "Lowest price in 30 days", days: 30 });
  }
  return out;
}

export function summarize(productId: string, days: number, points: PricePoint[], demo = false): PriceHistory {
  const vals = points.map((x) => x.bdt);
  const low = Math.min(...vals);
  const high = Math.max(...vals);
  const current = vals[vals.length - 1];
  const position = high > low ? (current - low) / (high - low) : 0;
  const positionLabel: PriceHistory["positionLabel"] =
    high <= low ? "steady" : position <= 0.05 ? "lowest" : position <= 0.35 ? "low" : position < 0.75 ? "typical" : "high";
  return {
    productId,
    days,
    points,
    lowestBdt: low,
    highestBdt: high,
    averageBdt: Math.round(vals.reduce((s, v) => s + v, 0) / vals.length),
    currentBdt: current,
    position,
    positionLabel,
    badges: badges(points),
    demo,
  };
}

export function demoHistory(p: PriceInput, days: number): PriceHistory {
  return summarize(p.id, days, demoPoints(p, days), true);
}

/** Card badge for a demo product (API products carry `priceInsight`). */
export function demoInsight(p: PriceInput): PriceInsight | null {
  return badges(demoPoints(p, 30))[0] ?? null;
}
