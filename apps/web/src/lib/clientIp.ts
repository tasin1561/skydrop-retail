// The shopper's IP for the API's X-Client-IP header (assistant quotas, honeypot blocks). The API trusts that header
// whenever the internal key is present, so it must come from a header the client cannot choose:
// - CF-Connecting-IP: Cloudflare overwrites it on every request.
// - X-Real-IP: set by an nginx/Caddy hop in front of Next.js.
// - the LAST X-Forwarded-For hop: proxies append, so the FIRST entry is whatever the client sent and is never used.
// Sound only when the origin accepts traffic from Cloudflare / your own proxy alone (see docs/SECURITY.md).
export function clientIp(h: Headers): string {
  const xff = (h.get("x-forwarded-for") ?? "")
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean);
  const ip = (h.get("cf-connecting-ip") ?? "").trim() || (h.get("x-real-ip") ?? "").trim() || xff[xff.length - 1] || "";
  return /^[0-9A-Fa-f:.]{2,45}$/.test(ip) ? ip : "";
}

/**
 * True when another site's page made this request (form post, no-cors fetch). Route handlers that spend provider or
 * AI credit refuse those, so a third-party page cannot drive our proxies through its visitors' browsers (each with a
 * fresh IP, which per-IP rate limits would not catch). Requests without fetch metadata (old browsers, apps) pass.
 */
export function isCrossSite(req: Request): boolean {
  const site = req.headers.get("sec-fetch-site");
  if (site) return site === "cross-site" || site === "same-site";
  const origin = req.headers.get("origin");
  if (!origin) return false;
  try {
    return new URL(origin).host !== (req.headers.get("x-forwarded-host") ?? req.headers.get("host"));
  } catch {
    return true;
  }
}
