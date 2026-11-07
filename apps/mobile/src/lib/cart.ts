// Cart totals and coupons. Same math as apps/web/src/components/checkout/cart-math.ts.
import type { CartItem, MarketId, Product } from "./types";
import { MARKET_IDS } from "./markets";
import { PRICING, groupByMarket, mrpBdt, priceBdt, shippingFor } from "./pricing";
import { pointsToEarn, pointsValueBdt } from "./points";
import { fmtDate, type Lang } from "@/i18n/core";

/** A coupon. Built-in demo codes below; live codes come from the server (POST /checkout/quote → coupon). */
export type Coupon = {
  code: string;
  label: string;
  kind: "percent" | "freeship" | "fixed";
  /** percent: % off items; fixed: ৳ off */
  value?: number;
  maxDiscountBdt?: number | null;
  minSubtotalBdt?: number | null;
};

export const COUPONS: Record<string, Coupon> = {
  SKYDROP10: { code: "SKYDROP10", label: "10% off items, up to ৳1,000", kind: "percent", value: 10, maxDiscountBdt: 1000 },
  FREESHIP: { code: "FREESHIP", label: "Free Skydrop shipping on every store", kind: "freeship" },
};

/** Coupon from the API's quote (`coupon: { code, label, kind, value, maxDiscountBdt, minSubtotalBdt }`). */
export function couponFromApi(c: unknown): Coupon | null {
  if (!c || typeof c !== "object") return null;
  const o = c as Record<string, unknown>;
  const kind = o.kind === "percent" || o.kind === "freeship" || o.kind === "fixed" ? o.kind : null;
  if (typeof o.code !== "string" || !kind) return null;
  const num = (v: unknown) => (typeof v === "number" && Number.isFinite(v) ? v : null);
  return {
    code: o.code.toUpperCase(),
    label: typeof o.label === "string" ? o.label : o.code,
    kind,
    value: num(o.value) ?? undefined,
    maxDiscountBdt: num(o.maxDiscountBdt),
    minSubtotalBdt: num(o.minSubtotalBdt),
  };
}

/** Item discount for a coupon (same rules as the API's CheckoutService::quote). */
export function couponItemDiscount(coupon: Coupon | null, subtotal: number): number {
  if (!coupon || (coupon.minSubtotalBdt ?? 0) > subtotal) return 0;
  if (coupon.kind === "percent") {
    const d = Math.round((subtotal * (coupon.value ?? 10)) / 100);
    return coupon.maxDiscountBdt != null ? Math.min(d, coupon.maxDiscountBdt) : d;
  }
  if (coupon.kind === "fixed") return Math.min(subtotal, Math.round(coupon.value ?? 0));
  return 0;
}

export function findCoupon(raw: string | null | undefined): Coupon | null {
  if (!raw) return null;
  return COUPONS[raw.trim().toUpperCase()] ?? null;
}

export function couponHint(raw: string): "empty" | "match" | "partial" | "unknown" {
  const v = raw.trim().toUpperCase();
  if (!v) return "empty";
  if (COUPONS[v]) return "match";
  if (Object.keys(COUPONS).some((c) => c.startsWith(v))) return "partial";
  return "unknown";
}

export type Group = {
  market: MarketId;
  items: CartItem[];
  count: number;
  sub: number;
  shipping: number;
  toFree: number;
};

export type Totals = {
  groups: Group[];
  count: number;
  subtotal: number;
  shipping: number;
  itemDiscount: number;
  shipDiscount: number;
  discount: number;
  total: number;
  pointsRedeemed: number;
  pointsDiscount: number;
  pointsToEarn: number;
};

/** redeemPoints: points the shopper chose to use (already capped by maxRedeem). */
export function computeTotals(items: CartItem[], coupon: Coupon | null, redeemPoints = 0): Totals {
  const map = groupByMarket(items);
  const groups: Group[] = MARKET_IDS.filter((m) => map[m]?.length).map((m) => {
    const list = map[m]!;
    const sub = list.reduce((s, i) => s + i.unitBdt * i.qty, 0);
    return {
      market: m,
      items: list,
      count: list.reduce((n, i) => n + i.qty, 0),
      sub,
      shipping: shippingFor(list),
      toFree: Math.max(0, PRICING.shipping.freeAboveBdt - sub),
    };
  });
  const subtotal = groups.reduce((s, g) => s + g.sub, 0);
  const shipping = groups.reduce((s, g) => s + g.shipping, 0);
  const itemDiscount = couponItemDiscount(coupon, subtotal);
  const shipDiscount = coupon?.kind === "freeship" && (coupon.minSubtotalBdt ?? 0) <= subtotal ? shipping : 0;
  const pointsDiscount = pointsValueBdt(redeemPoints);
  const discount = itemDiscount + shipDiscount + pointsDiscount;
  return {
    pointsRedeemed: pointsDiscount > 0 ? redeemPoints : 0,
    pointsDiscount,
    pointsToEarn: pointsToEarn(subtotal - itemDiscount - pointsDiscount),
    groups,
    count: groups.reduce((n, g) => n + g.count, 0),
    subtotal,
    shipping,
    itemDiscount,
    shipDiscount,
    discount,
    total: Math.max(0, subtotal + shipping - discount),
  };
}

/** BDT price for a product: the server's figure when present, else the shared pricing formula. */
export function unitPrice(p: Product) {
  return p.priceBdt ?? priceBdt(p);
}
export function unitMrp(p: Product) {
  return p.mrpBdt ?? mrpBdt(p);
}

export function cartKey(i: Pick<CartItem, "productId" | "variant">) {
  return i.productId + "|" + (i.variant ?? "");
}

export function isUnavailableStock(stock?: string) {
  return /out of stock|unavailable/i.test(stock ?? "");
}

export function addDays(iso: string, days: number, lang: Lang = "en") {
  const d = new Date(iso);
  d.setDate(d.getDate() + days);
  return fmtDate(d, lang, { day: "numeric", month: "short" });
}
