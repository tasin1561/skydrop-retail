// First-touch / last-touch marketing attribution, kept in localStorage for 30 days and sent with POST /orders.
import { getConsent } from "./consent";

const KEY = "skydrop-retail:attribution";
const TTL = 30 * 24 * 3600 * 1000;
const PARAMS = ["utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content", "fbclid", "gclid", "ttclid"] as const;

export type Touch = Partial<Record<(typeof PARAMS)[number], string>> & { landing: string; referrer?: string; at: string };
type Stored = { first?: Touch; last?: Touch; fbc?: string };

/** Body of `attribution` on POST /orders (see apps/api App\Services\Tracking\Attribution). */
export type OrderAttribution = {
  first?: Touch;
  last?: Touch;
  fbp?: string;
  fbc?: string;
  ttp?: string;
  gaClientId?: string;
  consent?: "all" | "essential";
};

const fresh = (t?: Touch) => (t && Date.now() - Date.parse(t.at) < TTL ? t : undefined);

function read(): Stored {
  try {
    const s = JSON.parse(localStorage.getItem(KEY) ?? "{}") as Stored;
    return { first: fresh(s.first), last: fresh(s.last), fbc: s.fbc && fresh(s.last) ? s.fbc : undefined };
  } catch {
    return {};
  }
}

function cookie(name: string): string | undefined {
  if (typeof document === "undefined") return undefined;
  const m = document.cookie.match(new RegExp("(?:^|; )" + name.replace(/[.$?*|{}()[\]\\/+^]/g, "\\$&") + "=([^;]*)"));
  return m ? decodeURIComponent(m[1]) : undefined;
}

/** Call once per full page load (first landing). Records campaign params, click ids, landing page and referrer. */
export function captureAttribution() {
  if (typeof window === "undefined") return;
  try {
    const url = new URL(window.location.href);
    const touch: Touch = { landing: (url.pathname + url.search).slice(0, 500), at: new Date().toISOString() };
    let campaign = false;
    for (const k of PARAMS) {
      const v = url.searchParams.get(k);
      if (v) {
        touch[k] = v.slice(0, 200);
        campaign = true;
      }
    }
    let external = false;
    if (document.referrer) {
      try {
        external = new URL(document.referrer).host !== url.host;
      } catch {
        /* ignore */
      }
      if (external) touch.referrer = document.referrer.slice(0, 500);
    }
    const s = read();
    if (!s.first) s.first = touch;
    if (campaign || external || !s.last) s.last = touch;
    // Meta click id cookie format, used until the Pixel sets _fbc itself.
    if (touch.fbclid) s.fbc = `fb.1.${Date.now()}.${touch.fbclid}`;
    localStorage.setItem(KEY, JSON.stringify(s));
  } catch {
    /* storage blocked */
  }
}

/** Browser ids the ad platforms use to match server events. */
export function clickIds() {
  const s = read();
  const ga = cookie("_ga")?.split(".");
  return {
    fbp: cookie("_fbp"),
    fbc: cookie("_fbc") ?? s.fbc,
    ttp: cookie("_ttp"),
    gaClientId: ga && ga.length >= 4 ? `${ga[2]}.${ga[3]}` : undefined,
    ttclid: s.last?.ttclid ?? s.first?.ttclid,
    gclid: s.last?.gclid ?? s.first?.gclid,
  };
}

export function getAttribution(): OrderAttribution {
  const s = read();
  const { fbp, fbc, ttp, gaClientId } = clickIds();
  const out: OrderAttribution = { first: s.first, last: s.last, fbp, fbc, ttp, gaClientId, consent: getConsent() ?? undefined };
  return Object.fromEntries(Object.entries(out).filter(([, v]) => v !== undefined)) as OrderAttribution;
}
