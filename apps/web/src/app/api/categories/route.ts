import { NextResponse } from "next/server";
import { getCategoryTree } from "@/lib/data";
import { isStoreMode } from "@/lib/markets";

// Browser-side access to the category tree (mega menu, mobile sheet) in API mode. Never fails: falls back to the bundled tree.
export async function GET(req: Request) {
  const store = new URL(req.url).searchParams.get("store");
  const items = await getCategoryTree(isStoreMode(store) ? store : "all", 4);
  return NextResponse.json({ items }, { headers: { "Cache-Control": "public, max-age=120, stale-while-revalidate=600" } });
}
