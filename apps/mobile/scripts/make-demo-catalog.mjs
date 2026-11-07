// Builds the offline demo catalog for the app from the web storefront's catalog.
// Usage (from apps/mobile): node scripts/make-demo-catalog.mjs
// Keeps every detailed product and every flipkart/bigbasket demo item, then fills up to
// ~80 products with the most-reviewed amazon.in listings, spread across categories.
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const src = resolve(here, "../../web/src/data/catalog.json");
const out = resolve(here, "../src/demo/catalog.json");
const TARGET = 80;

const data = JSON.parse(readFileSync(src, "utf8"));
const all = data.products;

const keep = all.filter((p) => p.detailed || p.market !== "amazon");
const ids = new Set(keep.map((p) => p.id));

// Round-robin over categories so rails on every store have something to show.
const byCat = {};
for (const p of all.filter((p) => !ids.has(p.id)).sort((a, b) => b.reviews - a.reviews)) {
  (byCat[p.category] ??= []).push(p);
}
const cats = Object.keys(byCat);
let i = 0;
while (keep.length < TARGET && cats.some((c) => byCat[c].length)) {
  const c = cats[i++ % cats.length];
  const p = byCat[c].shift();
  if (p) keep.push(p);
}

// Trim fields the app never shows to keep the bundle small.
const slim = keep.map((p) => {
  const q = { ...p };
  if (!q.detailed) {
    delete q.aplus;
    delete q.reviewList;
    delete q.specs;
    delete q.bullets;
    q.images = (q.images ?? []).slice(0, 3);
  } else {
    q.aplus = (q.aplus ?? []).slice(0, 4);
    q.reviewList = (q.reviewList ?? []).slice(0, 8);
  }
  return q;
});

mkdirSync(dirname(out), { recursive: true });
writeFileSync(out, JSON.stringify({ generatedFrom: data.generatedFrom, products: slim }));
const count = slim.reduce((m, p) => ((m[p.market] = (m[p.market] ?? 0) + 1), m), {});
console.log(`Wrote ${slim.length} products to ${out}`, count);
