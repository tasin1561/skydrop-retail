// Server-side data access. Uses the Laravel API when API_URL is set (e.g. http://localhost:8000/api/v1),
// otherwise falls back to the bundled demo catalog so the storefront always works.
import * as local from "./catalog";
import type { SearchParams } from "./catalog";
import type { Product, StoreMode } from "./types";
import { demoDecorate } from "./cms";
import { clientIp } from "./clientIp";
import { localCategoryPage, localTree, type CatNode, type CategoryPageData } from "./categories";

const API = process.env.API_URL?.replace(/\/$/, "");

export const usingApi = Boolean(API);

// Server-to-server key: the API's catalog endpoints only answer the Next.js server (or signed apps). Never expose it to the browser.
export const internalHeaders: Record<string, string> = process.env.INTERNAL_API_KEY ? { "X-Internal-Key": process.env.INTERNAL_API_KEY } : {};

/**
 * One URL path segment for API_URL. encodeURIComponent keeps "/" out, but "." and ".." survive it and fetch() resolves
 * them, so a crafted id could walk out of /products/... into any other API route with the internal key attached.
 */
export const pathSeg = (s: string) => (s === "." || s === ".." ? "-" : encodeURIComponent(s));
/** Product ids as the API routes accept them. */
export const isProductId = (id: string) => /^[A-Za-z0-9_-]{1,64}$/.test(id);
/** Category paths: lowercase slug segments ("mobiles-electronics/phones"). */
const isCategoryPath = (p: string) => p.length <= 300 && /^[a-z0-9-]+(?:\/[a-z0-9-]+)*$/.test(p);

type ApiResult<T> = { ok: true; status: number; data: T } | { ok: false; status: number };

// Last good answer per path, kept in this server process. When the API is slow or down we serve it
// instead of an error, so product pages and search keep working (prices are re-checked at checkout).
const lastGood = new Map<string, unknown>();
const LAST_GOOD_MAX = 800;
function remember(path: string, data: unknown) {
  if (lastGood.has(path)) lastGood.delete(path);
  lastGood.set(path, data);
  if (lastGood.size > LAST_GOOD_MAX) lastGood.delete(lastGood.keys().next().value as string);
}
export function lastGoodFor<T>(path: string): T | null {
  return (lastGood.get(path) as T | undefined) ?? null;
}

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

/**
 * Who the shopper is, for the API: user agent (bots never trigger paid Oxylabs fetches) and the trusted client IP
 * (per-shopper rate limits and blocks; without it every shopper shares the server's single bucket).
 */
export async function shopperHeaders(): Promise<Record<string, string>> {
  try {
    const { headers } = await import("next/headers");
    const h = await headers();
    const ua = h.get("user-agent");
    const ip = clientIp(h);
    return { ...(ua ? { "X-Client-UA": ua.slice(0, 300) } : {}), ...(ip ? { "X-Client-IP": ip } : {}) };
  } catch {
    return {}; // outside a request (build time): no forwarding
  }
}

/**
 * Fetch from the Laravel API with a hard timeout and one quick retry on network errors / 502-504.
 * Never throws. status 0 = network error or timeout.
 */
async function apiFetch<T>(path: string, { revalidate = 60, timeoutMs = 8000 }: { revalidate?: number; timeoutMs?: number } = {}): Promise<ApiResult<T>> {
  if (!API) return { ok: false, status: 0 };
  const started = Date.now();
  const ua = await shopperHeaders();
  for (let attempt = 0; attempt < 2; attempt++) {
    const left = timeoutMs - (Date.now() - started);
    if (left < 500) break;
    try {
      const res = await fetch(API + path, {
        headers: { Accept: "application/json", ...internalHeaders, ...ua },
        next: { revalidate },
        signal: AbortSignal.timeout(left),
      });
      if (res.ok) {
        const data = (await res.json()) as T;
        if (res.status === 200) remember(path, data);
        return { ok: true, status: res.status, data };
      }
      if (![502, 503, 504].includes(res.status)) return { ok: false, status: res.status };
    } catch {
      // timeout or network error: retry once if there is time left
    }
    await sleep(250);
  }
  return { ok: false, status: 0 };
}

