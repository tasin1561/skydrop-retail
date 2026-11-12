// Variant grouping by dimension, same rules as apps/web/src/components/product/Variants.tsx.
import type { Variant } from "./types";
import { sizedImg } from "./img";

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

/**
 * A product photo at about the size it is shown. The token is the old Amazon one ("SL400", "SS112"): its number is
 * the wanted width in pixels, and every store's image server is asked for that size (lib/img.ts), so a card never
 * downloads a 1500 px photo.
 */
export function sized(url: string | null | undefined, token: string) {
  if (!url) return url ?? null;
  return sizedImg(url, Number(token.replace(/\D/g, "")) || 480);
}
