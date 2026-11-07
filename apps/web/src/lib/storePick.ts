// "Has this browser chosen where to shop?" Remembered in localStorage and in the sr-store-picked cookie, so the
// root layout can skip rendering the first-visit store picker on the server for returning shoppers.
export const STORE_PICKED_COOKIE = "sr-store-picked";
const KEY = "skydrop-retail:store-picked";

export function isStorePicked(): boolean {
  if (typeof window === "undefined") return true;
  try {
    if (localStorage.getItem(KEY) === "1") return true;
  } catch {
    /* storage blocked: fall back to the cookie */
  }
  try {
    return document.cookie.split("; ").some((c) => c === `${STORE_PICKED_COOKIE}=1`);
  } catch {
    return false;
  }
}

export function markStorePicked() {
  if (typeof window === "undefined") return;
  try {
    localStorage.setItem(KEY, "1");
  } catch {
    /* ignore */
  }
  try {
    document.cookie = `${STORE_PICKED_COOKIE}=1; path=/; max-age=31536000; samesite=lax`;
  } catch {
    /* cookies blocked: localStorage still remembers it */
  }
}

const BOT_UA =
  /bot|crawl|spider|slurp|mediapartners|lighthouse|headless|phantom|puppeteer|playwright|bingpreview|facebookexternalhit|embedly|quora link preview|whatsapp|telegram|skypeuripreview|vkshare|pinterest|redditbot|applebot|yandex|baidu|duckduck|semrush|ahrefs|petalbot|google-inspectiontool|chrome-lighthouse/i;

/** Crawlers, link previewers and automated browsers never see the picker. */
export function isBot(): boolean {
  if (typeof navigator === "undefined") return true;
  if (navigator.webdriver) return true;
  return BOT_UA.test(navigator.userAgent || "");
}
