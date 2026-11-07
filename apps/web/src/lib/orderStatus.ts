import type { MarketId, Order, OrderStatus } from "./types";
import { STORES } from "./markets";
import { deliveryWindow } from "./pricing";

// Order tracking stages, in order. In production the real status comes from the Skydrop API
// (and its webhooks pushing hub scans); the demo below only SIMULATES progress so the
// tracking UI can be seen moving.

export type TrackStatus = Exclude<OrderStatus, "cancelled">;
export type Stage = { status: TrackStatus; label: string; detail: string; icon: string };

export const STAGES: Stage[] = [
  { status: "placed", label: "Order placed", detail: "We have your order and payment details.", icon: "receipt" },
  { status: "verified", label: "Price and stock verified", detail: "We checked the live price and stock with the store.", icon: "shield" },
  { status: "purchased", label: "Bought from {store} in India", detail: "Your items are bought and on their way to our hub.", icon: "bag" },
  { status: "india_hub", label: "At Skydrop Kolkata hub", detail: "Checked, packed and labelled for Bangladesh.", icon: "package" },
  { status: "in_transit", label: "Crossing the border", detail: "Customs clearance with Skydrop, no paperwork for you.", icon: "plane" },
  { status: "dhaka_hub", label: "At Skydrop Dhaka hub", detail: "Sorted for delivery to your district.", icon: "pin" },
  { status: "out_for_delivery", label: "Out for delivery", detail: "A Skydrop rider is bringing it to your door.", icon: "truck" },
  { status: "delivered", label: "Delivered", detail: "Handed over at your address. Enjoy!", icon: "check" },
];

/** Demo pace: one stage every 2 minutes after the order is placed. */
export const DEMO_STAGE_MS = 2 * 60 * 1000;

export function stageIndex(status: OrderStatus): number {
  return STAGES.findIndex((s) => s.status === status);
}

/**
 * Current status of an order. DEMO ONLY: progress is simulated from order.createdAt
 * (one stage every DEMO_STAGE_MS). Production reads the real status from the Skydrop
 * tracking API / webhooks and this function simply returns order.status.
 */
export function currentStatus(order: Pick<Order, "status" | "createdAt">, now: number): OrderStatus {
  if (order.status === "cancelled") return "cancelled";
  const real = Math.max(0, stageIndex(order.status));
  const created = Date.parse(order.createdAt);
  const elapsed = Number.isFinite(created) && now > 0 ? Math.max(0, now - created) : 0;
  const simulated = Math.min(STAGES.length - 1, Math.floor(elapsed / DEMO_STAGE_MS));
  return STAGES[Math.max(real, simulated)].status;
}

/** 0..1 progress along the tracking stages (cancelled = 0). */
export function progressOf(status: OrderStatus) {
  const i = stageIndex(status);
  return i <= 0 ? 0 : i / (STAGES.length - 1);
}

/** Orders can be cancelled only before we buy the items in India. */
export function canCancel(status: OrderStatus) {
  const i = stageIndex(status);
  return i >= 0 && i < stageIndex("purchased");
}

export function isInProgress(status: OrderStatus) {
  return status !== "cancelled" && status !== "delivered";
}

export function storesOf(order: Pick<Order, "items">): MarketId[] {
  return Array.from(new Set(order.items.map((i) => i.market)));
}

/** Stage label with the real source store(s) filled in, e.g. "Bought from amazon.in in India". */
export function stageLabel(stage: Stage, order?: Pick<Order, "items">) {
  if (!stage.label.includes("{store}")) return stage.label;
  const names = order ? storesOf(order).map((m) => STORES[m].source) : [];
  const store = names.length ? names.join(" and ") : "the store";
  return stage.label.replace("{store}", store);
}

/** Short status text for chips. */
export function statusText(status: OrderStatus) {
  if (status === "cancelled") return "Cancelled";
  const short: Record<TrackStatus, string> = {
    placed: "Placed",
    verified: "Verified",
    purchased: "Bought in India",
    india_hub: "At Kolkata hub",
    in_transit: "Crossing border",
    dhaka_hub: "At Dhaka hub",
    out_for_delivery: "Out for delivery",
    delivered: "Delivered",
  };
  return short[status];
}

export function statusIcon(status: OrderStatus) {
  if (status === "cancelled") return "x";
  return STAGES[stageIndex(status)]?.icon ?? "package";
}

/** Estimated delivery date range from the order date. */
export function etaRange(order: Pick<Order, "createdAt">) {
  const { min, max } = deliveryWindow();
  const base = Date.parse(order.createdAt);
  const day = 24 * 60 * 60 * 1000;
  const fmt = (t: number) => new Date(t).toLocaleDateString("en-GB", { day: "numeric", month: "short" });
  const from = new Date(base + min * day);
  const to = new Date(base + max * day);
  if (from.getMonth() === to.getMonth()) return `${from.getDate()}-${fmt(to.getTime())}`;
  return `${fmt(from.getTime())} - ${fmt(to.getTime())}`;
}
