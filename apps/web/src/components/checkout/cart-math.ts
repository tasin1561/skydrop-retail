// Cart totals, coupons and small formatting helpers shared by /cart and /checkout.
import { useCallback, useSyncExternalStore } from "react";
import type { CartItem, MarketId } from "@/lib/types";
import { MARKET_IDS } from "@/lib/markets";
import { PRICING, groupByMarket, shippingFor } from "@/lib/pricing";

// ---------- coupons (demo codes until the Laravel promotions API exists) ----------
export type Coupon = { code: string; label: string; kind: "percent" | "freeship" };

export const COUPONS: Record<string, Coupon> = {
  SKYDROP10: { code: "SKYDROP10", label: "10% off items, up to ৳1,000", kind: "percent" },
  FREESHIP: { code: "FREESHIP", label: "Free Skydrop shipping on every store", kind: "freeship" },
};

export function findCoupon(raw: string): Coupon | null {
  return COUPONS[raw.trim().toUpperCase()] ?? null;
}

/** Live hint while typing: exact match, could still become a code, or no match. */
export function couponHint(raw: string): "empty" | "match" | "partial" | "unknown" {
  const v = raw.trim().toUpperCase();
  if (!v) return "empty";
  if (COUPONS[v]) return "match";
  if (Object.keys(COUPONS).some((c) => c.startsWith(v))) return "partial";
  return "unknown";
}

// Applied coupon persists between /cart and /checkout (per browser) via its own key.
const COUPON_KEY = "skydrop-retail:coupon";
const COUPON_EVT = "skydrop-retail:coupon";

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
  const set = useCallback((next: string) => {
    try {
      if (next) localStorage.setItem(COUPON_KEY, next);
      else localStorage.removeItem(COUPON_KEY);
    } catch {
      /* storage unavailable */
    }
    window.dispatchEvent(new Event(COUPON_EVT));
  }, []);
  return [findCoupon(code), set] as const;
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
};

export function computeTotals(items: CartItem[], coupon: Coupon | null): Totals {
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
  const itemDiscount = coupon?.kind === "percent" ? Math.min(1000, Math.round(subtotal * 0.1)) : 0;
  const shipDiscount = coupon?.kind === "freeship" ? shipping : 0;
  const discount = itemDiscount + shipDiscount;
  return {
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

export function addDays(iso: string, days: number) {
  const d = new Date(iso);
  d.setDate(d.getDate() + days);
  return d.toLocaleDateString("en-GB", { day: "numeric", month: "short" });
}
