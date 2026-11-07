import { NextResponse } from "next/server";
import { suggestions } from "@/lib/catalog";
import { isStoreMode } from "@/lib/markets";

export async function GET(req: Request) {
  const url = new URL(req.url);
  const q = (url.searchParams.get("q") ?? "").slice(0, 80);
  const store = url.searchParams.get("store");
  if (q.trim().length < 2) return NextResponse.json({ items: [] });
  return NextResponse.json({ items: suggestions(q, isStoreMode(store) ? store : "all") });
}
