import { NextResponse } from "next/server";
import { isCrossSite } from "@/lib/clientIp";
import { demoAlternatives, demoBundleSaving } from "@/lib/shopping/server";

// Demo-mode helper for the shopping features (the Laravel API serves these when NEXT_PUBLIC_API_URL is set):
// POST { action: "alternatives", items } → cheaper identical listings; POST { action: "bundle-saving", items } → bundle discount.
type Body = { action?: string; items?: { productId: string; qty: number; variant?: string; unitBdt?: number }[] };

export async function POST(req: Request) {
  if (isCrossSite(req)) return Response.json({ message: "Forbidden" }, { status: 403 });
  const body = (await req.json().catch(() => ({}))) as Body;
  const items = (body.items ?? []).slice(0, 50).map((i) => ({ productId: String(i.productId), qty: Math.max(1, Number(i.qty) || 1), variant: i.variant, unitBdt: Number(i.unitBdt) || 0 }));
  if (body.action === "alternatives") return NextResponse.json({ items: demoAlternatives(items) });
  if (body.action === "bundle-saving") return NextResponse.json(demoBundleSaving(items));
  return NextResponse.json({ message: "Unknown action" }, { status: 400 });
}
