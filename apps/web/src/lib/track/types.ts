export type TrackEvent =
  | "PageView"
  | "ViewContent"
  | "Search"
  | "AddToCart"
  | "AddToWishlist"
  | "InitiateCheckout"
  | "AddShippingInfo"
  | "AddPaymentInfo"
  | "Purchase"
  | "CompleteRegistration"
  | "Lead";

export type TrackItem = {
  id: string;
  title?: string;
  brand?: string;
  category?: string;
  price?: number; // BDT per unit
  qty?: number;
  variant?: string;
};

export type TrackPayload = {
  items?: TrackItem[];
  value?: number; // BDT
  orderId?: string;
  search?: string;
  method?: string; // sign-up method / payment type
  coupon?: string;
  shipping?: number;
  /** Force an event id (Purchase uses purchase_<orderId> on both browser and server). */
  eventId?: string;
};

/** Public tag ids (env, optionally overridden by GET /tracking/config). */
export type TrackingIds = {
  metaPixelId: string | null;
  ga4Id: string | null;
  tiktokPixelId: string | null;
  gtmId: string | null;
  /** The API has a server-side driver on, so mirror events to POST /track. */
  server: boolean;
};
