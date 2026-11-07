import { NextResponse } from "next/server";
import { productsByIds } from "@/lib/data";
import type { Product } from "@/lib/types";

// GET /api/products?ids=a,b,c -> card-sized product data for client lists (wishlist).
// Returns products in the requested order; unknown ids are skipped.
export async function GET(req: Request) {
  const raw = new URL(req.url).searchParams.get("ids") ?? "";
  const ids = Array.from(new Set(raw.split(",").map((s) => s.trim()).filter(Boolean))).slice(0, 100);
  const items: Partial<Product>[] = [];
  for (const p of await productsByIds(ids)) {
    items.push({
      id: p.id,
      market: p.market,
      category: p.category,
      title: p.title,
      brand: p.brand,
      priceInr: p.priceInr,
      mrpInr: p.mrpInr,
      rating: p.rating,
      reviews: p.reviews,
      image: p.image,
      images: [],
      placeholder: p.placeholder,
      prime: p.prime,
      bestSeller: p.bestSeller,
      amazonsChoice: p.amazonsChoice,
      salesVolume: p.salesVolume,
      detailed: p.detailed,
      weight: p.weight ?? null,
    });
  }
  return NextResponse.json({ items }, { headers: { "Cache-Control": "public, max-age=300" } });
}
