// Server-side data for the shopping features. Uses the Laravel API when API_URL is set, otherwise the
// bundled demo catalog (same idea as src/lib/data.ts), so every section works in demo mode.
import * as local from "../catalog";
import { demoDecorate } from "../cms";
import { priceBdt } from "../pricing";
import type { CartItem, Product } from "../types";
import { pathSeg } from "../data";
import { compareProducts, deliveryFor, normTitle, similarity } from "./match";
import type { Alternative, BoughtTogether, Bundle, CompareResult, CompareRow } from "./types";

const API = process.env.API_URL?.replace(/\/$/, "");

async function api<T>(path: string, revalidate = 300, init?: RequestInit): Promise<T | null> {
  if (!API) return null;
  try {
    const res = await fetch(API + path, { headers: { Accept: "application/json", "Content-Type": "application/json" }, next: { revalidate }, ...init });
    if (!res.ok) return null;
    return (await res.json()) as T;
  } catch {
    return null;
  }
}

const enc = pathSeg; // never lets "." / ".." walk out of the intended API route

function all(): Product[] {
  return local.searchProducts({ perPage: 5000 }).items;
}

const inStock = (p: Product) => !/unavailable|out of stock/i.test(p.stock ?? "");

// ---------------- compare stores ----------------

export async function getCompare(p: Product): Promise<CompareResult> {
  if (API) return (await api<CompareResult>(`/products/${enc(p.id)}/compare`, 300)) ?? { productId: p.id, items: [], bestId: null, savingBdt: 0 };
  const others = all()
    .map((o) => ({ o, m: compareProducts(p, o) }))
    .filter((x) => x.m)
    .slice(0, 5);
  if (!others.length) return { productId: p.id, items: [], bestId: null, savingBdt: 0, demo: true };
  const rows: CompareRow[] = [{ o: p, m: null }, ...others].map(({ o, m }) => ({
    productId: o.id,
    market: o.market,
    priceBdt: priceBdt(o),
    inStock: inStock(o),
    delivery: deliveryFor(o),
    current: o.id === p.id,
    best: false,
    match: m,
    product: demoDecorate(o)!,
  }));
  const best = rows.filter((r) => r.inStock).sort((a, b) => a.priceBdt - b.priceBdt || a.delivery.max - b.delivery.max)[0];
  for (const r of rows) r.best = r.productId === best?.productId;
  rows.sort((a, b) => a.priceBdt - b.priceBdt);
  const current = rows.find((r) => r.current)!;
  return { productId: p.id, items: rows, bestId: best?.productId ?? null, savingBdt: best ? Math.max(0, current.priceBdt - best.priceBdt) : 0, demo: true };
}

/** Cheaper identical listings for cart lines (checkout suggestion). Demo: local matcher. */
export function demoAlternatives(items: Pick<CartItem, "productId" | "qty" | "variant">[]): Alternative[] {
  const catalog = all();
  const ids = new Set(items.map((i) => i.productId));
  const out: Alternative[] = [];
  for (const i of items) {
    const p = local.getProduct(i.productId);
    if (!p) continue;
    const mine = priceBdt(p);
    const best = catalog
      .filter((o) => !ids.has(o.id) && inStock(o) && compareProducts(p, o))
      .map((o) => ({ o, price: priceBdt(o) }))
      .filter((x) => x.price < mine)
      .sort((a, b) => a.price - b.price)[0];
    if (best && mine - best.price >= 20) {
      out.push({
        productId: p.id,
        variant: i.variant ?? null,
        qty: i.qty,
        title: p.title,
        market: p.market,
        unitBdt: mine,
        alternativeId: best.o.id,
        alternativeMarket: best.o.market,
        alternativeBdt: best.price,
        savingBdt: (mine - best.price) * i.qty,
        delivery: deliveryFor(best.o),
        alternative: demoDecorate(best.o)!,
      });
    }
  }
  return out;
}

// ---------------- frequently bought together ----------------

const COMPLEMENTS: Record<string, string[]> = {
  electronics: ["electronics", "home"],
  fashion: ["fashion", "beauty"],
  beauty: ["beauty", "fashion"],
  grocery: ["grocery", "home"],
  home: ["home", "grocery"],
};

function complements(p: Product, n: number, exclude: Set<string>): Product[] {
  const cats = COMPLEMENTS[p.category] ?? [p.category];
  const price = priceBdt(p);
  const title = normTitle(p.title, p.brand);
  return all()
    .filter((x) => x.market === p.market && cats.includes(x.category) && !exclude.has(x.id) && inStock(x))
    .filter((x) => similarity(title, normTitle(x.title, x.brand)) <= 0.45 && !compareProducts(p, x))
    .sort((a, b) => Number(priceBdt(a) > price) - Number(priceBdt(b) > price) || b.reviews - a.reviews)
    .slice(0, n);
}

