// Queueing stubs for the vendor tags. The real scripts are loaded by <Tracking /> with next/script
// (afterInteractive) and drain these queues, so track() never waits for a network request.
/* eslint-disable @typescript-eslint/no-explicit-any */

declare global {
  interface Window {
    fbq?: any;
    _fbq?: any;
    gtag?: (...args: unknown[]) => void;
    dataLayer?: unknown[];
    ttq?: any;
    TiktokAnalyticsObject?: string;
  }
}

export const TIKTOK_SDK = "https://analytics.tiktok.com/i18n/pixel/events.js";

export function ensureGtag() {
  window.dataLayer = window.dataLayer || [];
  if (!window.gtag) {
    window.gtag = function gtag() {
      // gtag.js only accepts the Arguments object
      // eslint-disable-next-line prefer-rest-params
      window.dataLayer!.push(arguments);
    };
  }
  return window.gtag;
}

/** GA4 Consent Mode v2: everything denied until the shopper accepts. */
export function consentDefaults() {
  ensureGtag()("consent", "default", {
    ad_storage: "denied",
    ad_user_data: "denied",
    ad_personalization: "denied",
    analytics_storage: "denied",
    wait_for_update: 500,
  });
}

export function consentUpdate(granted: boolean) {
  const v = granted ? "granted" : "denied";
  ensureGtag()("consent", "update", { ad_storage: v, ad_user_data: v, ad_personalization: v, analytics_storage: v });
  if (!granted) {
    window.fbq?.("consent", "revoke");
    window.ttq?.revokeConsent?.();
  } else {
    window.fbq?.("consent", "grant");
    window.ttq?.grantConsent?.();
  }
}

export function installMeta(pixelId: string) {
  if (!window.fbq) {
    const n: any = function (...args: unknown[]) {
      // same call shape as Meta's base code (callMethod bound to the fbq function)
      // eslint-disable-next-line prefer-spread
      if (n.callMethod) n.callMethod.apply(n, args);
      else n.queue.push(args);
    };
    n.push = n;
    n.loaded = true;
    n.version = "2.0";
    n.queue = [];
    window.fbq = n;
    window._fbq = n;
  }
  window.fbq("init", pixelId);
}

export function installGa4(id: string) {
  const gtag = ensureGtag();
  gtag("js", new Date());
  gtag("config", id, { send_page_view: false }); // page views are sent by track("PageView") on every route change
}

export function installGtm() {
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({ "gtm.start": Date.now(), event: "gtm.js" });
}

const TTQ_METHODS = ["page", "track", "identify", "instances", "debug", "on", "off", "once", "ready", "alias", "group",
  "enableCookie", "disableCookie", "holdConsent", "revokeConsent", "grantConsent"];

export function installTikTok(pixelId: string) {
  if (window.ttq) return;
  window.TiktokAnalyticsObject = "ttq";
  const ttq: any = [];
  const defer = (t: any, m: string) => {
    t[m] = (...args: unknown[]) => {
      t.push([m, ...args]);
    };
  };
  ttq.methods = TTQ_METHODS;
  ttq.setAndDefer = defer;
  for (const m of TTQ_METHODS) defer(ttq, m);
  ttq.instance = (id: string) => {
    const e = ttq._i[id] || [];
    for (const m of TTQ_METHODS) defer(e, m);
    return e;
  };
  ttq._i = { [pixelId]: Object.assign([], { _u: TIKTOK_SDK }) };
  ttq._t = { [pixelId]: Date.now() };
  ttq._o = { [pixelId]: {} };
  window.ttq = ttq;
}
