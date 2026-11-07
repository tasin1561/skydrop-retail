// Feature switches controlled from Admin → System → Features (GET /api/v1/features).
// Server components: `await getFeatures()`; client components: `useFeature("affiliate")`.
export type FeatureKey =
  | "store.amazon" | "store.flipkart" | "store.bigbasket" | "paste_link" | "live_fetch" | "offers" | "price_history"
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

export async function getFeatures(): Promise<Features> {
  const api = process.env.API_URL?.replace(/\/$/, "");
  if (!api) return DEFAULT_FEATURES;
  try {
    const res = await fetch(api + "/features", {
      headers: { Accept: "application/json", ...(process.env.INTERNAL_API_KEY ? { "X-Internal-Key": process.env.INTERNAL_API_KEY } : {}) },
      next: { revalidate: 60 },
    });
    if (!res.ok) return DEFAULT_FEATURES;
    return ((await res.json()) as { features: Features }).features;
  } catch {
    return DEFAULT_FEATURES;
  }
}
