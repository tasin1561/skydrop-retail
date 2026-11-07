// Fulfilment module: tracking map, paid unboxing, returns (docs/API.md "Fulfilment").
// With the API off (demo mode) the tracking route is computed locally from the order status.
import { API_URL, apiEnabled, apiRequest, getApiToken, ApiError } from "./apiClient";
import type { Order, OrderStatus } from "./types";
import type { MsgKey } from "@/i18n/core";

/* ---------------- types ---------------- */

export type GeoPoint = { id: "kolkata" | "border" | "dhaka" | "customer"; label: string; short: string; lat: number; lng: number };
export type Leg = { id: string; from: string; to: string; label: string; state: "done" | "current" | "next" };
export type TrackEvent = {
  type: "status" | "scan";
  status: string | null;
  event?: string;
  note: string | null;
  at: string;
  source?: string;
  lat?: number | null;
  lng?: number | null;
};
export type Pod = { photoUrl: string | null; signatureUrl: string | null; receivedBy: string | null; at: string };
export type Tracking = {
  orderId: string;
  status: OrderStatus;
  trackingCode: string;
  points: GeoPoint[];
  legs: Leg[];
  currentLeg: number | null;
  stage: string;
  progress: number;
  position: { lat: number; lng: number } | null;
  eta: { from: string; to: string } | null;
  lastEvent: TrackEvent | null;
  events: TrackEvent[];
  rider: { lat: number; lng: number; at: string } | null;
  liveMap: boolean;
  pod: Pod | null;
  unboxingHold: boolean;
};

export type UnboxingStatus = "awaiting_customer_approval" | "approved" | "auto_approved" | "problem" | null;
export type ProblemReason = "damaged" | "wrong_item" | "not_as_described" | "size_issue";
export type UnboxingItem = {
  id: number;
  title: string;
  variant: string | null;
  qty: number;
  image: string | null;
  market: string;
  condition: "ok" | "damaged" | "wrong_item" | null;
  note: string | null;
  photos: string[];
  video: string | null;
};
export type Unboxing = {
  orderId: string;
  selected: boolean;
  feeBdt: number;
  status: UnboxingStatus;
  requestedAt: string | null;
  autoApproveAt: string | null;
  decidedAt: string | null;
  reasons: ProblemReason[];
  items: UnboxingItem[];
};

export type ReturnReason = "damaged" | "wrong_item" | "not_as_described" | "size_issue" | "changed_mind";
export type Resolution = "refund" | "replacement" | "store_credit";
export type ReturnStatus = "requested" | "approved" | "rejected" | "pickup_scheduled" | "received" | "refunded" | "replaced" | "credited";
export type ReturnCase = {
  id: number;
  orderId: string;
  stage: "india" | "delivered";
  items: { orderItemId: number; qty: number }[];
  reason: ReturnReason;
  reasonLabel: string;
  note: string | null;
  photos: string[];
  resolution: Resolution;
  status: ReturnStatus;
  statusLabel: string;
  refundBdt: number;
  replacementOrderId: string | null;
  pickupAt: string | null;
  createdAt: string;
  timeline: { status: ReturnStatus; at: string; note: string | null }[];
};
export type ReturnableItem = {
  id: number;
  productId: string;
  title: string;
  variant: string | null;
  image: string | null;
  market: string;
  qty: number;
  returnableQty: number;
  unitBdt: number;
};
export type Eligibility = {
  eligible: boolean;
  reason: "not_delivered" | "window_closed" | "already_requested" | "disabled" | null;
  windowDays: number;
  windowEndsAt: string | null;
  reasons: ReturnReason[];
  resolutions: Resolution[];
  items: ReturnableItem[];
};
export type Upload = { id: number; url: string; mime: string; size: number };

/** Order fields added by the fulfilment module (GET /orders/{id}). */
export type FulfilmentOrder = Order & {
  unboxing?: boolean;
  unboxingFee?: number;
  unboxingStatus?: UnboxingStatus;
  replacementOf?: string | null;
  deliveredAt?: string | null;
};

// Labels are dictionary keys (show them with t()).
export const REASON_LABELS: Record<ReturnReason, MsgKey> = {
  damaged: "rr.damaged",
  wrong_item: "rr.wrong_item",
  not_as_described: "rr.not_as_described",
  size_issue: "rr.size_issue",
  changed_mind: "rr.changed_mind",
};
export const RESOLUTION_LABELS: Record<Resolution, { label: MsgKey; detail: MsgKey }> = {
  refund: { label: "rs.refund", detail: "rs.refundD" },
  replacement: { label: "rs.replacement", detail: "rs.replacementD" },
  store_credit: { label: "rs.store_credit", detail: "rs.store_creditD" },
};
export const RETURN_STEPS: ReturnStatus[] = ["requested", "approved", "pickup_scheduled", "received"];
export const RETURN_STATUS_LABELS: Record<ReturnStatus, MsgKey> = {
  requested: "rst.requested",
  approved: "rst.approved",
  rejected: "rst.rejected",
  pickup_scheduled: "rst.pickup_scheduled",
  received: "rst.received",
  refunded: "rst.refunded",
  replaced: "rst.replaced",
  credited: "rst.credited",
};