export async function getBoughtTogether(p: Product, limit = 3): Promise<BoughtTogether | null> {
  if (API) return api<BoughtTogether>(`/products/${enc(p.id)}/bought-together?limit=${limit}`, 600);
  const items = complements(p, limit, new Set([p.id])).map((x) => demoDecorate(x)!);
  if (!items.length) return null;
  return { product: p, source: "category", items, totalBdt: priceBdt(p) + items.reduce((s, x) => s + priceBdt(x), 0) };
}

// ---------------- curated bundles ----------------

/** Same as BundleService::bundleDiscount: % of the set, capped at our 10% margin (never below cost). */
function bundleDiscount(lines: { unitBdt: number; qty: number }[], pct: number) {
  let price = 0;
  let margin = 0;
  for (const l of lines) {
    const line = l.unitBdt * Math.max(1, l.qty);
    price += line;
    margin += line - line / 1.1;
  }
  return Math.floor(Math.min((price * pct) / 100, margin));
}

/** Demo bundles: the most reviewed product of a store + two complements, 5% extra off. */
export function demoBundles(): Bundle[] {
  const seeds: [string, string, string][] = [
    ["amazon", "electronics", "Everyday tech kit"],
    ["amazon", "grocery", "Pantry refill box"],
    ["flipkart", "fashion", "Weekend outfit set"],
    ["bigbasket", "grocery", "Breakfast basket"],
  ];
  const out: Bundle[] = [];
  for (const [market, category, title] of seeds) {
    const anchor = all()
      .filter((p) => p.market === market && p.category === category && inStock(p))
      .sort((a, b) => b.reviews - a.reviews)[0];
    if (!anchor) continue;
    const items = [anchor, ...complements(anchor, 2, new Set([anchor.id]))];
    if (items.length < 2) continue;
    const lines = items.map((p) => ({ unitBdt: priceBdt(p), qty: 1 }));
    const regular = lines.reduce((s, l) => s + l.unitBdt, 0);
    const discount = bundleDiscount(lines, 5);
    const slug = title.toLowerCase().replace(/[^a-z0-9]+/g, "-");
    out.push({
      id: "demo-" + slug,
      slug,
      title,
      description: `${items.length} favourites from ${anchor.brand || "one store"} and friends, bought together.`,
      image: anchor.image,
      discountPct: 5,
      regularBdt: regular,
      discountBdt: discount,
      totalBdt: regular - discount,
      available: true,
      count: items.length,
      items: items.map((p) => ({ qty: 1, product: demoDecorate(p)! })),
    });
  }
  return out;
}

export async function getBundles(): Promise<Bundle[]> {
  if (API) return (await api<{ items: Bundle[] }>("/bundles", 300))?.items ?? [];
  return demoBundles();
}

export async function getBundle(slug: string): Promise<Bundle | null> {
  if (API) return (await api<{ bundle: Bundle }>(`/bundles/${enc(slug)}`, 120))?.bundle ?? null;
  return demoBundles().find((b) => b.slug === slug) ?? null;
}

export async function bundlesForProduct(id: string): Promise<Bundle[]> {
  if (API) return (await api<{ items: Bundle[] }>(`/products/${enc(id)}/bundles`, 300))?.items ?? [];
  return demoBundles().filter((b) => b.items?.some((i) => i.product.id === id));
}

/** Demo cart: saving for curated bundles fully in the cart (one bundle per unit, biggest first). */
export function demoBundleSaving(items: Pick<CartItem, "productId" | "qty" | "unitBdt">[]) {
  const left = new Map<string, number>();
  const unit = new Map<string, number>();
  for (const i of items) {
    left.set(i.productId, (left.get(i.productId) ?? 0) + i.qty);
    unit.set(i.productId, i.unitBdt);
  }
  const applied: { id: string; slug: string; title: string; sets: number; discountBdt: number }[] = [];
  let total = 0;
  const value = (b: Bundle) => bundleDiscount((b.items ?? []).map((i) => ({ unitBdt: unit.get(i.product.id) ?? 0, qty: i.qty })), b.discountPct);
  const candidates = demoBundles().filter((b) => b.items?.every((i) => left.has(i.product.id)));
  for (const b of candidates.sort((x, y) => value(y) - value(x))) {
    const sets = Math.min(...b.items!.map((i) => Math.floor((left.get(i.product.id) ?? 0) / Math.max(1, i.qty))));
    if (sets < 1) continue;
    for (const i of b.items!) left.set(i.product.id, (left.get(i.product.id) ?? 0) - sets * Math.max(1, i.qty));
    const d = value(b) * sets;
    total += d;
    applied.push({ id: b.id, slug: b.slug, title: b.title, sets, discountBdt: d });
  }
  return { discount: total, bundles: applied };
}
