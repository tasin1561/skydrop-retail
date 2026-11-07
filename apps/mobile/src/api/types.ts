// Request/response shapes from docs/API.md (v1).
import type { Address, MarketId, Order, Product, StoreMode, User } from "@/lib/types";

export type Sort = "relevance" | "price_asc" | "price_desc" | "rating" | "discount";

export type SearchParams = {
  q?: string;
  store?: StoreMode;
  category?: string;
  sort?: Sort;
  min?: number;
  max?: number;
  rating?: number;
  fast?: boolean;
  page?: number;
  perPage?: number;
};

export type SearchResponse = {
  status: "ready" | "pending";
  jobId?: string;
  total: number;
  page: number;
  pages: number;
  items: Product[];
};

export type Suggestion = { id: string; title: string; market: MarketId };
export type Marketplace = { id: MarketId; name: string; source: string; enabled: boolean };
export type ProductResponse = { status: "ready" | "pending"; jobId?: string; product: Product };
export type Job = { id: string; status: "queued" | "running" | "done" | "failed"; type: string; result?: unknown; error?: string };

/** weightKg is app-side only (demo totals); the server derives weight itself. */
export type CheckoutItem = { productId: string; variant?: string; qty: number; unitBdt: number; weightKg?: number };

export type VerifyResult = {
  productId: string;
  ok: boolean;
  freshBdt?: number;
  changed?: boolean;
  deliverable?: boolean;
  reason?: string;
};
export type VerifyResponse = { ok: boolean; checkedAt: string; results: VerifyResult[] };

export type QuoteResponse = {
  groups: { market: MarketId; subtotal: number; shipping: number }[];
  subtotal: number;
  shipping: number;
  discount: number;
  total: number;
  coupon?: string;
};

export type PaymentMethod = "bkash" | "nagad" | "card" | "advance50";

export type OrderRequest = {
  items: CheckoutItem[];
  addressId: string;
  payment: PaymentMethod;
  coupon?: string;
  redeemPoints?: number;
  /** Where the payment gateway sends the shopper back (deep link into the app). */
  returnUrl?: string;
};
export type OrderResponse = { order: Order; payment: { status: "pending" | "redirect" | "paid"; redirectUrl?: string } };

export type OtpRequestResponse = { ok: true; expiresIn: number; demoCode?: string };
export type OtpVerifyResponse = { token: string; user: User; isNew: boolean };

export type ApiErrorBody = { message: string; errors?: Record<string, string[]> };
export type { Address };

export type PointsEntryType = "earn" | "pending" | "redeem" | "bonus" | "refund" | "expire" | "adjust";
export type PointsResponse = {
  balance: number;
  pending: number;
  valueBdt?: number;
  history: { type: PointsEntryType; points: number; note: string; at: string; orderId?: string }[];
};
export type WatchType = "price_drop" | "back_in_stock";