async function api<T>(path: string, revalidate = 60): Promise<T | null> {
  const r = await apiFetch<T>(path, { revalidate });
  if (r.ok) return r.data;
  return r.status === 404 ? null : lastGoodFor<T>(path);
}

function qs(params: Record<string, string | number | boolean | undefined>) {
  const u = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) if (v !== undefined && v !== "" && v !== false) u.set(k, String(v === true ? 1 : v));
  return u.toString();
}

export type SearchFacets = { categories: { path: string; name: string; nameBn?: string | null; icon: string; count: number }[] };
export type SearchResult = {
  total: number;
  page: number;
  pages: number;
  items: Product[];
  status?: "ready" | "pending";
  jobId?: string;
  stale?: boolean;
  /** child-category counts of the whole result (API mode) */
  facets?: SearchFacets;
};

export async function searchProducts(sp: SearchParams): Promise<SearchResult> {
  const r = await api<SearchResult>(
    `/search?${qs({ q: sp.q, store: sp.store, category: sp.category, sort: sp.sort, min: sp.min, max: sp.max, rating: sp.rating, fast: sp.fast, page: sp.page })}`,
    30,
  );
  if (r) return r;
  // Demo mode, or the API is down with nothing cached: answer from the bundled catalog (marked stale in API mode).
  const res = local.searchProducts(sp);
  return { ...res, items: res.items.map(demoDecorate), ...(API ? { stale: true } : {}) };
}

/**
 * Product page loader with explicit states so the page never crashes or blocks:
 * - ready: render it (refreshJobId = a live price refresh is running; stale = served from a fallback)
 * - fetching: not in our database yet; a live fetch was queued (show the live-fetch screen, poll jobId)
 * - notfound: real 404
 * - unavailable: API down and nothing cached (show retry)
 */
export type ProductLoad =
  | { kind: "ready"; product: Product; refreshJobId?: string; stale?: boolean }
  | { kind: "fetching"; jobId: string; market?: string }
  | { kind: "notfound" }
  | { kind: "unavailable" };

export async function loadProduct(id: string): Promise<ProductLoad> {
  if (!API) {
    const p = demoDecorate(local.getProduct(id));
    return p ? { kind: "ready", product: p } : { kind: "notfound" };
  }
  if (!isProductId(id)) return { kind: "notfound" };
  const path = `/products/${pathSeg(id)}`;
  type Body = { product: Product | null; status: string; jobId?: string; market?: string; stale?: boolean };
  const r = await apiFetch<Body>(path, { revalidate: 30, timeoutMs: 8000 });
  if (r.ok) {
    if (r.status === 202 || !r.data.product) {
      return r.data.jobId ? { kind: "fetching", jobId: r.data.jobId, market: r.data.market } : { kind: "unavailable" };
    }
    return {
      kind: "ready",
      product: r.data.product,
      refreshJobId: r.data.status === "pending" ? r.data.jobId : undefined,
      stale: r.data.stale,
    };
  }
  if (r.status === 404) return { kind: "notfound" };
  // API slow/down: last good copy, then the bundled catalog, then a retry screen.
  const cached = lastGoodFor<Body>(path)?.product ?? demoDecorate(local.getProduct(id));
  return cached ? { kind: "ready", product: cached, stale: true } : { kind: "unavailable" };
}

export async function getProduct(id: string): Promise<(Product & { pending?: boolean }) | null> {
  const r = await loadProduct(id);
  return r.kind === "ready" ? { ...r.product, pending: Boolean(r.refreshJobId) } : null;
}

