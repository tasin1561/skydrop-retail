// Category matcher for demo mode: a port of apps/api/app/Services/Categories/CategoryMatcher.php.
// Pure and dependency-free (erasable TypeScript only) so scripts/build-category-tree.mjs can import it with Node's
// type stripping. Keep the weights in sync with the PHP version.

export type MatchNode = {
  path: string;
  name: string;
  keywords?: string[];
  stores?: string[];
  depth: number;
  hasChildren: boolean;
  parent: string | null;
};

export function normalize(s: string): string {
  return s
    .toLowerCase()
    .replace(/[’`]/g, "'")
    .replace(/'s\b/g, "s")
    .replace(/'/g, "")
    .replace(/[^a-z0-9]+/g, " ")
    .replace(/(?<=\d)(?=[a-z])|(?<=[a-z])(?=\d)/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

export function variants(kw: string): string[] {
  const out = [kw];
  if (kw.length < 3 || /\d$/.test(kw)) return out;
  if (kw.endsWith("ies")) out.push(kw.slice(0, -3) + "y");
  else if (/(ses|xes|ches|shes)$/.test(kw)) out.push(kw.slice(0, -2));
  else if (kw.endsWith("s") && !kw.endsWith("ss") && kw.length > 3) out.push(kw.slice(0, -1));
  else if (kw.endsWith("y") && !/[aeiou]y$/.test(kw)) out.push(kw.slice(0, -1) + "ies");
  else if (!kw.endsWith("s")) out.push(kw + "s");
  return out;
}

/** Title head (the product noun) and tail (variants, ingredients, compatibility). */
export function splitTitle(title: string): [string, string] {
  const m = /\s*(?:\||\(|\[|,|:|\s[-–—]\s|\swith\s|\sfor\s|\sw\/\s)/i.exec(title);
  return m && m.index > 0 ? [title.slice(0, m.index), title.slice(m.index)] : [title, ""];
}

type Indexed = { node: MatchNode; kws: { needles: string[]; words: number }[]; name: string };

export function buildIndex(nodes: MatchNode[]): Indexed[] {
  return nodes.map((node) => {
    const seen = new Set<string>();
    const kws: Indexed["kws"] = [];
    for (const raw of [node.name, ...(node.keywords ?? [])]) {
      const k = normalize(raw);
      if (!k || seen.has(k)) continue;
      const vs = variants(k).filter((v) => !seen.has(v));
      vs.forEach((v) => seen.add(v));
      kws.push({ needles: vs.map((v) => ` ${v} `), words: k.split(" ").length });
    }
    return { node, kws, name: normalize(node.name) };
  });
}

/** Deepest matching node path, or null. Same scoring as the API (see the PHP class doc). */
export function matchCategory(index: Indexed[], market: string | null, title: string, crumbs: string[] = []): string | null {
  return best(index, market, title, crumbs) ?? (market ? best(index, null, title, crumbs) : null);
}

function best(index: Indexed[], market: string | null, title: string, crumbs: string[]): string | null {
  const [head, tail] = splitTitle(title);
  const h = ` ${normalize(head)} `;
  const t = ` ${normalize(tail)} `;
  const cs = crumbs.map((c, i) => ({ hay: ` ${normalize(c)} `, plain: normalize(c), i })).filter((c) => c.plain);
  const own = new Map<string, number>();
  for (const { node, kws, name } of index) {
    if (market && !(node.stores ?? []).includes(market)) continue;
    let s = 0;
    const tf = node.hasChildren ? 0.5 : 1;
    for (const { needles, words } of kws) {
      let crumb = 0;
      let inTitle = 0;
      for (const n of needles) {
        for (const c of cs) if (c.hay.includes(n)) crumb = Math.max(crumb, 2 * words + 0.5 * c.i);
        if (h.includes(n)) inTitle = Math.max(inTitle, words);
        else if (t.includes(n)) inTitle = Math.max(inTitle, 0.4 * words);
      }
      s += crumb + tf * inTitle;
    }
    for (const c of cs) if (c.plain === name) s += 3 + 0.5 * c.i;
    if (s > 0) own.set(node.path, s);
  }
  const byPath = new Map(index.map((x) => [x.node.path, x.node]));
  let win: { path: string; score: number; depth: number } | null = null;
  for (const path of own.keys()) {
    let total = 0;
    for (let cur: MatchNode | undefined = byPath.get(path); cur; cur = cur.parent ? byPath.get(cur.parent) : undefined) total += own.get(cur.path) ?? 0;
    const depth = byPath.get(path)!.depth;
    if (!win || total > win.score + 1e-9 || (Math.abs(total - win.score) < 1e-9 && depth > win.depth)) win = { path, score: total, depth };
  }
  return win && win.score >= 0.8 ? win.path : null;
}
