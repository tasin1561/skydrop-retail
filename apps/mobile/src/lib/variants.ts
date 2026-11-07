// Variant grouping by dimension, same rules as apps/web/src/components/product/Variants.tsx.
import type { Variant } from "./types";

export type DimGroup = { key: string; values: { value: string; swatch: string | null }[] };

const LETTER = ["XXS", "XS", "S", "M", "L", "XL", "XXL", "XXXL", "3XL", "4XL"];
function sizeRank(v: string) {
  const l = LETTER.indexOf(v.toUpperCase().trim());
  if (l >= 0) return l;
  const n = parseFloat(v.replace(/[^\d.]/g, ""));
  return Number.isFinite(n) ? 100 + n : 10000;
}

export function groupVariants(variants: Variant[]): DimGroup[] {
  const map = new Map<string, Map<string, string | null>>();
  for (const v of variants) {
    for (const [k, val] of Object.entries(v.dimensions)) {
      if (!map.has(k)) map.set(k, new Map());
      const m = map.get(k)!;
      if (!m.has(val) || (!m.get(val) && v.swatch)) m.set(val, v.swatch);
    }
  }
  const groups: DimGroup[] = [];
  map.forEach((vals, key) => {
    const list = Array.from(vals, ([value, swatch]) => ({ value, swatch }));
    if (!/colou?r|shade|style|pattern/i.test(key)) list.sort((a, b) => sizeRank(a.value) - sizeRank(b.value));
    groups.push({ key, values: list });
  });
  return groups.sort((a, b) => Number(b.values.some((v) => v.swatch)) - Number(a.values.some((v) => v.swatch)));
}

export function initialSelection(id: string, variants: Variant[]) {
  const v = variants.find((x) => x.id === id) ?? variants.find((x) => x.selected) ?? variants[0];
  return v ? { ...v.dimensions } : {};
}

export function matches(v: Variant, sel: Record<string, string>, except?: string) {
  return Object.entries(sel).every(([k, val]) => k === except || v.dimensions[k] === undefined || v.dimensions[k] === val);
}

function score(v: Variant, sel: Record<string, string>) {
  return Object.entries(sel).reduce((n, [k, val]) => n + (v.dimensions[k] === val ? 1 : 0), 0);
}

/** Picks a value; if that exact combination doesn't exist, keeps it and takes the closest other values. */
export function pickVariant(variants: Variant[], sel: Record<string, string>, key: string, value: string) {
  const next = { ...sel, [key]: value };
  return (
    variants.find((v) => matches(v, next)) ??
    variants.filter((v) => v.dimensions[key] === value).sort((a, b) => score(b, next) - score(a, next))[0] ??
    null
  );
}

export function comboExists(variants: Variant[], sel: Record<string, string>, key: string, value: string) {
  return variants.some((v) => v.dimensions[key] === value && matches(v, sel, key));
}

/** Amazon CDN images accept a size token before the extension; fetch small versions for thumbs. */
export function sized(url: string | null | undefined, token: string) {
  if (!url) return url ?? null;
  if (!/m\.media-amazon\.com\/images\/I\//.test(url)) return url;
  return url.replace(/(\._[^/]*_)?\.(jpg|jpeg|png|webp)$/i, `._${token}_.$2`);
}
