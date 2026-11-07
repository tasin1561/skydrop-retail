import { NextResponse } from "next/server";
import { isCrossSite } from "@/lib/clientIp";
import { getProduct } from "@/lib/catalog";
import { verifyItems } from "@/lib/data";
import { priceBdt } from "@/lib/pricing";
import { demoDecorate } from "@/lib/cms";

// Mock of the live checkout verification. In production this calls Oxylabs for each item
// (pincode 700001) and compares the fresh price and stock with what the shopper saw.
export async function POST(req: Request) {
  if (isCrossSite(req)) return Response.json({ message: "Forbidden" }, { status: 403 });
  const body = (await req.json().catch(() => ({}))) as { items?: { productId: string; unitBdt: number }[] };
  const items = body.items ?? [];
  const live = await verifyItems(items);
  if (live) return NextResponse.json(live);
  await new Promise((r) => setTimeout(r, 900));
  const results = items.map((i) => {
    const p = demoDecorate(getProduct(i.productId));
    if (!p) return { productId: i.productId, ok: false, reason: "not_found" };
    // Items that cannot ship to Bangladesh (same rules as the API's restricted_rules).
    if (p.restriction?.action === "block")
      return { productId: i.productId, ok: false, deliverable: false, reason: "restricted", message: p.restriction.message };
    const fresh = priceBdt(p);
    return { productId: i.productId, ok: true, freshBdt: fresh, changed: fresh !== i.unitBdt, deliverable: true };
  });
  return NextResponse.json({ ok: results.every((r) => r.ok), results, checkedAt: new Date().toISOString() });
}
