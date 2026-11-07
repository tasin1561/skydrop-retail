// Fulfilment API for the app: tracking route, returns (docs/API.md "Fulfilment").
// Demo mode computes the route locally from the order status.
import { http, isDemo } from "@/api/client";
import type { Order, OrderStatus } from "@/lib/types";
import type { MsgKey } from "@/i18n/core";

export type GeoPoint = { id: string; label: string; short: string; lat: number; lng: number };
export type Leg = { id: string; label: string; state: "done" | "current" | "next" };
export type Tracking = {
  orderId: string;
  status: OrderStatus;
  points: GeoPoint[];
  legs: Leg[];
  currentLeg: number | null;
  progress: number;
  eta: { from: string; to: string } | null;
  lastEvent: { status: string | null; note: string | null; at: string } | null;
  rider: { lat: number; lng: number; at: string } | null;
  pod: { photoUrl: string | null; signatureUrl: string | null; receivedBy: string | null; at: string } | null;
  unboxingHold: boolean;
};

export type ReturnReason = "damaged" | "wrong_item" | "not_as_described" | "size_issue" | "changed_mind";
export type Resolution = "refund" | "replacement" | "store_credit";
export type ReturnCase = {
  id: number;
  stage: "india" | "delivered";
  reason: ReturnReason;
  reasonLabel: string;
  resolution: Resolution;
  status: string;
  statusLabel: string;
  timeline: { status: string; at: string; note: string | null }[];
};
export type Eligibility = {
  eligible: boolean;
  reason: string | null;
  windowDays: number;
  reasons: ReturnReason[];
  resolutions: Resolution[];
  items: { id: number; title: string; variant: string | null; qty: number; returnableQty: number; unitBdt: number }[];
};

/** Dictionary keys: show them with t(). */
export const REASON_LABELS: Record<ReturnReason, MsgKey> = {
  damaged: "rr.damaged",
  wrong_item: "rr.wrong_item",
  not_as_described: "rr.not_as_described",
  size_issue: "rr.size_issue",
  changed_mind: "rr.changed_mind",
};
export const RESOLUTION_LABELS: Record<Resolution, MsgKey> = { refund: "rs.refund", replacement: "rs.replacement", store_credit: "rs.store_credit" };

const HUBS: GeoPoint[] = [
  { id: "kolkata", label: "Skydrop Kolkata hub", short: "Kolkata", lat: 22.5726, lng: 88.3639 },
  { id: "border", label: "Petrapole / Benapole border", short: "Benapole", lat: 23.0417, lng: 88.8838 },
  { id: "dhaka", label: "Skydrop Dhaka hub", short: "Dhaka hub", lat: 23.8103, lng: 90.4125 },
];
// Division centres (the API returns the exact district point; this is only the demo fallback).
const DIVISIONS: Record<string, [number, number]> = {
  Dhaka: [23.81, 90.41], Chattogram: [22.36, 91.78], Rajshahi: [24.37, 88.6], Khulna: [22.85, 89.54],
  Barishal: [22.7, 90.35], Sylhet: [24.89, 91.87], Rangpur: [25.74, 89.25], Mymensingh: [24.75, 90.41],
};
const PROGRESS: Partial<Record<OrderStatus, [number, number]>> = {
  india_hub: [0, 0], in_transit: [0.2, 0], dhaka_hub: [2 / 3, 2], out_for_delivery: [0.83, 2], delivered: [1, 2],
};

export function localTracking(order: Order, status: OrderStatus): Tracking {
  const [lat, lng] = DIVISIONS[order.address?.division ?? "Dhaka"] ?? DIVISIONS.Dhaka;
  const d = order.address?.district ?? "Dhaka";
  const points = [...HUBS, { id: "customer", label: d, short: d, lat, lng }];
  const [progress, leg] = PROGRESS[status] ?? [0, null];
  const labels = ["Kolkata → Petrapole", "Benapole → Dhaka hub", `Dhaka hub → ${d}`];
  return {
    orderId: order.id,
    status,
    points,
    legs: labels.map((label, i) => ({
      id: String(i),
      label,
      state: status === "delivered" || (leg !== null && i < leg) ? "done" : leg === i && status !== "india_hub" && status !== "dhaka_hub" ? "current" : "next",
    })),
    currentLeg: leg,
    progress,
    eta: null,
    lastEvent: order.timeline?.length ? { ...order.timeline[order.timeline.length - 1], note: order.timeline[order.timeline.length - 1].note ?? null } : null,
    rider: null,
    pod: null,
    unboxingHold: false,
  };
}

export const fulfilment = {
  /** GET /orders/{id}/tracking; null when unavailable (feature off, demo). */
  tracking: async (id: string): Promise<Tracking | null> => {
    if (isDemo()) return null;
    try {
      return (await http<{ tracking: Tracking }>("GET", `/orders/${encodeURIComponent(id)}/tracking`)).tracking;
    } catch {
      return null;
    }
  },
  returns: (id: string) => http<{ eligibility: Eligibility; items: ReturnCase[] }>("GET", `/orders/${encodeURIComponent(id)}/returns`),
  createReturn: (id: string, body: { items: { orderItemId: number; qty: number }[]; reason: ReturnReason; note?: string; photos: number[]; resolution: Resolution }) =>
    http<{ return: ReturnCase }>("POST", `/orders/${encodeURIComponent(id)}/returns`, { body }).then((r) => r.return),
};
