// Coupons are managed in the admin (Sales → Coupons). The storefront never hardcodes codes or rules:
// API mode validates every code with POST /checkout/quote and shows promos from GET /coupons/featured (see docs/API.md "Coupons").
// Demo mode (no API) keeps a small local list so the cart still has something to try.
import type { MarketId, StoreMode } from "./types";
import { STORES } from "./markets";
import type { Translator } from "@/i18n/core";

export type Coupon = {
  code: string;
  /** English label from the API; shown only when no localized text can be built */
  label: string;
  kind: "percent" | "freeship" | "fixed";
  /** percent: % off items; fixed: BDT off items */
  value?: number;
  maxDiscountBdt?: number | null;
  minSubtotalBdt?: number;
  /** "all" or the one store whose items / shipping the coupon discounts */
  store?: StoreMode;
  firstOrderOnly?: boolean;
  promoText?: { en?: string | null; bn?: string | null };
  startsAt?: string | null;
  endsAt?: string | null;
};

/** Demo mode only (no API): local example codes. */
export const DEMO_COUPONS: Record<string, Coupon> = {
  SKYDROP10: { code: "SKYDROP10", label: "10% off items, up to ৳1,000", kind: "percent", value: 10, maxDiscountBdt: 1000, store: "all" },
  FREESHIP: { code: "FREESHIP", label: "Free Skydrop shipping on every store", kind: "freeship", store: "all" },
};

export function findDemoCoupon(raw: string | null | undefined): Coupon | null {
  return raw ? (DEMO_COUPONS[raw.trim().toUpperCase()] ?? null) : null;
}

/** The store a coupon is limited to, or null for every store. */
export function couponStore(c: Pick<Coupon, "store"> | null | undefined): MarketId | null {
  return c?.store && c.store !== "all" ? c.store : null;
}

/** What the coupon gives, in the shopper's language (admin promo text wins). */
export function couponText(c: Coupon, tr: Pick<Translator, "t" | "bdt" | "num" | "lang">): string {
  const promo = c.promoText?.[tr.lang]?.trim();
  if (promo) return promo;
  const { t, bdt, num } = tr;
  const base =
    c.kind === "freeship"
      ? t("coupon.kindFreeship")
      : c.kind === "percent"
        ? c.maxDiscountBdt
          ? t("coupon.kindPercentCap", { value: num(c.value ?? 0), max: bdt(c.maxDiscountBdt) })
          : t("coupon.kindPercent", { value: num(c.value ?? 0) })
        : t("coupon.kindFixed", { amount: bdt(c.value ?? 0) });
  const store = couponStore(c);
  return store ? t("coupon.onStore", { label: base, store: STORES[store].name }) : base;
}

/** Small print: store, minimum spend, first order only. */
export function couponTerms(c: Coupon, tr: Pick<Translator, "t" | "bdt" | "date">): string[] {
  const out: string[] = [];
  if (c.minSubtotalBdt) out.push(tr.t("coupon.minSpend", { amount: tr.bdt(c.minSubtotalBdt) }));
  if (c.firstOrderOnly) out.push(tr.t("coupon.firstOrder"));
  if (c.endsAt) out.push(tr.t("coupon.endsOn", { date: tr.date(c.endsAt) }));
  return out;
}

/** Validates the API shape (quote `coupon`, featured items). */
export function parseCoupon(raw: unknown): Coupon | null {
  if (!raw || typeof raw !== "object") return null;
  const o = raw as Record<string, unknown>;
  const kind = o.kind === "percent" || o.kind === "freeship" || o.kind === "fixed" ? o.kind : null;
  if (typeof o.code !== "string" || !o.code || !kind) return null;
  const num = (v: unknown) => (typeof v === "number" && Number.isFinite(v) ? v : undefined);
  const str = (v: unknown) => (typeof v === "string" && v ? v : null);
  const store = o.store === "amazon" || o.store === "flipkart" || o.store === "bigbasket" ? o.store : "all";
  const promo = o.promoText && typeof o.promoText === "object" ? (o.promoText as Record<string, unknown>) : {};
  return {
    code: o.code,
    label: typeof o.label === "string" ? o.label : o.code,
    kind,
    value: num(o.value),
    maxDiscountBdt: num(o.maxDiscountBdt) ?? null,
    minSubtotalBdt: num(o.minSubtotalBdt),
    store,
    firstOrderOnly: o.firstOrderOnly === true,
    promoText: { en: str(promo.en), bn: str(promo.bn) },
    startsAt: str(o.startsAt),
    endsAt: str(o.endsAt),
  };
}
