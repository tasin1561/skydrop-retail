// Server-side: storefront promo coupons (admin "Show as promo") from GET /coupons/featured, cached ~60 s.
// Demo mode (no API_URL) shows the local demo code so the strip is not empty.
import { internalHeaders, shopperHeaders } from "./data";
import { DEMO_COUPONS, parseCoupon, type Coupon } from "./coupons";
import type { StoreMode } from "./types";

const API = process.env.API_URL?.replace(/\/$/, "");

export async function featuredCoupons(store: StoreMode = "all"): Promise<Coupon[]> {
  if (!API) return [DEMO_COUPONS.SKYDROP10];
  try {
    const res = await fetch(`${API}/coupons/featured?store=${encodeURIComponent(store)}`, {
      headers: { Accept: "application/json", ...internalHeaders, ...(await shopperHeaders()) },
      next: { revalidate: 60 },
      signal: AbortSignal.timeout(4000),
    });
    if (!res.ok) return [];
    const data = (await res.json()) as { items?: unknown[] };
    return (data.items ?? []).map(parseCoupon).filter((c): c is Coupon => c !== null);
  } catch {
    return []; // no promo rather than a broken page
  }
}
