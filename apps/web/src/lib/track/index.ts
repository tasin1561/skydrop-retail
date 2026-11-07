// track(event, payload): one call fans out to Meta Pixel, GA4, TikTok and window.dataLayer (GTM), and mirrors
// server-relevant events to POST /track with the same event_id so Meta / TikTok dedupe browser + server.
// Nothing is sent before the shopper accepts cookies (events wait in a small queue); "Essential only" drops them.
import { API_URL, apiEnabled, getApiToken } from "@/lib/apiClient";
import { clickIds } from "./attribution";
import { hasAnyTag } from "./config";
import { getConsent } from "./consent";
import { consentUpdate, installGa4, installGtm, installMeta, installTikTok } from "./platforms";
import type { TrackEvent, TrackingIds, TrackItem, TrackPayload } from "./types";

export type { TrackEvent, TrackItem, TrackPayload, TrackingIds } from "./types";

const META_STANDARD = new Set<TrackEvent>(["PageView", "ViewContent", "Search", "AddToCart", "AddToWishlist", "InitiateCheckout",
  "AddPaymentInfo", "Purchase", "CompleteRegistration", "Lead"]);

const GA4: Record<TrackEvent, string> = {
  PageView: "page_view",
  ViewContent: "view_item",
  Search: "search",
  AddToCart: "add_to_cart",
  AddToWishlist: "add_to_wishlist",
  InitiateCheckout: "begin_checkout",
  AddShippingInfo: "add_shipping_info",
  AddPaymentInfo: "add_payment_info",
  Purchase: "purchase",
  CompleteRegistration: "sign_up",
  Lead: "generate_lead",
};

const TIKTOK: Partial<Record<TrackEvent, string>> = {
  ViewContent: "ViewContent",
  Search: "Search",
  AddToCart: "AddToCart",
  AddToWishlist: "AddToWishlist",
  InitiateCheckout: "InitiateCheckout",
  AddPaymentInfo: "AddPaymentInfo",
  Purchase: "CompletePayment",
  CompleteRegistration: "CompleteRegistration",
  Lead: "SubmitForm",
};

/** Sent to POST /track too. Purchase is sent by the API itself (order observer); PageView stays browser-only. */
const SERVER_EVENTS = new Set<TrackEvent>(["ViewContent", "Search", "AddToCart", "AddToWishlist", "InitiateCheckout",
  "AddShippingInfo", "AddPaymentInfo", "CompleteRegistration", "Lead"]);

let ids: TrackingIds | null = null;
let pending: { event: TrackEvent; payload: TrackPayload; eventId: string; url: string; referrer: string }[] = [];

/** Shared by the browser Purchase and the server Purchase so the platforms count it once. */
export const purchaseEventId = (orderId: string) => `purchase_${orderId.toUpperCase()}`;

export function newEventId(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) return crypto.randomUUID();
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    return (c === "x" ? r : (r & 0x3) | 0x8).toString(16);
  });
}

/** Called by <Tracking /> once consent is "all" and the ids are known: installs the tags and flushes queued events. */
export function activateTracking(next: TrackingIds) {
  if (typeof window === "undefined") return;
  if (!ids) {
    if (next.ga4Id || next.gtmId) consentUpdate(true);
    if (next.metaPixelId) installMeta(next.metaPixelId);
    if (next.ga4Id) installGa4(next.ga4Id);
    if (next.gtmId) installGtm();
    if (next.tiktokPixelId) installTikTok(next.tiktokPixelId);
  }
  ids = next;
  const queued = pending;
  pending = [];
  for (const q of queued) send(q.event, q.payload, q.eventId, q.url, q.referrer);
}

/** "Essential only" after having accepted: stop sending and tell the tags. */
let disabled = false;

export function deactivateTracking() {
  pending = [];
  if (ids) consentUpdate(false);
  ids = null;
}

export function track(event: TrackEvent, payload: TrackPayload = {}): string {
  const eventId = payload.eventId ?? (event === "Purchase" && payload.orderId ? purchaseEventId(payload.orderId) : newEventId());
  if (typeof window === "undefined") return eventId;
  const consent = getConsent();
  if (consent === "essential") return eventId;
  const url = window.location.href;
  const referrer = document.referrer;
  if (consent !== "all" || !ids) {
    if (pending.length < 30) pending.push({ event, payload, eventId, url, referrer });
    return eventId;
  }
  send(event, payload, eventId, url, referrer);
  return eventId;
}

const compact = <T extends Record<string, unknown>>(o: T) =>
  Object.fromEntries(Object.entries(o).filter(([, v]) => v !== undefined && v !== null && v !== "")) as Partial<T>;

function valueOf(p: TrackPayload, items: TrackItem[]) {
  if (typeof p.value === "number") return Math.round(p.value);
  const sum = items.reduce((n, i) => n + (i.price ?? 0) * (i.qty ?? 1), 0);
  return sum > 0 ? Math.round(sum) : undefined;
}

