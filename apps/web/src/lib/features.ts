// Feature switches controlled from Admin → System → Features (GET /api/v1/features).
// Server components: `await getFeatures()`; client components: `useFeature("affiliate")`.
export type FeatureKey =
  | "store.amazon" | "store.flipkart" | "store.myntra" | "paste_link" | "live_fetch" | "offers" | "price_history"
  | "compare_stores" | "bundles" | "group_orders" | "preorders" | "waitlist" | "product_requests" | "reviews"
  | "flash_sales" | "banners" | "restrictions" | "ai_assistant" | "bangla"
  | "pay.bkash" | "pay.nagad" | "pay.card" | "pay.advance50" | "pay.emi" | "coupons" | "points" | "wallet"
  | "gift_cards" | "membership" | "unboxing" | "returns" | "live_tracking_map" | "supplier_price_alerts" | "fraud_check"
  | "affiliate" | "referral" | "share" | "tracking" | "feeds" | "sms" | "email" | "push" | "in_app_notifications"
  | "abandoned_cart" | "marketing_messages" | "support_chat" | "signups" | "checkout" | "mobile_app";

export type Features = Partial<Record<FeatureKey, boolean>>;

/** Demo-mode defaults (no API): everything on except paid/opt-in features. Mirrors App\Support\Features::REGISTRY. */
export const DEFAULT_FEATURES: Features = {
  ai_assistant: true, // demo uses a local responder, so it costs nothing
  "pay.emi": false,
  membership: false,
};

export function isOn(f: Features, key: FeatureKey) {
  return f[key] ?? DEFAULT_FEATURES[key] ?? true;
}

/** Storefront maintenance (Admin → System → Settings). Web shows a full-page screen; the app shows it on start. */
export type Maintenance = { on: boolean; message: string; since?: string | null };

/** GET /api/v1/features */
export type FeaturesResponse = { features: Features; maintenance?: Maintenance };

export const NO_MAINTENANCE: Maintenance = { on: false, message: "" };

/** Full GET /features response (features + maintenance). Fetches are deduped and revalidated every 60 s by Next. */
export async function getFeaturesResponse(): Promise<FeaturesResponse> {
  const api = process.env.API_URL?.replace(/\/$/, "");
  if (!api) return { features: DEFAULT_FEATURES, maintenance: NO_MAINTENANCE };
  try {
    const res = await fetch(api + "/features", {
      headers: { Accept: "application/json", ...(process.env.INTERNAL_API_KEY ? { "X-Internal-Key": process.env.INTERNAL_API_KEY } : {}) },
      next: { revalidate: 60 },
    });
    if (!res.ok) return { features: DEFAULT_FEATURES, maintenance: NO_MAINTENANCE };
    const body = (await res.json()) as FeaturesResponse;
    return { features: body.features ?? DEFAULT_FEATURES, maintenance: body.maintenance ?? NO_MAINTENANCE };
  } catch {
    return { features: DEFAULT_FEATURES, maintenance: NO_MAINTENANCE };
  }
}

export async function getFeatures(): Promise<Features> {
  return (await getFeaturesResponse()).features;
}

export async function getMaintenance(): Promise<Maintenance> {
  return (await getFeaturesResponse()).maintenance ?? NO_MAINTENANCE;
}
