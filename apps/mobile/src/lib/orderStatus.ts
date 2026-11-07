import type { MarketId, Order, OrderStatus } from "./types";
import { STORES } from "./markets";
import { deliveryWindow } from "./pricing";
import { hasKey, makeT, type MsgKey, type Translator } from "@/i18n/core";

// Order tracking stages, in order. In production the real status comes from the Skydrop API
// (and its webhooks pushing hub scans); the demo below only SIMULATES progress so the
// tracking UI can be seen moving.

export type TrackStatus = Exclude<OrderStatus, "cancelled">;
/** label/detail are dictionary keys: show them with stageLabel() / stageDetail(). */
export type Stage = { status: TrackStatus; label: MsgKey; detail: MsgKey; icon: string };

export const STAGES: Stage[] = [
  { status: "placed", label: "st.placed.label", detail: "st.placed.detail", icon: "receipt" },
  { status: "verified", label: "st.verified.label", detail: "st.verified.detail", icon: "shield" },
  { status: "purchased", label: "st.purchased.label", detail: "st.purchased.detail", icon: "bag" },
  { status: "india_hub", label: "st.india_hub.label", detail: "st.india_hub.detail", icon: "package" },
  { status: "in_transit", label: "st.in_transit.label", detail: "st.in_transit.detail", icon: "plane" },
  { status: "dhaka_hub", label: "st.dhaka_hub.label", detail: "st.dhaka_hub.detail", icon: "pin" },
  { status: "out_for_delivery", label: "st.out_for_delivery.label", detail: "st.out_for_delivery.detail", icon: "truck" },
  { status: "delivered", label: "st.delivered.label", detail: "st.delivered.detail", icon: "check" },
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
export function stageLabel(stage: Stage, order?: Pick<Order, "items">, tr: Translator = makeT("en")) {
  const names = order ? storesOf(order).map((m) => STORES[m].source) : [];
  const store = names.length ? names.join(tr.t("st.and")) : tr.t("st.theStore");
  return tr.t(stage.label, { store });
}

export function stageDetail(stage: Stage, tr: Translator = makeT("en")) {
  return tr.t(stage.detail);
}

/** Short status text for chips. */
export function statusText(status: OrderStatus, tr: Translator = makeT("en")): string {
  if (status === "cancelled") return tr.t("st.cancelled");
  const k = `st.${status}.short`;
  return hasKey(k) ? tr.t(k) : (status as string);
}

export function statusIcon(status: OrderStatus) {
  if (status === "cancelled") return "x";
  return STAGES[stageIndex(status)]?.icon ?? "package";
}

/** Estimated delivery date range from the order date. */
export function etaRange(order: Pick<Order, "createdAt">, tr: Translator = makeT("en")) {
  const { min, max } = deliveryWindow();
  const base = Date.parse(order.createdAt);
  const day = 24 * 60 * 60 * 1000;
  const fmt = (t: number) => tr.date(t, { day: "numeric", month: "short" });
  const from = new Date(base + min * day);
  const to = new Date(base + max * day);
  if (from.getMonth() === to.getMonth()) return `${tr.digits(from.getDate())}-${fmt(to.getTime())}`;
  return `${fmt(from.getTime())} - ${fmt(to.getTime())}`;
}

/** "paid", "partial", "refund_pending" … in the shopper's language (raw value when unknown). */
export function payStatusText(status: string, t: (k: never, v?: Record<string, string | number>) => string): string {
  const key = ({ paid: "af2.paid", pending: "mx.ps.pending", partial: "mx.ps.partial", failed: "mx.ps.failed", refunded: "mx.ps.refunded", refund_pending: "mx.ps.refund_pending" } as Record<string, string>)[status];
  return key ? t(key as never) : status;
}
