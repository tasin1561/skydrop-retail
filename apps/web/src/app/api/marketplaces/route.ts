import { NextResponse } from "next/server";
import { internalHeaders } from "@/lib/data";

// GET /api/marketplaces -> the API's marketplace switches, fetched server-side (the catalog API answers the Next.js server only).
export async function GET() {
  const api = process.env.API_URL?.replace(/\/$/, "");
  if (!api) return NextResponse.json({ items: [] });
  try {
    const res = await fetch(api + "/marketplaces", { headers: { Accept: "application/json", ...internalHeaders }, next: { revalidate: 60 } });
    if (!res.ok) return NextResponse.json({ items: [] }, { status: 502 });
    return NextResponse.json(await res.json(), { headers: { "Cache-Control": "public, max-age=60" } });
  } catch {
    return NextResponse.json({ items: [] }, { status: 502 });
  }
}
