import { NextResponse } from "next/server";
import { getProduct } from "@/lib/catalog";
import { priceBdt } from "@/lib/pricing";

// Mock of the live checkout verification. In production this calls Oxylabs for each item
// (pincode 700001) and compares the fresh price and stock with what the shopper saw.
export async function POST(req: Request) {
  const body = (await req.json().catch(() => ({}))) as { items?: { productId: string; unitBdt: number }[] };
  const items = body.items ?? [];
  await new Promise((r) => setTimeout(r, 900));
  const results = items.map((i) => {
    const p = getProduct(i.productId);
    if (!p) return { productId: i.productId, ok: false, reason: "not_found" };
    const fresh = priceBdt(p);
    return { productId: i.productId, ok: true, freshBdt: fresh, changed: fresh !== i.unitBdt, deliverable: true };
  });
  return NextResponse.json({ ok: results.every((r) => r.ok), results, checkedAt: new Date().toISOString() });
}
