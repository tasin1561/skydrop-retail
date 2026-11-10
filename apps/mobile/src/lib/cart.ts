// Cart totals and coupons. Same math as apps/web/src/components/checkout/cart-math.ts.
import type { CartItem, MarketId, Product } from "./types";
import { MARKET_IDS, STORES } from "./markets";
import { PRICING, groupByMarket, mrpBdt, priceBdt, shippingFor } from "./pricing";
import { pointsToEarn, pointsValueBdt } from "./points";
import { fmtDate, type Lang, type Translator } from "@/i18n/core";

/**
 * A coupon. All coupons are managed in the admin (Sales → Coupons): live mode validates every code with
 * POST /checkout/quote and shows promos from GET /coupons/featured. DEMO_COUPONS are for demo mode only.
 */
export type Coupon = {
  code: string;
  label: string;
  kind: "percent" | "freeship" | "fixed";
  /** percent: % off items; fixed: ৳ off */
  value?: number;
  maxDiscountBdt?: number | null;
  minSubtotalBdt?: number | null;
  /** "all" or the one store whose items / shipping it discounts */
  store?: "all" | MarketId;
  firstOrderOnly?: boolean;
  promoText?: { en?: string | null; bn?: string | null };
  endsAt?: string | null;
};

/** Demo mode only (no API). */
export const DEMO_COUPONS: Record<string, Coupon> = {
  SKYDROP10: { code: "SKYDROP10", label: "10% off items, up to ৳1,000", kind: "percent", value: 10, maxDiscountBdt: 1000, store: "all" },
  FREESHIP: { code: "FREESHIP", label: "Free Skydrop shipping on every store", kind: "freeship", store: "all" },
};

/** Coupon from the API (quote `coupon`, featured items). */
export function couponFromApi(c: unknown): Coupon | null {
  if (!c || typeof c !== "object") return null;
  const o = c as Record<string, unknown>;
  const kind = o.kind === "percent" || o.kind === "freeship" || o.kind === "fixed" ? o.kind : null;
  if (typeof o.code !== "string" || !kind) return null;
  const num = (v: unknown) => (typeof v === "number" && Number.isFinite(v) ? v : null);
  const str = (v: unknown) => (typeof v === "string" && v ? v : null);
  const promo = o.promoText && typeof o.promoText === "object" ? (o.promoText as Record<string, unknown>) : {};
  return {
    code: o.code.toUpperCase(),
    label: typeof o.label === "string" ? o.label : o.code,
    kind,
    value: num(o.value) ?? undefined,
    maxDiscountBdt: num(o.maxDiscountBdt),
    minSubtotalBdt: num(o.minSubtotalBdt),
    store: o.store === "amazon" || o.store === "flipkart" || o.store === "myntra" ? o.store : "all",
    firstOrderOnly: o.firstOrderOnly === true,
    promoText: { en: str(promo.en), bn: str(promo.bn) },
    endsAt: str(o.endsAt),
  };
}

/** The store a coupon is limited to, or null for every store. */
export function couponStore(c: Pick<Coupon, "store"> | null | undefined): MarketId | null {
  return c?.store && c.store !== "all" ? c.store : null;
}

/** Item discount for a coupon on the eligible subtotal (same rules as the API's CheckoutService::quote). */
export function couponItemDiscount(coupon: Coupon | null, subtotal: number): number {
  if (!coupon || (coupon.minSubtotalBdt ?? 0) > subtotal) return 0;
  if (coupon.kind === "percent") {
    const d = Math.round((subtotal * (coupon.value ?? 10)) / 100);
    return coupon.maxDiscountBdt != null ? Math.min(d, coupon.maxDiscountBdt) : d;
  }
  if (coupon.kind === "fixed") return Math.min(subtotal, Math.round(coupon.value ?? 0));
  return 0;
}

/** Demo mode lookup. */
export function findCoupon(raw: string | null | undefined): Coupon | null {
  if (!raw) return null;
  return DEMO_COUPONS[raw.trim().toUpperCase()] ?? null;
}

/** Demo mode typing hint against the local codes. */
export function couponHint(raw: string): "empty" | "match" | "partial" | "unknown" {
  const v = raw.trim().toUpperCase();
  if (!v) return "empty";
  if (DEMO_COUPONS[v]) return "match";
  if (Object.keys(DEMO_COUPONS).some((c) => c.startsWith(v))) return "partial";
  return "unknown";
}

/** What the coupon gives, in the shopper's language (admin promo text wins). */
export function couponText(c: Coupon, tr: Pick<Translator, "t" | "bdt" | "lang">): string {
  const promo = c.promoText?.[tr.lang]?.trim();
  if (promo) return promo;
  const base =
    c.kind === "freeship"
      ? tr.t("coupon.kindFreeship")
      : c.kind === "percent"
        ? c.maxDiscountBdt
          ? tr.t("coupon.kindPercentCap", { value: c.value ?? 0, max: tr.bdt(c.maxDiscountBdt) })
          : tr.t("coupon.kindPercent", { value: c.value ?? 0 })
        : tr.t("coupon.kindFixed", { amount: tr.bdt(c.value ?? 0) });
  const store = couponStore(c);
  return store ? tr.t("coupon.onStore", { label: base, store: STORES[store].name }) : base;
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
  // Live mode: an estimate; the checkout shows the server quote's coupon discount.
  const only = couponStore(coupon);
  const eligible = only ? groups.filter((g) => g.market === only) : groups;
  const base = eligible.reduce((s, g) => s + g.sub, 0);
  const itemDiscount = eligible.length ? couponItemDiscount(coupon, base) : 0;
  const shipDiscount =
    coupon?.kind === "freeship" && eligible.length && (coupon.minSubtotalBdt ?? 0) <= base ? eligible.reduce((s, g) => s + g.shipping, 0) : 0;
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
