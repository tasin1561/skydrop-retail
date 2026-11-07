// Server-side data access. Uses the Laravel API when API_URL is set (e.g. http://localhost:8000/api/v1),
// otherwise falls back to the bundled demo catalog so the storefront always works.
import * as local from "./catalog";
import type { SearchParams } from "./catalog";
import type { Product, StoreMode } from "./types";

const API = process.env.API_URL?.replace(/\/$/, "");

export const usingApi = Boolean(API);

async function api<T>(path: string, revalidate = 60): Promise<T | null> {
  if (!API) return null;
  try {
    const res = await fetch(API + path, { headers: { Accept: "application/json" }, next: { revalidate } });
    if (!res.ok) return null;
    return (await res.json()) as T;
  } catch {
    return null;
  }
}

function qs(params: Record<string, string | number | boolean | undefined>) {
  const u = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== "" && v !== false) u.set(k, String(v === true ? 1 : v));
  return u.toString();
}

export type SearchResult = { total: number; page: number; pages: number; items: Product[]; status?: "ready" | "pending"; jobId?: string };

export async function searchProducts(sp: SearchParams): Promise<SearchResult> {
  const r = await api<SearchResult>(
    `/search?${qs({ q: sp.q, store: sp.store, category: sp.category, sort: sp.sort, min: sp.min, max: sp.max, rating: sp.rating, fast: sp.fast, page: sp.page })}`,
    30,
  );
  return r ?? local.searchProducts(sp);
}

export async function getProduct(id: string): Promise<(Product & { pending?: boolean }) | null> {
  const r = await api<{ product: Product; status: string }>(`/products/${encodeURIComponent(id)}`, 30);
  if (r?.product) return { ...r.product, pending: r.status === "pending" };
  return local.getProduct(id);
}

export async function byMarket(m: StoreMode, limit = 12, category?: string): Promise<Product[]> {
  const r = await api<{ items: Product[] }>(`/products/popular?${qs({ store: m, limit, category })}`, 300);
  return r?.items ?? local.byMarket(m, limit, category);
}

export async function deals(m: StoreMode, limit = 12): Promise<Product[]> {
  const r = await api<{ items: Product[] }>(`/products/deals?${qs({ store: m, limit })}`, 300);
  return r?.items ?? local.deals(m, limit);
}

export async function related(p: Product, limit = 8): Promise<Product[]> {
  const r = await api<{ items: Product[] }>(`/products/${encodeURIComponent(p.id)}/related?${qs({ limit })}`, 300);
  return r?.items ?? local.related(p, limit);
}

export async function suggestions(q: string, store: StoreMode, limit = 6) {
  const r = await api<{ items: { id: string; title: string; market: string }[] }>(`/suggest?${qs({ q, store, limit })}`, 30);
  return r?.items ?? local.suggestions(q, store, limit);
}

export async function productsByIds(ids: string[]): Promise<Product[]> {
  if (!ids.length) return [];
  const r = await api<{ items: Product[] }>(`/products?ids=${ids.map(encodeURIComponent).join(",")}`, 30);
  if (r?.items) return r.items;
  return ids.map((id) => local.getProduct(id)).filter((p): p is Product => !!p);
}

export async function verifyItems(items: { productId: string; unitBdt: number; qty?: number; variant?: string }[]) {
  if (!API) return null;
  try {
    const res = await fetch(API + "/checkout/verify", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ items }),
      cache: "no-store",
    });
    return res.ok ? await res.json() : null;
  } catch {
    return null;
  }
}

export async function offersFeed(store: StoreMode, limit = 40): Promise<Product[]> {
  const r = await api<{ items: Product[] }>(`/offers?${qs({ store, limit })}`, 120);
  if (r?.items) return r.items;
  // demo: products with any offer, then the deepest discounts
  const withOffers = local.searchProducts({ store, perPage: 400 }).items.filter((p) => (p.offers?.length ?? 0) > 0);
  const discounted = local.deals(store, limit);
  const seen = new Set<string>();
  return [...withOffers, ...discounted].filter((p) => (seen.has(p.id) ? false : (seen.add(p.id), true))).slice(0, limit);
}
