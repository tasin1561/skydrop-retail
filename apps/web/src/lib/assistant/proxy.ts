// Server-only helpers for the /api/assistant/* route handlers: forward to the Laravel API with the shopper's
// bearer token, the internal key and the shopper's IP, so the API origin and key never reach the browser.
import { clientIp } from "@/lib/clientIp";

export const ASSISTANT_API = process.env.API_URL?.replace(/\/$/, "") ?? "";

export function upstreamHeaders(req: Request, json = true): Record<string, string> {
  const h: Record<string, string> = { Accept: json ? "application/json" : "text/event-stream" };
  if (json) h["Content-Type"] = "application/json";
  const auth = req.headers.get("authorization");
  if (auth && /^Bearer [\w|.-]+$/.test(auth)) h.Authorization = auth;
  if (process.env.INTERNAL_API_KEY) {
    h["X-Internal-Key"] = process.env.INTERNAL_API_KEY;
    const ip = clientIp(req.headers);
    if (ip) h["X-Client-IP"] = ip;
  }
  return h;
}

export async function forwardJson(req: Request, path: string, init: RequestInit) {
  try {
    const res = await fetch(ASSISTANT_API + path, { ...init, headers: upstreamHeaders(req), cache: "no-store" });
    const body = await res.text();
    return new Response(body || "{}", { status: res.status, headers: { "Content-Type": "application/json", "Cache-Control": "no-store" } });
  } catch {
    return Response.json({ message: "Assistant is unavailable right now.", code: "upstream" }, { status: 502 });
  }
}
