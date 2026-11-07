import { NextResponse } from "next/server";
import { headers } from "next/headers";
import { internalHeaders } from "@/lib/data";

// GET /api/price-history/{id}?days=30 → the Laravel price history, fetched server-side.
// The API guards this catalog-like read (internal key or signed app only), so the browser goes through here.
const API = process.env.API_URL?.replace(/\/$/, "");

export async function GET(req: Request, ctx: { params: Promise<{ id: string }> }) {
  const { id } = await ctx.params;
  if (!API || !/^[A-Za-z0-9._:-]{1,64}$/.test(id)) return NextResponse.json({ message: "Not found" }, { status: 404 });
  const raw = Number(new URL(req.url).searchParams.get("days") ?? 30);
  const days = [7, 30, 90, 180, 365].includes(raw) ? raw : 30;
  const ua = (await headers()).get("user-agent") ?? "";
  try {
    const res = await fetch(`${API}/products/${encodeURIComponent(id)}/price-history?days=${days}`, {
      headers: { Accept: "application/json", ...internalHeaders, ...(ua ? { "X-Client-UA": ua.slice(0, 300) } : {}) },
      next: { revalidate: 600 },
      signal: AbortSignal.timeout(6000),
    });
    const body = await res.json().catch(() => ({}));
    return NextResponse.json(body, { status: res.status, headers: res.ok ? { "Cache-Control": "public, max-age=600" } : {} });
  } catch {
    return NextResponse.json({ status: "unavailable" }, { status: 503 });
  }
}
