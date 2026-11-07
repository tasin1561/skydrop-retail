"use client";

// The single tracking entry point in the root layout: attribution capture, consent banner, lazy tag loading
// (next/script afterInteractive, only after "Accept all") and a PageView on every client-side route change.
import Script from "next/script";
import { usePathname, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState, useSyncExternalStore } from "react";
import { activateTracking, deactivateTracking, track } from "@/lib/track";
import { captureAttribution } from "@/lib/track/attribution";
import { hasAnyTag, loadTrackingIds } from "@/lib/track/config";
import { getConsent, OPEN_CONSENT_EVENT, subscribeConsent } from "@/lib/track/consent";
import { consentDefaults, TIKTOK_SDK } from "@/lib/track/platforms";
import type { TrackingIds } from "@/lib/track/types";
import { useFeature } from "@/components/features/FeaturesProvider";
import { ConsentBanner } from "./ConsentBanner";

const SSR = "ssr" as const;

export function Tracking() {
  // Admin → Features → "Pixels & server-side tracking": off = no banner, no tags, no attribution capture.
  const enabled = useFeature("tracking");
  const consent = useSyncExternalStore(subscribeConsent, getConsent, () => SSR);
  const [ids, setIds] = useState<TrackingIds | null>(null);
  const [reopened, setReopened] = useState(false);

  useEffect(() => {
    if (!enabled) {
      deactivateTracking(true);
      return;
    }
    captureAttribution();
    consentDefaults();
    const open = () => setReopened(true);
    window.addEventListener(OPEN_CONSENT_EVENT, open);
    return () => window.removeEventListener(OPEN_CONSENT_EVENT, open);
  }, [enabled]);

  useEffect(() => {
    if (!enabled) return;
    if (consent === "essential") {
      deactivateTracking();
      return;
    }
    if (consent !== "all") return;
    let alive = true;
    loadTrackingIds().then((next) => {
      if (!alive) return;
      activateTracking(next);
      if (hasAnyTag(next)) setIds(next);
    });
    return () => {
      alive = false;
    };
  }, [consent, enabled]);

  if (!enabled) return null;
  const tags = consent === "all" ? ids : null;
  return (
    <>
      <Suspense fallback={null}>
        <RouteViews />
      </Suspense>
      {tags?.metaPixelId && <Script id="sr-meta" src="https://connect.facebook.net/en_US/fbevents.js" strategy="afterInteractive" />}
      {tags?.ga4Id && (
        <Script id="sr-ga4" src={`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(tags.ga4Id)}`} strategy="afterInteractive" />
      )}
      {tags?.gtmId && (
        <Script id="sr-gtm" src={`https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(tags.gtmId)}`} strategy="afterInteractive" />
      )}
      {tags?.tiktokPixelId && (
        <Script id="sr-ttq" src={`${TIKTOK_SDK}?sdkid=${encodeURIComponent(tags.tiktokPixelId)}&lib=ttq`} strategy="afterInteractive" />
      )}
      {consent !== SSR && (consent === null || reopened) && <ConsentBanner current={consent} onDone={() => setReopened(false)} />}
    </>
  );
}

/** PageView on first load and on every App Router navigation (path or query change). */
function RouteViews() {
  const pathname = usePathname();
  const search = useSearchParams().toString();
  useEffect(() => {
    // let the new page's <title> settle first
    const t = setTimeout(() => track("PageView"), 0);
    return () => clearTimeout(t);
  }, [pathname, search]);
  return null;
}
