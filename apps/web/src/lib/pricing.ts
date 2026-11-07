import type { CartItem, MarketId, Product } from "./types";

// All figures are placeholders until the admin pricing rules are live.
export const PRICING = {
  fxInrToBdt: 1.42, // 1 INR in BDT (update from the FX feed)
  fxBuffer: 0.015, // protects against FX movement between order and purchase
  margin: 0.1, // 10%
  importPct: {
    electronics: 0.15,
    fashion: 0.1,
    beauty: 0.1,
    grocery: 0.05,
    home: 0.1,
  } as Record<string, number>,
  // Skydrop India -> Bangladesh door delivery, per shipment from one store
  shipping: { baseBdt: 180, perKgBdt: 420, freeAboveBdt: 15000 },
  defaultWeightKg: { electronics: 0.4, fashion: 0.6, beauty: 0.15, grocery: 1, home: 1.2 } as Record<
    string,
    number
  >,
};

function roundUp10(n: number) {
  return Math.ceil(n / 10) * 10;
}

/** Store price after coupons and deal prices we can use when we buy. */
export function buyPriceInr(p: Pick<Product, "priceInr" | "effectivePriceInr">) {
  return p.effectivePriceInr && p.effectivePriceInr > 0 && p.effectivePriceInr < p.priceInr ? p.effectivePriceInr : p.priceInr;
}

export function priceBdt(p: Pick<Product, "priceInr" | "category" | "effectivePriceInr">) {
  const base = buyPriceInr(p) * PRICING.fxInrToBdt * (1 + PRICING.fxBuffer);
  const withImport = base * (1 + (PRICING.importPct[p.category] ?? 0.1));
  return roundUp10(withImport * (1 + PRICING.margin));
}

export function mrpBdt(p: Pick<Product, "mrpInr" | "category">) {
  return priceBdt({ priceInr: p.mrpInr, category: p.category });
}

export function discountPct(p: Product) {
  const buy = buyPriceInr(p);
  if (!p.mrpInr || p.mrpInr <= buy) return 0;
  return Math.round((1 - buy / p.mrpInr) * 100);
}

export function weightKg(p: Pick<Product, "weight" | "category">) {
  const w = p.weight;
  if (w) {
    const m = String(w).match(/([\d.]+)\s*(kg|kilo|g|gram)/i);
    if (m) {
      const v = parseFloat(m[1]);
      return /^k/i.test(m[2]) ? v : v / 1000;
    }
  }
  return PRICING.defaultWeightKg[p.category] ?? 0.5;
}

export function shippingFor(items: CartItem[]) {
  const sub = items.reduce((s, i) => s + i.unitBdt * i.qty, 0);
  if (sub >= PRICING.shipping.freeAboveBdt) return 0;
  const kg = Math.max(
    0.5,
    items.reduce((s, i) => s + i.weightKg * i.qty, 0),
  );
  return roundUp10(PRICING.shipping.baseBdt + PRICING.shipping.perKgBdt * kg);
}

export function groupByMarket(items: CartItem[]) {
  const groups: Partial<Record<MarketId, CartItem[]>> = {};
  for (const i of items) (groups[i.market] ??= []).push(i);
  return groups;
}

export function formatBdt(n: number) {
  return "৳" + Math.round(n).toLocaleString("en-IN");
}

export function deliveryWindow() {
  // India store to Kolkata hub (1-3 days) + Skydrop cross-border and last mile (5-8 days)
  return { min: 7, max: 12 };
}