/** Default paid-unboxing fee per store shipment (server setting; the quote from the API is authoritative). */
export const UNBOXING_FEE_BDT = 150;
export const RETURN_WINDOW_DAYS = 7;
export const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

/* ---------------- API ---------------- */

const { call, post } = apiRequest;

export const fulfilmentApi = {
  tracking: (id: string) => call<{ tracking: Tracking }>(`/orders/${encodeURIComponent(id)}/tracking`).then((r) => r.tracking),
  unboxing: (id: string) => call<{ unboxing: Unboxing }>(`/orders/${encodeURIComponent(id)}/unboxing`).then((r) => r.unboxing),
  approveUnboxing: (id: string) => post<{ unboxing: Unboxing }>(`/orders/${encodeURIComponent(id)}/unboxing/approve`, {}).then((r) => r.unboxing),
  reportUnboxing: (id: string, reason: ProblemReason, note?: string) =>
    post<{ unboxing: Unboxing; return: ReturnCase | null }>(`/orders/${encodeURIComponent(id)}/unboxing/problem`, { reason, note: note || undefined }),
  returns: (id: string) => call<{ eligibility: Eligibility; items: ReturnCase[] }>(`/orders/${encodeURIComponent(id)}/returns`),
  createReturn: (
    id: string,
    body: { items: { orderItemId: number; qty: number }[]; reason: ReturnReason; note?: string; photos: number[]; resolution: Resolution },
  ) => post<{ return: ReturnCase }>(`/orders/${encodeURIComponent(id)}/returns`, body).then((r) => r.return),
  payDifference: (id: string) =>
    post<{ payment: { status: string; redirectUrl?: string } }>(`/orders/${encodeURIComponent(id)}/pay-difference`, {}),
  /** POST /uploads (multipart, image only, 5 MB). Returns a signed URL for previews. */
  upload: async (file: File): Promise<Upload> => {
    const fd = new FormData();
    fd.append("file", file);
    const token = getApiToken();
    const res = await fetch(API_URL + "/uploads", {
      method: "POST",
      body: fd,
      headers: { Accept: "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    });
    const data = (await res.json().catch(() => ({}))) as { upload?: Upload; message?: string };
    if (!res.ok || !data.upload) throw new ApiError(res.status, data.message ?? "Upload failed", data);
    return data.upload;
  },
};

/* ---------------- route geometry (shared by the SVG map and the demo fallback) ---------------- */

export const HUBS: GeoPoint[] = [
  { id: "kolkata", label: "Skydrop Kolkata hub", short: "Kolkata", lat: 22.5726, lng: 88.3639 },
  { id: "border", label: "Petrapole / Benapole border", short: "Benapole", lat: 23.0417, lng: 88.8838 },
  { id: "dhaka", label: "Skydrop Dhaka hub", short: "Dhaka hub", lat: 23.8103, lng: 90.4125 },
];

/** Approximate district centres; same table as App\Services\Fulfilment\OrderTracking::DISTRICTS. */
export const DISTRICTS: Record<string, [number, number]> = {
  Dhaka: [23.81, 90.41], Gazipur: [24.0, 90.42], Narayanganj: [23.62, 90.5], Narsingdi: [23.92, 90.72], Manikganj: [23.86, 90.0],
  Munshiganj: [23.54, 90.53], Tangail: [24.25, 89.92], Faridpur: [23.61, 89.84], Gopalganj: [23.01, 89.83], Kishoreganj: [24.44, 90.78],
  Madaripur: [23.17, 90.2], Rajbari: [23.76, 89.64], Shariatpur: [23.24, 90.43],
  Chattogram: [22.36, 91.78], "Cox's Bazar": [21.43, 92.01], Cumilla: [23.46, 91.18], Feni: [23.02, 91.4], Noakhali: [22.87, 91.1],
  Lakshmipur: [22.94, 90.83], Chandpur: [23.23, 90.67], Brahmanbaria: [23.96, 91.11], Rangamati: [22.65, 92.18], Khagrachhari: [23.12, 91.98],
  Bandarban: [22.2, 92.22],
  Rajshahi: [24.37, 88.6], Bogura: [24.85, 89.37], Pabna: [24.01, 89.24], Sirajganj: [24.45, 89.7], Natore: [24.41, 89.0],
  Naogaon: [24.81, 88.94], Chapainawabganj: [24.6, 88.27], Joypurhat: [25.1, 89.02],
  Khulna: [22.85, 89.54], Jashore: [23.17, 89.21], Satkhira: [22.72, 89.07], Bagerhat: [22.65, 89.79], Kushtia: [23.9, 89.12],
  Jhenaidah: [23.54, 89.17], Magura: [23.49, 89.42], Narail: [23.17, 89.51], Chuadanga: [23.64, 88.84], Meherpur: [23.76, 88.63],
  Barishal: [22.7, 90.35], Patuakhali: [22.36, 90.33], Bhola: [22.69, 90.65], Pirojpur: [22.58, 89.97], Jhalokati: [22.64, 90.2],
  Barguna: [22.15, 90.12],
  Sylhet: [24.89, 91.87], Moulvibazar: [24.48, 91.77], Habiganj: [24.37, 91.42], Sunamganj: [25.07, 91.4],
  Rangpur: [25.74, 89.25], Dinajpur: [25.63, 88.64], Kurigram: [25.81, 89.64], Gaibandha: [25.33, 89.53], Nilphamari: [25.93, 88.86],
  Lalmonirhat: [25.92, 89.45], Thakurgaon: [26.03, 88.46], Panchagarh: [26.33, 88.55],
  Mymensingh: [24.75, 90.41], Jamalpur: [24.92, 89.95], Netrokona: [24.88, 90.73], Sherpur: [25.02, 90.02],
};

export function routePoints(order: Pick<Order, "address">): GeoPoint[] {
  const d = order.address?.district || "Dhaka";
  const [lat, lng] = DISTRICTS[d] ?? DISTRICTS.Dhaka;
  return [...HUBS, { id: "customer", label: [order.address?.area, d].filter(Boolean).join(", "), short: d, lat, lng }];
}

/** Point at t (0..1) along the polyline, equal weight per leg (matches OrderTracking::along). */
export function along(points: { lat: number; lng: number }[], t: number) {
  const legs = points.length - 1;
  const x = Math.max(0, Math.min(1, t)) * legs;
  const i = Math.min(legs - 1, Math.floor(x));
  const f = x - i;
  const a = points[i];
  const b = points[i + 1];
  return { lat: a.lat + (b.lat - a.lat) * f, lng: a.lng + (b.lng - a.lng) * f };
}

const PROGRESS: Partial<Record<OrderStatus, { progress: number; leg: number | null; stage: string }>> = {
  india_hub: { progress: 0, leg: 0, stage: "kolkata" },
  in_transit: { progress: 0.2, leg: 0, stage: "to_border" },
  dhaka_hub: { progress: 2 / 3, leg: 2, stage: "dhaka" },
  out_for_delivery: { progress: 0.83, leg: 2, stage: "to_customer" },
  delivered: { progress: 1, leg: 2, stage: "customer" },
};
const ETA_DAYS: Partial<Record<OrderStatus, [number, number]>> = {
  // placed matches the 7-12 day promise shown at checkout and on the order (deliveryWindow)
  placed: [7, 12], verified: [7, 11], purchased: [5, 9], india_hub: [4, 7], in_transit: [2, 5], dhaka_hub: [1, 2], out_for_delivery: [0, 0],
};

/** Demo-mode tracking (no API): same shape as GET /orders/{id}/tracking. */
export function localTracking(order: Order, status: OrderStatus, now: number): Tracking {
  const points = routePoints(order);
  const p = PROGRESS[status] ?? { progress: 0, leg: null, stage: status === "cancelled" ? "cancelled" : "india" };
  const legs: Leg[] = [
    { id: "india", from: "kolkata", to: "border", label: "Kolkata → Petrapole", state: "next" },
    { id: "border", from: "border", to: "dhaka", label: "Benapole → Dhaka hub", state: "next" },
    { id: "last_mile", from: "dhaka", to: "customer", label: `Dhaka hub → ${points[3].short}`, state: "next" },
  ].map((l, i) => ({
    ...l,
    state:
      status === "delivered" || (p.leg !== null && i < p.leg)
        ? ("done" as const)
        : p.leg === i && status !== "india_hub" && status !== "dhaka_hub"
          ? ("current" as const)
          : ("next" as const),
  }));
  const day = 86_400_000;
  const eta = ETA_DAYS[status];
  const iso = (t: number) => new Date(t).toISOString().slice(0, 10);
  const events: TrackEvent[] = (order.timeline ?? []).map((e) => ({ type: "status", status: e.status, note: e.note ?? null, at: e.at }));
  return {
    orderId: order.id,
    status,
    trackingCode: order.trackingCode,
    points,
    legs,
    currentLeg: p.leg,
    stage: p.stage,
    progress: p.progress,
    position: status === "cancelled" ? null : along(points, p.progress),
    eta: eta ? { from: iso(now + eta[0] * day), to: iso(now + eta[1] * day) } : null,
    lastEvent: events.at(-1) ?? null,
    events,
    rider: null,
    liveMap: false,
    pod: null,
    unboxingHold: false,
  };
}

export { apiEnabled };
