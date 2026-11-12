"use client";

import { useCallback, useEffect, useState } from "react";
import { apiEnabled } from "@/lib/apiClient";
import { useLang } from "@/i18n/client";
import { catLabel, type CatNode } from "@/lib/catNode";
import type { StoreMode } from "@/lib/types";

// The tree is NOT part of the page's JavaScript (it used to be: 116 KB on every page). It is fetched once per page
// session in idle time, well before a shopper can open a menu: the live API tree, or the bundled copy as its own
// lazy chunk in demo mode or when the API is down.
let live: CatNode[] | null = null;
let loading: Promise<CatNode[]> | null = null;
const bundled = () => import("@/data/category-menu.json").then((m) => (m.default as unknown as { tree: CatNode[] }).tree);
function loadTree(): Promise<CatNode[]> {
  if (live) return Promise.resolve(live);
  loading ??= (apiEnabled
    ? fetch("/api/categories?store=all", { headers: { Accept: "application/json" } })
        .then((r) => (r.ok ? r.json() : null))
        .then((j: { items?: CatNode[] } | null) => (j?.items?.length ? j.items : bundled()))
        .catch(bundled)
    : bundled()
  )
    .then((t) => (live = t))
    .finally(() => {
      loading = null;
    });
  return loading;
}

export function filterTree(nodes: CatNode[], store: StoreMode): CatNode[] {
  if (store === "all") return nodes;
  return nodes.filter((n) => n.stores.includes(store)).map((n) => ({ ...n, children: filterTree(n.children, store) }));
}

/** Category tree for menus: empty for the first moments of a page, then the tree (loaded in idle time, or at once by `warm()`). */
export function useCategoryMenu(store: StoreMode) {
  const [tree, setTree] = useState<CatNode[]>(live ?? []);
  const warm = useCallback(() => {
    void loadTree().then(setTree).catch(() => {});
  }, []);
  useEffect(() => {
    // Desktop shows the menu button all the time: have the tree ready. Phones fetch it when the sheet opens (warm()).
    if (live || !window.matchMedia("(min-width: 1024px)").matches) return;
    const idle = typeof requestIdleCallback === "function" ? requestIdleCallback(warm, { timeout: 2500 }) : window.setTimeout(warm, 1200);
    return () => (typeof cancelIdleCallback === "function" ? cancelIdleCallback(idle) : clearTimeout(idle));
  }, [warm]);
  return { tree: filterTree(tree, store), warm };
}

/** Shoppers who switched the site to Bangla see Bangla category names (nameBn). */
export function useIsBn() {
  return useLang().lang === "bn";
}

export { catLabel };

export function catHref(path: string, store: StoreMode) {
  return `/c/${path}${store !== "all" ? `?store=${store}` : ""}`;
}
