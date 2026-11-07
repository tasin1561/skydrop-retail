// Cookie consent: "all" (analytics + ads) or "essential" (no marketing tags). Remembered for 180 days.
export type Consent = "all" | "essential";

const KEY = "skydrop-retail:consent";
const MAX_AGE = 180 * 24 * 3600 * 1000;
export const OPEN_CONSENT_EVENT = "skydrop:consent-open";

let cached: Consent | null | undefined;
const listeners = new Set<() => void>();

export function getConsent(): Consent | null {
  if (cached !== undefined) return cached;
  cached = null;
  try {
    const raw = JSON.parse(localStorage.getItem(KEY) ?? "null") as { choice?: string; at?: number } | null;
    if (raw && (raw.choice === "all" || raw.choice === "essential") && Date.now() - (raw.at ?? 0) < MAX_AGE) cached = raw.choice;
  } catch {
    /* storage blocked: ask again */
  }
  return cached;
}

export function setConsent(choice: Consent) {
  cached = choice;
  try {
    localStorage.setItem(KEY, JSON.stringify({ choice, at: Date.now() }));
  } catch {
    /* keep for this page view */
  }
  listeners.forEach((l) => l());
}

export function subscribeConsent(l: () => void) {
  listeners.add(l);
  return () => {
    listeners.delete(l);
  };
}

/** Reopens the consent banner (footer "Cookie settings"). */
export function openConsent() {
  window.dispatchEvent(new Event(OPEN_CONSENT_EVENT));
}