function send(event: TrackEvent, p: TrackPayload, eventId: string, url: string, referrer: string) {
  if (!ids || (!hasAnyTag(ids) && !ids.server)) return; // nothing configured: no-op
  const items = p.items ?? [];
  const value = valueOf(p, items);
  const hasMoney = value !== undefined;

  try {
    if (ids.metaPixelId && window.fbq) {
      const params =
        event === "PageView"
          ? {}
          : compact({
              currency: hasMoney || items.length ? "BDT" : undefined,
              value,
              content_ids: items.length ? items.map((i) => i.id) : undefined,
              content_type: items.length ? "product" : undefined,
              contents: items.length ? items.map((i) => compact({ id: i.id, quantity: i.qty ?? 1, item_price: i.price })) : undefined,
              num_items: items.length ? items.reduce((n, i) => n + (i.qty ?? 1), 0) : undefined,
              search_string: p.search,
              order_id: p.orderId,
            });
      window.fbq(META_STANDARD.has(event) ? "track" : "trackCustom", event, params, { eventID: eventId });
    }

    const gaItems = items.map((i) =>
      compact({ item_id: i.id, item_name: i.title, item_brand: i.brand, item_category: i.category, item_variant: i.variant, price: i.price, quantity: i.qty ?? 1 }),
    );
    const gaParams = compact({
      currency: hasMoney || items.length ? "BDT" : undefined,
      value,
      items: gaItems.length ? gaItems : undefined,
      transaction_id: p.orderId,
      search_term: p.search,
      method: event === "CompleteRegistration" ? (p.method ?? "otp") : undefined,
      payment_type: event === "AddPaymentInfo" ? p.method : undefined,
      shipping_tier: event === "AddShippingInfo" ? "skydrop" : undefined,
      shipping: p.shipping,
      coupon: p.coupon,
      event_id: eventId,
    });
    if (ids.ga4Id && window.gtag) {
      window.gtag("event", GA4[event], event === "PageView" ? { page_location: url, page_referrer: referrer, page_title: document.title, event_id: eventId } : gaParams);
    }

    if (ids.tiktokPixelId && window.ttq) {
      if (event === "PageView") window.ttq.page();
      else if (TIKTOK[event])
        window.ttq.track(
          TIKTOK[event],
          compact({
            contents: items.length
              ? items.map((i) => compact({ content_id: i.id, content_type: "product", content_name: i.title, quantity: i.qty ?? 1, price: i.price }))
              : undefined,
            content_type: items.length ? "product" : undefined,
            value,
            currency: hasMoney ? "BDT" : undefined,
            query: p.search,
          }),
          { event_id: eventId },
        );
    }

    // GTM-friendly push (works with or without a container on the page).
    window.dataLayer = window.dataLayer || [];
    if (event !== "PageView") window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push(
      compact({
        event: GA4[event],
        sr_event: event,
        event_id: eventId,
        page_location: event === "PageView" ? url : undefined,
        search_term: p.search,
        ecommerce: event === "PageView" ? undefined : compact({ currency: "BDT", value, transaction_id: p.orderId, items: gaItems }),
      }),
    );
  } catch {
    /* a blocked or broken tag must never break the shop */
  }

  if (ids.server && apiEnabled && SERVER_EVENTS.has(event)) postServer(event, p, eventId, url, referrer, items, value);
}

function postServer(event: TrackEvent, p: TrackPayload, eventId: string, url: string, referrer: string, items: TrackItem[], value?: number) {
  const token = getApiToken();
  const body = compact({
    event,
    eventId,
    url: url.slice(0, 1000),
    referrer: referrer.slice(0, 1000),
    value,
    search: p.search,
    orderId: p.orderId,
    items: items.length
      ? items.slice(0, 50).map((i) => compact({ id: i.id, title: i.title?.slice(0, 500), brand: i.brand, category: i.category, price: i.price, qty: i.qty ?? 1 }))
      : undefined,
    ...clickIds(),
  });
  fetch(`${API_URL}/track`, {
    method: "POST",
    keepalive: true,
    headers: { Accept: "application/json", "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: JSON.stringify(body),
  }).catch(() => {});
}

// ---- helpers for call sites ----

type ProductLike = { id: string; title?: string; brand?: string; category?: string; priceBdt?: number };
type CartLike = { productId: string; title?: string; market?: string; unitBdt: number; qty: number; variant?: string };

export const productItem = (p: ProductLike, qty = 1): TrackItem =>
  compact({ id: p.id, title: p.title, brand: p.brand || undefined, category: p.category, price: p.priceBdt, qty }) as TrackItem;

export const cartItems = (items: CartLike[]): TrackItem[] =>
  items.map((i) => compact({ id: i.productId, title: i.title, category: i.market, price: i.unitBdt, qty: i.qty, variant: i.variant }) as TrackItem);

const stepsSent = new Set<string>();

/** Checkout funnel events, once per cart contents and step. */
export function trackCheckout(event: "InitiateCheckout" | "AddShippingInfo" | "AddPaymentInfo", items: CartLike[], extra: Omit<TrackPayload, "items"> = {}) {
  if (!items.length) return;
  const key = event + ":" + items.map((i) => `${i.productId}x${i.qty}`).join(",") + (extra.method ? ":" + extra.method : "");
  if (stepsSent.has(key)) return;
  stepsSent.add(key);
  track(event, { ...extra, items: cartItems(items) });
}
