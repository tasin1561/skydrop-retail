// Tag ids: NEXT_PUBLIC_* env first, overridden by the admin (GET /tracking/config) when the API is on.
import { API_URL, apiEnabled } from "@/lib/apiClient";
import type { TrackingIds } from "./types";

const clean = (v: string | null | undefined) => (v && v.trim() ? v.trim() : null);

export const ENV_IDS: TrackingIds = {
  metaPixelId: clean(process.env.NEXT_PUBLIC_META_PIXEL_ID),
  ga4Id: clean(process.env.NEXT_PUBLIC_GA4_ID),
  tiktokPixelId: clean(process.env.NEXT_PUBLIC_TIKTOK_PIXEL_ID),
  gtmId: clean(process.env.NEXT_PUBLIC_GTM_ID),
  server: false,
};

const CACHE = "skydrop-retail:tracking-config";

export function hasAnyTag(ids: TrackingIds) {
  return !!(ids.metaPixelId || ids.ga4Id || ids.tiktokPixelId || ids.gtmId);
}

/** Resolved once per tab session; never blocks render (called after consent). */
export async function loadTrackingIds(): Promise<TrackingIds> {
  if (!apiEnabled) return ENV_IDS;
  try {
    const hit = sessionStorage.getItem(CACHE);
    if (hit) return JSON.parse(hit) as TrackingIds;
  } catch {
    /* ignore */
  }
  try {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), 4000);
    const res = await fetch(`${API_URL}/tracking/config`, { headers: { Accept: "application/json" }, signal: ctrl.signal });
    clearTimeout(timer);
    if (!res.ok) return ENV_IDS;
    const d = (await res.json()) as Partial<TrackingIds>;
    const ids: TrackingIds = {
      metaPixelId: clean(d.metaPixelId) ?? ENV_IDS.metaPixelId,
      ga4Id: clean(d.ga4Id) ?? ENV_IDS.ga4Id,
      tiktokPixelId: clean(d.tiktokPixelId) ?? ENV_IDS.tiktokPixelId,
      gtmId: clean(d.gtmId) ?? ENV_IDS.gtmId,
      server: !!d.server,
    };
    try {
      sessionStorage.setItem(CACHE, JSON.stringify(ids));
    } catch {
      /* ignore */
    }
    return ids;
  } catch {
    return ENV_IDS;
  }
}
