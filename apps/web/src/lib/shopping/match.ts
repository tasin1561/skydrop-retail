// Product matching for "Compare stores" (TypeScript port of App\Services\Shopping\ProductMatcher), used by the demo catalog.
import type { Product } from "../types";

const STOP = new Set(["with", "and", "for", "the", "pack", "of", "new", "latest", "edition", "black", "white", "blue", "red", "green", "grey", "gray", "silver", "gold", "pink"]);

export const normBrand = (b: string) =>
  b
    .toLowerCase()
    .trim()
    .replace(/^(visit the|brand:)\s+/, "")
    .replace(/\s+(store|india|official)$/, "")
    .replace(/[^a-z0-9]/g, "");

const normModel = (m: string) => m.replace(/[^A-Za-z0-9]/g, "").toUpperCase();

export function modelTokens(title: string): string[] {
  const out = new Set<string>();
  for (const t of title.match(/\b(?=[A-Za-z0-9-]*\d)(?=[A-Za-z0-9-]*[A-Za-z])[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*\b/g) ?? []) {
    const n = normModel(t);
    if (n.length < 4 || /^\d+(GB|TB|MB|ML|L|G|KG|MAH|W|HZ|MM|CM|INCH|PCS|X)$/.test(n)) continue;
    out.add(n);
  }
  return [...out];
}

export function sizes(title: string): string[] {
  const out = new Set<string>();
  for (const m of title.matchAll(/(\d+(?:\.\d+)?)\s?(gb|tb|ml|l|kg|g|mah|w|inch|pcs)\b/gi)) {
    const unit = m[2].toLowerCase();
    if (unit === "g" && ["2", "3", "4", "5"].includes(m[1])) continue;
    out.add((m[1].includes(".") ? m[1].replace(/\.?0+$/, "") : m[1]) + unit);
  }
  return [...out];
}

function sizesConflict(a: string[], b: string[]) {
  const unit = (s: string) => s.replace(/^[\d.]+/, "");
  for (const u of new Set(a.map(unit))) {
    const va = a.filter((s) => unit(s) === u);
    const vb = b.filter((s) => unit(s) === u);
    if (vb.length && !va.some((x) => vb.includes(x))) return true;
  }
  return false;
}

export function normTitle(title: string, brand = "") {
  const b = brand.toLowerCase().trim();
  return title
    .toLowerCase()
    .replace(/\(.*?\)|\[.*?\]/g, " ")
    .replace(/[^a-z0-9 ]+/g, " ")
    .split(" ")
    .filter((w) => w && !STOP.has(w) && w !== b)
    .slice(0, 14)
    .join(" ");
}

function trigrams(s: string) {
  const out = new Set<string>();
  for (const w of s.split(" ")) {
    if (!w) continue;
    const p = "  " + w + " ";
    for (let i = 0; i < p.length - 2; i++) out.add(p.slice(i, i + 3));
  }
  return out;
}

export function similarity(a: string, b: string) {
  const ta = trigrams(a);
  const tb = trigrams(b);
  if (!ta.size || !tb.size) return 0;
  let inter = 0;
  for (const t of ta) if (tb.has(t)) inter++;
  return inter / (ta.size + tb.size - inter);
}

export const AUTO_SHOW = 0.82;

/** Returns the match method and score when a and b look like the same product (null otherwise). */
export function compareProducts(a: Product, b: Product): { method: "model" | "title"; score: number } | null {
  if (a.id === b.id || !a.brand || normBrand(a.brand) !== normBrand(b.brand)) return null;
  const ma = modelTokens(a.title);
  const mb = modelTokens(b.title);
  const sa = sizes(a.title);
  const sb = sizes(b.title);
  if (ma.length && mb.length && ma.some((m) => mb.includes(m))) return sizesConflict(sa, sb) ? null : { method: "model", score: 0.95 };
  if (a.category !== b.category || sizesConflict(sa, sb)) return null;
  const score = similarity(normTitle(a.title, a.brand), normTitle(b.title, b.brand));
  return score >= AUTO_SHOW ? { method: "title", score: Math.round(score * 1000) / 1000 } : null;
}

export function deliveryFor(p: Pick<Product, "market" | "prime">) {
  const base = { amazon: [7, 12], flipkart: [8, 13], myntra: [8, 13] }[p.market] ?? [7, 12];
  const fast = p.prime ? 1 : 0;
  return { min: base[0] - fast, max: base[1] - fast };
}
