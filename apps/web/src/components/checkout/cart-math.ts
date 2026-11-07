import { pointsToEarn, pointsValueBdt } from "@/lib/points";
// Cart totals, coupons and small formatting helpers shared by /cart and /checkout.
import { useCallback, useSyncExternalStore } from "react";
import type { CartItem, MarketId } from "@/lib/types";
import { MARKET_IDS } from "@/lib/markets";
import { PRICING, groupByMarket, shippingFor } from "@/lib/pricing";
import { useFeature } from "@/components/features/FeaturesProvider";
import { fmtDate, type Lang } from "@/i18n/core";
import { apiEnabled } from "@/lib/apiClient";
import { DEMO_COUPONS, couponStore, findDemoCoupon, type Coupon } from "@/lib/coupons";

// ---------- coupons (admin-managed; see src/lib/coupons.ts) ----------
export type { Coupon } from "@/lib/coupons";

/** Demo mode only: live hint while typing against the local demo codes. */
export function couponHint(raw: string): "empty" | "match" | "partial" | "unknown" {
  const v = raw.trim().toUpperCase();
  if (!v) return "empty";
  if (DEMO_COUPONS[v]) return "match";
  if (Object.keys(DEMO_COUPONS).some((c) => c.startsWith(v))) return "partial";
  return "unknown";
}

// Applied coupon persists between /cart and /checkout (per browser) via its own key.
const COUPON_KEY = "skydrop-retail:coupon";
const COUPON_EVT = "skydrop-retail:coupon";
const COUPON_META_KEY = "skydrop-retail:coupon-meta"; // API-validated coupon details

function readCouponMeta(code: string): Coupon | null {
  if (!code) return null;
  try {
    const c = JSON.parse(localStorage.getItem(COUPON_META_KEY) ?? "null") as Coupon | null;
    return c && c.code === code ? c : null;
  } catch {
    return null;
  }
}

function readCoupon() {
  try {
    return localStorage.getItem(COUPON_KEY) ?? "";
  } catch {
    return "";
  }
}

function subscribeCoupon(cb: () => void) {
  window.addEventListener(COUPON_EVT, cb);
  window.addEventListener("storage", cb);
  return () => {
    window.removeEventListener(COUPON_EVT, cb);
    window.removeEventListener("storage", cb);
  };
}

export function useCoupon() {
  const code = useSyncExternalStore(subscribeCoupon, readCoupon, () => "");
  const set = useCallback((next: string, meta?: Coupon) => {
    try {
      if (next) localStorage.setItem(COUPON_KEY, next);
      else localStorage.removeItem(COUPON_KEY);
      if (meta) localStorage.setItem(COUPON_META_KEY, JSON.stringify(meta));
      else if (!next) localStorage.removeItem(COUPON_META_KEY);
    } catch {
      /* storage unavailable */
    }
    window.dispatchEvent(new Event(COUPON_EVT));
  }, []);
  const enabled = useFeature("coupons"); // Admin → Features → Coupons off: ignore any saved code
  // API mode: only a server-validated coupon (its details saved when POST /checkout/quote accepted it). Demo: local codes.
  const c = enabled ? (apiEnabled ? readCouponMeta(code) : (findDemoCoupon(code) ?? readCouponMeta(code))) : null;
  return [c, set] as const;
}

// ---------- totals ----------
export type Group = {
  market: MarketId;
  items: CartItem[];
  count: number;
  sub: number;
  shipping: number; // Skydrop shipping for this store's shipment, before coupons
  toFree: number; // BDT still needed for free shipping (0 when free)
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
  /** fulfilment: paid unboxing at the Kolkata hub (already inside total) */
  unboxingFee?: number;
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
  // Demo mode estimate only: in API mode the coupon discount comes from the server quote (useCouponTotals).
  const only = couponStore(coupon);
  const eligible = only ? groups.filter((g) => g.market === only) : groups;
  const base = eligible.reduce((s, g) => s + g.sub, 0);
  const meetsMin = !!coupon && eligible.length > 0 && base >= (coupon.minSubtotalBdt ?? 0);
  const itemDiscount = !coupon || !meetsMin
    ? 0
    : coupon.kind === "percent"
      ? Math.min(coupon.maxDiscountBdt ?? Infinity, Math.round((base * (coupon.value ?? 10)) / 100))
      : coupon.kind === "fixed"
        ? Math.min(base, Math.round(coupon.value ?? 0))
        : 0;
  const shipDiscount = coupon?.kind === "freeship" && meetsMin ? eligible.reduce((s, g) => s + g.shipping, 0) : 0;
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

// ---------- Bangladesh phone helpers ----------
/** Local 10-digit mobile number (1XXXXXXXXX) from whatever the user typed. */
export function phoneDigits(raw: string) {
  let d = raw.replace(/\D/g, "");
  if (d.startsWith("880")) d = d.slice(3);
  if (d.startsWith("0")) d = d.slice(1);
  return d.slice(0, 10);
}

export function formatPhone(digits: string) {
  if (!digits) return "";
  const a = digits.slice(0, 4);
  const b = digits.slice(4);
  return "+880 " + a + (b ? "-" + b : "");
}

export function phoneValid(digits: string) {
  return /^1[3-9]\d{8}$/.test(digits);
}

/** Handles backspace over the auto-inserted separators so deleting never gets stuck. */
export function nextPhoneDigits(prevDigits: string, rawInput: string) {
  const d = phoneDigits(rawInput);
  if (d === prevDigits && rawInput.length < formatPhone(prevDigits).length) return prevDigits.slice(0, -1);
  return d;
}

export function phoneState(digits: string): "idle" | "typing" | "valid" | "invalid" {
  if (!digits) return "idle";
  if (phoneValid(digits)) return "valid";
  if (digits[0] !== "1" || (digits.length > 1 && !/[3-9]/.test(digits[1]))) return "invalid";
  return digits.length < 10 ? "typing" : "invalid";
}

export function addDays(iso: string, days: number, lang: Lang = "en") {
  const d = new Date(iso);
  d.setDate(d.getDate() + days);
  return fmtDate(d, lang, { day: "numeric", month: "short" });
}
