// Storefront promo coupons (admin "Show as promo"): GET /coupons/featured. Demo mode shows the local demo code.
import { useEffect, useState } from "react";
import { http, resolveMode } from "./client";
import { DEMO_COUPONS, couponFromApi, type Coupon } from "@/lib/cart";
import type { StoreMode } from "@/lib/types";

const cache = new Map<string, { at: number; items: Coupon[] }>();

export async function featuredCoupons(store: StoreMode = "all"): Promise<Coupon[]> {
  if ((await resolveMode()) === "demo") return [DEMO_COUPONS.SKYDROP10];
  const hit = cache.get(store);
  if (hit && Date.now() - hit.at < 60_000) return hit.items;
  try {
    const r = await http<{ items?: unknown[] }>("GET", "/coupons/featured", { query: { store } });
    const items = (r.items ?? []).map(couponFromApi).filter((c): c is Coupon => c !== null);
    cache.set(store, { at: Date.now(), items });
    return items;
  } catch {
    return hit?.items ?? [];
  }
}

/** Featured coupons for a store ([] while loading or when none is live). */
export function useFeaturedCoupons(store: StoreMode = "all"): Coupon[] {
  const [items, setItems] = useState<Coupon[]>(() => cache.get(store)?.items ?? []);
  useEffect(() => {
    let live = true;
    featuredCoupons(store).then((c) => live && setItems(c));
    return () => {
      live = false;
    };
  }, [store]);
  return items;
}
