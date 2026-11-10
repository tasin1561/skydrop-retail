// Generates the demo-mode copies of the category tree from the API's source of truth.
// Usage (from apps/web): node scripts/build-category-tree.mjs   (Node >= 22.18: imports the .ts matcher directly)
//
//   apps/api/database/data/category_tree.json  ->  src/data/category-tree.json    (server: names, icons, stores, keywords)
//                                               ->  src/data/category-menu.json    (client menus: no keywords, compact)
//                                               ->  ../mobile/src/demo/category-tree.json + category-map.json
//                                                   (mobile demo: tree + productId -> path for its demo catalog)
import { readFileSync, writeFileSync, existsSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { buildIndex, matchCategory } from "../src/lib/categoryMatch.ts";

const here = dirname(fileURLToPath(import.meta.url));
const src = resolve(here, "../../api/database/data/category_tree.json");
const doc = JSON.parse(readFileSync(src, "utf8"));

const STORES = ["amazon", "flipkart", "myntra"];
function convert(n, depth, group) {
  const g = n.pricing_group ?? group;
  return {
    slug: n.slug,
    path: n.path,
    name: n.name,
    nameBn: n.name_bn,
    icon: n.icon,
    depth,
    group: g,
    stores: STORES.filter((s) => n.sources?.[s]),
    ...(n.featured ? { featured: true } : {}),
    keywords: n.keywords ?? [],
    children: (n.children ?? []).map((c) => convert(c, depth + 1, g)),
  };
}
const tree = doc.tree.map((n) => convert(n, 1, "home"));
const strip = ({ keywords: _k, group: _g, ...n }) => ({ ...n, children: n.children.map(strip) });
const menu = tree.map(strip);

writeFileSync(resolve(here, "../src/data/category-tree.json"), JSON.stringify({ generatedFrom: "apps/api/database/data/category_tree.json", tree }));
writeFileSync(resolve(here, "../src/data/category-menu.json"), JSON.stringify({ tree: menu }));

// Mobile demo copy + product map for its bundled catalog.
const flat = [];
const walk = (n, parent) => {
  flat.push({ path: n.path, name: n.name, keywords: n.keywords, stores: n.stores, depth: n.depth, hasChildren: n.children.length > 0, parent });
  n.children.forEach((c) => walk(c, n.path));
};
tree.forEach((n) => walk(n, null));
const index = buildIndex(flat);
const mobileDir = resolve(here, "../../mobile/src/demo");
if (existsSync(mobileDir)) {
  writeFileSync(resolve(mobileDir, "category-tree.json"), JSON.stringify({ tree: menu }));
  const catalog = JSON.parse(readFileSync(resolve(mobileDir, "catalog.json"), "utf8"));
  const map = {};
  for (const p of catalog.products) {
    const path = matchCategory(index, p.market, p.title, p.categoryPath ?? []);
    if (path) map[p.id] = path;
  }
  writeFileSync(resolve(mobileDir, "category-map.json"), JSON.stringify(map));
  console.log(`mobile: ${Object.keys(map).length}/${catalog.products.length} demo products mapped`);
}
const count = (ns) => ns.reduce((a, n) => a + 1 + count(n.children), 0);
console.log(`category tree: ${count(tree)} nodes, ${tree.length} top level`);
