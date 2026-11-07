"use client";

import { useCallback, useEffect, useState } from "react";
import menuData from "@/data/category-menu.json";
import { apiEnabled } from "@/lib/apiClient";
import type { CatNode } from "@/lib/categories";
import type { StoreMode } from "@/lib/types";

const BUNDLED = (menuData as unknown as { tree: CatNode[] }).tree;

// One in-flight / last-good copy of the live tree per page session (API mode only).
let live: CatNode[] | null = null;
let loading: Promise<CatNode[] | null> | null = null;
function loadLive(): Promise<CatNode[] | null> {
  if (live) return Promise.resolve(live);
  loading ??= fetch("/api/categories?store=all", { headers: { Accept: "application/json" } })
    .then((r) => (r.ok ? r.json() : null))
    .then((j: { items?: CatNode[] } | null) => (j?.items?.length ? (live = j.items) : null))
    .catch(() => null)
    .finally(() => {
      loading = null;
    });
  return loading;
}

export function filterTree(nodes: CatNode[], store: StoreMode): CatNode[] {
  if (store === "all") return nodes;
  return nodes.filter((n) => n.stores.includes(store)).map((n) => ({ ...n, children: filterTree(n.children, store) }));
}

/** Category tree for menus: the bundled copy immediately, replaced by the live API tree after `warm()` (API mode). */
export function useCategoryMenu(store: StoreMode) {
  const [tree, setTree] = useState<CatNode[]>(live ?? BUNDLED);
  const warm = useCallback(() => {
    if (!apiEnabled) return;
    void loadLive().then((t) => t && setTree(t));
  }, []);
  return { tree: filterTree(tree, store), warm };
}

/** Shoppers who switched the site to Bangla (<html lang="bn">) see Bangla names first. */
export function useIsBn() {
  const [bn, setBn] = useState(false);
  useEffect(() => {
    const read = () => setBn(document.documentElement.lang.toLowerCase().startsWith("bn"));
    read();
    const mo = new MutationObserver(read);
    mo.observe(document.documentElement, { attributes: true, attributeFilter: ["lang"] });
    return () => mo.disconnect();
  }, []);
  return bn;
}

export const catLabel = (n: Pick<CatNode, "name" | "nameBn">, bn: boolean) => (bn && n.nameBn ? n.nameBn : n.name);

export function catHref(path: string, store: StoreMode) {
  return `/c/${path}${store !== "all" ? `?store=${store}` : ""}`;
}
