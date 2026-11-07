"use client";

import { useEffect } from "react";
import { cartItems, purchaseEventId, track } from "@/lib/track";
import { useStore } from "@/state/store";

const KEY = "skydrop-retail:purchases-tracked";

/**
 * Browser Purchase on the success page, once per order id (remembered in localStorage). The event id is
 * purchase_<orderId>, the same one the API uses for its server-side Purchase, so Meta / TikTok count it once.
 */
export function TrackPurchase({ orderId, payStatus }: { orderId: string; payStatus?: string }) {
  const { orders, ready } = useStore();
  const order = orders.find((o) => o.id === orderId);

  useEffect(() => {
    if (!ready || !order || payStatus === "failed" || payStatus === "cancelled" || order.status === "cancelled") return;
    let done: string[] = [];
    try {
      done = JSON.parse(localStorage.getItem(KEY) ?? "[]") as string[];
    } catch {
      /* ignore */
    }
    if (done.includes(order.id)) return;
    track("Purchase", {
      eventId: purchaseEventId(order.id),
      orderId: order.id,
      value: order.total,
      shipping: order.shipping,
      items: cartItems(order.items),
    });
    try {
      localStorage.setItem(KEY, JSON.stringify([order.id, ...done].slice(0, 50)));
    } catch {
      /* ignore */
    }
  }, [ready, order, payStatus]);

  return null;
}
