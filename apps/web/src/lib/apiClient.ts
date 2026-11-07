// Browser-side client for the Skydrop Retail API (docs/API.md).
// Active only when NEXT_PUBLIC_API_URL is set; otherwise the storefront runs in demo mode.
import type { Address, CartItem, Order, User } from "./types";
import { getAttribution, type OrderAttribution } from "./track/attribution";

export const API_URL = process.env.NEXT_PUBLIC_API_URL?.replace(/\/$/, "") ?? "";
export const apiEnabled = API_URL.length > 0;

let token: string | null = null;
export function setApiToken(t: string | null) {
  token = t;
}
/** Current bearer token (used by the tracking lib for POST /track). */
export function getApiToken() {
  return token;
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public data?: unknown,
  ) {
    super(message);
  }
}

async function call<T>(path: string, init: RequestInit = {}): Promise<T> {
  const res = await fetch(API_URL + path, {
    ...init,
    headers: {
      Accept: "application/json",
      ...(init.body ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(init.headers ?? {}),
    },
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new ApiError(res.status, (data as { message?: string }).message ?? `Request failed (${res.status})`, data);
  return data as T;
}

const post = <T,>(path: string, body: unknown) => call<T>(path, { method: "POST", body: JSON.stringify(body) });

/** Authenticated request helpers for feature modules (e.g. lib/growth.ts). */
export const apiRequest = { call, post };

type ApiAddress = Address;
type ApiOrder = Order & {
  paymentStatus?: string;
  timeline?: { status: string; at: string; note?: string }[];
  invoiceUrl?: string;
  canCancel?: boolean;
  dueOnDelivery?: number;
};
/** In-app notification (GET /notifications). */
export type AppNotification = {
  id: number;
  type: string;
  title: string;
  body: string | null;
  icon: string | null;
  link: string | null;
  image: string | null;
  data: Record<string, unknown>;
  read: boolean;
  createdAt: string;
};
export type NotificationsPage = { items: AppNotification[]; unreadCount: number; page: number; total: number; hasMore: boolean };
export type NotifyPrefs = {
  locale: "en" | "bn";
  marketing: { sms: boolean; email: boolean; push: boolean; inapp: boolean };
  email: string | null;
  emailVerified: boolean;
};
/** Body of a 409 price_changed response from POST /orders. */
export type PriceChange = { productId: string; oldBdt: number; freshBdt: number };

export const api = {
  requestOtp: (phone: string) => post<{ ok: boolean; expiresIn: number; demoCode?: string }>("/auth/otp/request", { phone }),
  verifyOtp: (phone: string, code: string, name?: string, invite?: string) =>
    post<{ token: string; user: User & { id: number }; isNew: boolean }>("/auth/otp/verify", { phone, code, name, invite: invite || undefined }),
  me: () => call<{ user: User }>("/me"),
  logout: () => post<{ ok: boolean }>("/auth/logout", {}),

  addresses: () => call<{ items: ApiAddress[] }>("/addresses"),
  saveAddress: (a: Address) =>
    /^\d+$/.test(a.id)
      ? call<{ address: ApiAddress }>(`/addresses/${a.id}`, { method: "PATCH", body: JSON.stringify(a) })
      : post<{ address: ApiAddress }>("/addresses", a),
  removeAddress: (id: string) => call<unknown>(`/addresses/${id}`, { method: "DELETE" }),

  wishlistAdd: (productId: string) => post<unknown>("/wishlist", { productId }),
  wishlistRemove: (productId: string) => call<unknown>(`/wishlist/${encodeURIComponent(productId)}`, { method: "DELETE" }),
  wishlist: () => call<{ items: { id: string }[] }>("/wishlist"),

  orders: () => call<{ items: ApiOrder[] }>("/orders"),
  order: (id: string) => call<{ order: ApiOrder }>(`/orders/${id}`),
  payOrder: (id: string, returnUrl: string) =>
    post<{ order: ApiOrder; payment: { status: string; redirectUrl?: string } }>(`/orders/${id}/pay`, { returnUrl }),
  cancelOrder: (id: string) => post<{ order: ApiOrder }>(`/orders/${id}/cancel`, {}),
  createOrder: (input: {
    items: CartItem[];
    address: Address;
    payment: string;
    coupon?: string;
    redeemPoints?: number;
    returnUrl: string;
    /** affiliate code captured by RefCapture (last click wins) */
    ref?: string;
    /** marketing attribution (utm, click ids, _fbp/_fbc); defaults to getAttribution() from src/lib/track */
    attribution?: OrderAttribution;
  }) =>
    post<{ order: ApiOrder; payment: { status: string; redirectUrl?: string } }>("/orders", {
      items: input.items.map((i) => ({ productId: i.productId, variant: i.variant, qty: i.qty, unitBdt: i.unitBdt })),
      ...(/^\d+$/.test(input.address.id) ? { addressId: Number(input.address.id) } : { address: input.address }),
      // API ids: bkash | nagad | card | advance50 (+ paymentVia for the advance)
      payment: input.payment === "half" ? "advance50" : input.payment,
      ...(input.payment === "half" ? { paymentVia: "bkash" } : {}),
      coupon: input.coupon || undefined,
      redeemPoints: input.redeemPoints || undefined,
      returnUrl: input.returnUrl,
      ref: input.ref || undefined,
      // defaults to the attribution captured on landing (first + last touch, 30 days)
      attribution: input.attribution ?? (typeof window !== "undefined" ? getAttribution() : undefined),
    }),
  points: () =>
    call<{ balance: number; pending: number; valueBdt: number; history: { type: string; points: number; note: string; at: string; orderId?: string }[] }>(
      "/points",
    ),
  watch: (productId: string, type: "price_drop" | "back_in_stock", targetBdt?: number) =>
    post<unknown>("/watches", { productId, type, targetBdt }),
  requestSupport: (orderId: string, type: string, message: string) =>
    post<unknown>(`/orders/${orderId}/support`, { type, message }),

  // ---- messaging (notification centre, prefs, cart sync, push) ----
  notifications: (page = 1, perPage = 20) =>
    call<NotificationsPage>(`/notifications?page=${page}&perPage=${perPage}`),
  notificationRead: (id: number) => post<{ unreadCount: number }>(`/notifications/${id}/read`, {}),
  notificationsReadAll: () => post<{ unreadCount: number }>("/notifications/read-all", {}),
  notificationDelete: (id: number) => call<{ unreadCount: number }>(`/notifications/${id}`, { method: "DELETE" }),
  notifyPrefs: () => call<NotifyPrefs>("/me/notifications"),
  saveNotifyPrefs: (p: { locale?: "en" | "bn"; marketing?: Partial<NotifyPrefs["marketing"]> }) =>
    call<NotifyPrefs>("/me/notifications", { method: "PATCH", body: JSON.stringify(p) }),
  syncCart: (items: CartItem[]) =>
    call<{ ok: boolean }>("/cart", {
      method: "PUT",
      body: JSON.stringify({
        items: items.map((i) => ({ productId: i.productId, market: i.market, title: i.title, image: i.image, variant: i.variant ?? null, unitBdt: i.unitBdt, qty: i.qty })),
      }),
    }),
  /** Web Push: stored server side; delivery is a documented TODO until minishlink/web-push is installed on the API. */
  pushSubscribe: (sub: PushSubscriptionJSON) => post<{ ok: boolean; delivering: boolean }>("/push/subscribe", sub),
};
