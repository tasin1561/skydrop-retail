import data from "@/data/catalog.json";
import type { MarketId, Product, StoreMode } from "./types";
import { priceBdt } from "./pricing";
import { inCategory } from "./categories";
import { brandFacets } from "./brands";

const ALL = (data as { products: Product[] }).products;

export type SearchParams = {
  q?: string;
  store?: StoreMode;
  category?: string;
  /** brand names, ORed, case-insensitive exact match (see lib/brands.ts) */
  brand?: string[];
  sort?: "relevance" | "price_asc" | "price_desc" | "rating" | "discount";
  min?: number;
  max?: number;
  rating?: number;
  fast?: boolean;
  page?: number;
  perPage?: number;
};

function score(p: Product, words: string[]) {
  if (!words.length) return 1;
  const hay = (p.title + " " + p.brand + " " + p.category).toLowerCase();
  let s = 0;
  for (const w of words) {
    if (hay.includes(w)) s += p.title.toLowerCase().startsWith(w) ? 3 : 1;
    else return 0;
  }
  return s;
}

export function searchProducts(sp: SearchParams) {
  const words = (sp.q ?? "")
    .toLowerCase()
    .split(/\s+/)
    .filter((w) => w.length > 1);
  let list = ALL.map((p) => ({ p, s: score(p, words) })).filter((x) => x.s > 0);
  if (sp.store && sp.store !== "all") list = list.filter((x) => x.p.market === sp.store);
  if (sp.category) list = list.filter((x) => inCategory(x.p, sp.category!)); // tree path (with descendants) or pricing group
  if (sp.min) list = list.filter((x) => priceBdt(x.p) >= sp.min!);
  if (sp.max) list = list.filter((x) => priceBdt(x.p) <= sp.max!);
  if (sp.rating) list = list.filter((x) => x.p.rating >= sp.rating!);
  if (sp.fast) list = list.filter((x) => x.p.prime);
  // Brand facets ignore the brand filter itself (same as the API), so shoppers can switch brands.
  const brands = brandFacets(list.map((x) => x.p));
  if (sp.brand?.length) {
    const want = new Set(sp.brand.map((b) => b.toLowerCase()));
    list = list.filter((x) => want.has((x.p.brand ?? "").trim().toLowerCase()));
  }

  const disc = (p: Product) => (p.mrpInr > p.priceInr ? 1 - p.priceInr / p.mrpInr : 0);
  switch (sp.sort) {
    case "price_asc":
      list.sort((a, b) => a.p.priceInr - b.p.priceInr);
      break;
    case "price_desc":
      list.sort((a, b) => b.p.priceInr - a.p.priceInr);
      break;
    case "rating":
      list.sort((a, b) => b.p.rating - a.p.rating || b.p.reviews - a.p.reviews);
      break;
    case "discount":
      list.sort((a, b) => disc(b.p) - disc(a.p));
      break;
    default:
      list.sort((a, b) => b.s - a.s || b.p.reviews - a.p.reviews);
  }
  const perPage = sp.perPage ?? 24;
  const page = Math.max(1, sp.page ?? 1);
  const total = list.length;
  return {
    total,
    page,
    pages: Math.max(1, Math.ceil(total / perPage)),
    items: list.slice((page - 1) * perPage, page * perPage).map((x) => x.p),
    facets: { categories: [], brands },
  };
}

export function getProduct(id: string) {
  return ALL.find((p) => p.id === id) ?? null;
}

export function byMarket(m: StoreMode, limit = 12, category?: string, brands: string[] = []) {
  const want = new Set(brands.map((b) => b.toLowerCase()));
  return ALL.filter((p) => (m === "all" || p.market === m) && (!category || p.category === category) && (!want.size || want.has((p.brand ?? "").trim().toLowerCase())))
    .sort((a, b) => b.reviews - a.reviews)
    .slice(0, limit);
}

export function deals(m: StoreMode, limit = 12) {
  return ALL.filter((p) => (m === "all" || p.market === m) && p.mrpInr > p.priceInr)
    .sort((a, b) => b.mrpInr / b.priceInr - a.mrpInr / a.priceInr)
    .slice(0, limit);
}

export function related(p: Product, limit = 8) {
  return ALL.filter((x) => x.id !== p.id && x.category === p.category && x.market === p.market)
    .sort((a, b) => b.reviews - a.reviews)
    .slice(0, limit);
}

export function suggestions(q: string, store: StoreMode, limit = 6) {
  return searchProducts({ q, store, perPage: limit }).items.map((p) => ({
    id: p.id,
    title: p.title,
    market: p.market as MarketId,
  }));
}