/** Fetch-job status for the live-fetch / live-search screens. */
export async function jobStatus(id: string): Promise<{ status: "queued" | "running" | "done" | "failed" | "unknown"; result?: Record<string, unknown>; error?: string }> {
  const r = await apiFetch<{ status: string; result?: Record<string, unknown>; error?: string }>(`/jobs/${pathSeg(id)}`, {
    revalidate: 0,
    timeoutMs: 5000,
  });
  if (!r.ok) return { status: "unknown" };
  const st = r.data.status;
  return {
    status: st === "done" || st === "failed" || st === "running" || st === "queued" ? st : "unknown",
    result: r.data.result,
    error: r.data.error,
  };
}

export async function byMarket(m: StoreMode, limit = 12, category?: string): Promise<Product[]> {
  const r = await api<{ items: Product[] }>(`/products/popular?${qs({ store: m, limit, category })}`, 300);
  return r?.items ?? local.byMarket(m, limit, category).map(demoDecorate);
}

export async function deals(m: StoreMode, limit = 12): Promise<Product[]> {
  const r = await api<{ items: Product[] }>(`/products/deals?${qs({ store: m, limit })}`, 300);
  return r?.items ?? local.deals(m, limit).map(demoDecorate);
}

export async function related(p: Product, limit = 8): Promise<Product[]> {
  const r = await api<{ items: Product[] }>(`/products/${pathSeg(p.id)}/related?${qs({ limit })}`, 300);
  return r?.items ?? local.related(p, limit).map(demoDecorate);
}

export async function suggestions(q: string, store: StoreMode, limit = 6) {
  // Typeahead must feel instant: short timeout, then the bundled catalog.
  const r = await apiFetch<{ items: { id: string; title: string; market: string }[] }>(`/suggest?${qs({ q, store, limit })}`, { revalidate: 30, timeoutMs: 2500 });
  return r.ok ? r.data.items : local.suggestions(q, store, limit);
}

export async function productsByIds(ids: string[]): Promise<Product[]> {
  if (!ids.length) return [];
  const safe = ids.filter(isProductId);
  const r = safe.length ? await api<{ items: Product[] }>(`/products?ids=${safe.map(encodeURIComponent).join(",")}`, 30) : null;
  if (r?.items) return r.items;
  return ids.map((id) => demoDecorate(local.getProduct(id))).filter((p): p is Product => !!p);
}

export async function verifyItems(items: { productId: string; unitBdt: number; qty?: number; variant?: string }[]) {
  if (!API) return null;
  try {
    const res = await fetch(API + "/checkout/verify", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json", ...internalHeaders, ...(await shopperHeaders()) },
      body: JSON.stringify({ items }),
      cache: "no-store",
      signal: AbortSignal.timeout(25000),
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
  return [...withOffers, ...discounted].filter((p) => (seen.has(p.id) ? false : (seen.add(p.id), true))).slice(0, limit).map(demoDecorate);
}

// ---------- category tree (GET /categories, GET /categories/{path}); demo / API-down fallback: the bundled tree ----------

/** Tree filtered to what the store sells, with product counts. Never throws. */
export async function getCategoryTree(store: StoreMode = "all", depth?: number): Promise<CatNode[]> {
  const r = await api<{ items: CatNode[] }>(`/categories?${qs({ store, depth })}`, 300);
  return r?.items?.length ? r.items : localTree(store, depth);
}

/** Category landing data, or null for an unknown path. Never throws. */
export async function getCategoryPage(path: string, store: StoreMode = "all"): Promise<CategoryPageData | null> {
  if (API) {
    if (!isCategoryPath(path)) return null;
    const r = await apiFetch<CategoryPageData>(`/categories/${path.split("/").map(pathSeg).join("/")}?${qs({ store })}`, { revalidate: 120 });
    if (r.ok) return { ...r.data, node: { ...r.data.node, children: r.data.node.children ?? [] } };
    if (r.status === 404) return null; // the API's tree is authoritative (admin edits)
  }
  return localCategoryPage(path, store);
}
