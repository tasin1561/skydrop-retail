"use client";

// API mode: the coupon discount shown in the cart / checkout comes from the server quote (POST /checkout/quote),
// so admin rules (store, minimum spend, per customer, first order, schedule) always match what the order will get.
import { useEffect, useMemo, useState } from "react";
import { API_URL, apiEnabled, getApiToken } from "@/lib/apiClient";
import { parseCoupon, type Coupon } from "@/lib/coupons";
import type { CartItem } from "@/lib/types";
import type { Totals } from "./cart-math";

type ServerCoupon = { itemDiscount: number; shipDiscount: number; error: string | null };

async function quoteCoupon(items: CartItem[], code: string): Promise<ServerCoupon | null> {
  try {
    const token = getApiToken();
    const res = await fetch(`${API_URL}/checkout/quote`, {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: JSON.stringify({ items: items.map((i) => ({ productId: i.productId, variant: i.variant, qty: i.qty, unitBdt: i.unitBdt })), coupon: code }),
    });
    const data = (await res.json().catch(() => ({}))) as { itemDiscount?: number; shipDiscount?: number; coupon?: unknown; errors?: Record<string, string[]> };
    if (res.ok) return { itemDiscount: data.itemDiscount ?? 0, shipDiscount: data.shipDiscount ?? 0, error: data.coupon ? null : "" };
    if (data.errors?.coupon?.[0]) return { itemDiscount: 0, shipDiscount: 0, error: data.errors.coupon[0] };
    return null; // other problems (items, limits) are shown by the checkout itself
  } catch {
    return null;
  }
}

/**
 * Replaces the local coupon estimate with the server's numbers (API mode). Until the server answers, no coupon discount
 * is counted. `couponError` is the server's reason when the applied coupon no longer fits the cart (null = fine / unknown).
 */
export function useCouponTotals<T extends Totals>(t: T, items: CartItem[], coupon: Coupon | null): T & { couponError: string | null; couponPending: boolean } {
  const code = apiEnabled && coupon ? coupon.code : "";
  const key = code ? code + "|" + items.map((i) => `${i.productId}:${i.variant ?? ""}:${i.qty}:${i.unitBdt}`).join("|") : "";
  const [server, setServer] = useState<{ key: string; data: ServerCoupon | null } | null>(null);

  useEffect(() => {
    if (!key || !items.length) return;
    let live = true;
    const id = setTimeout(() => {
      quoteCoupon(items, code).then((data) => live && setServer({ key, data }));
    }, 250);
    return () => {
      live = false;
      clearTimeout(id);
    };
    // items and code are captured through `key`
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  return useMemo(() => {
    if (!code) return { ...t, couponError: null, couponPending: false };
    const local = t.itemDiscount + t.shipDiscount;
    const s = server && server.key === key ? server.data : null;
    const item = s ? s.itemDiscount : 0;
    const ship = s ? s.shipDiscount : 0;
    const discount = t.discount - local + item + ship;
    return {
      ...t,
      itemDiscount: item,
      shipDiscount: ship,
      discount,
      total: Math.max(0, t.total + local - item - ship),
      couponError: s?.error || null,
      couponPending: !server || server.key !== key,
    };
  }, [t, server, key, code]);
}

// ---------- featured coupons (hints in the coupon field) ----------
let featuredCache: { at: number; items: Coupon[] } | null = null;
let featuredInflight: Promise<Coupon[]> | null = null;

function loadFeatured(): Promise<Coupon[]> {
  if (featuredCache && Date.now() - featuredCache.at < 60_000) return Promise.resolve(featuredCache.items);
  featuredInflight ??= fetch(`${API_URL}/coupons/featured`, { headers: { Accept: "application/json" } })
    .then((r) => (r.ok ? (r.json() as Promise<{ items?: unknown[] }>) : { items: [] }))
    .then((d) => (d.items ?? []).map(parseCoupon).filter((c): c is Coupon => c !== null))
    .catch(() => [] as Coupon[])
    .then((items) => {
      featuredCache = { at: Date.now(), items };
      featuredInflight = null;
      return items;
    });
  return featuredInflight;
}

/** Admin-featured coupons (API mode; [] in demo mode or when none is live). */
export function useFeaturedCoupons(): Coupon[] {
  const [items, setItems] = useState<Coupon[]>(() => featuredCache?.items ?? []);
  useEffect(() => {
    if (!apiEnabled) return;
    let live = true;
    loadFeatured().then((c) => live && setItems(c));
    return () => {
      live = false;
    };
  }, []);
  return items;
}
