// Brand filter helpers shared by server pages and client filters. Limits mirror the API (CatalogQuery::parseBrands):
// up to 10 brands, each at most 60 characters, case-insensitive, first spelling kept.

export const MAX_BRANDS = 10;
export const BRAND_MAX_LEN = 60;

export type BrandFacet = { name: string; count: number };

/** "Nike,red tape , NIKE" -> ["Nike", "red tape"]. Over-long names are dropped and the list is cut at MAX_BRANDS. */
export function parseBrands(raw: string | string[] | null | undefined): string[] {
  const parts = (Array.isArray(raw) ? raw.join(",") : (raw ?? "")).split(",");
  const seen = new Map<string, string>();
  for (const p of parts) {
    const b = p.replace(/\s+/g, " ").trim();
    if (!b || b.length > BRAND_MAX_LEN) continue;
    const k = b.toLowerCase();
    if (!seen.has(k)) seen.set(k, b);
    if (seen.size >= MAX_BRANDS) break;
  }
  return [...seen.values()];
}

export const sameBrand = (a: string, b: string) => a.toLowerCase() === b.toLowerCase();

/** Adds or removes one brand (case-insensitive) and returns the ?brand= value ("" = none). */
export function toggleBrand(selected: string[], brand: string): string {
  const on = selected.some((s) => sameBrand(s, brand));
  const next = on ? selected.filter((s) => !sameBrand(s, brand)) : [...selected, brand].slice(-MAX_BRANDS);
  return next.join(",");
}

/** Query string with readable commas (?brand=Nike,Red+Tape). */
export function prettyQuery(q: URLSearchParams): string {
  return q.toString().replace(/%2C/gi, ",");
}

/** Top brands of a product list (most products first, then name), counts capped like the API. */
export function brandFacets(items: { brand?: string | null }[], limit = 20): BrandFacet[] {
  const counts = new Map<string, BrandFacet>();
  for (const p of items) {
    const name = (p.brand ?? "").trim();
    if (!name || name.length > BRAND_MAX_LEN) continue;
    const k = name.toLowerCase();
    const f = counts.get(k);
    if (f) f.count++;
    else counts.set(k, { name, count: 1 });
  }
  return [...counts.entries()]
    .sort(([ka, a], [kb, b]) => b.count - a.count || (ka < kb ? -1 : ka > kb ? 1 : 0))
    .slice(0, limit)
    .map(([, f]) => ({ name: f.name, count: Math.min(f.count, 500) }));
}
