"use client";

import { usePathname, useSearchParams } from "next/navigation";
import { useEffect, useRef } from "react";
import { isStoreMode } from "@/lib/markets";
import { markStorePicked } from "@/lib/storePick";
import type { MarketId } from "@/lib/types";
import { useStore } from "@/state/store";

// Works out which store mode the shopper is in (from the URL, else their last choice)
// and sets data-store on <html> so the accent theme follows.
/** The store the URL itself names (/store/x, or ?store=x on search and category pages), else null. */
export function useUrlMode() {
  const path = usePathname();
  const sp = useSearchParams();
  const m = path.match(/^\/store\/([a-z]+)/)?.[1];
  if (isStoreMode(m)) return m;
  const q = sp.get("store");
  if ((path.startsWith("/search") || path.startsWith("/c/")) && isStoreMode(q)) return q;
  return null;
}

export function useCurrentMode() {
  const { mode } = useStore();
  return useUrlMode() ?? mode;
}

export function StoreTheme() {
  const mode = useCurrentMode();
  const urlMode = useUrlMode();
  const { ready, setMode, mode: saved } = useStore();
  // the URL's store is adopted once per URL change, so a switch made while still on the old URL is not undone
  const adopted = useRef<string | null>(null);
  useEffect(() => {
    document.documentElement.dataset.store = mode;
    if (!ready) return;
    if (!urlMode) {
      adopted.current = null;
      return;
    }
    if (adopted.current === urlMode) return;
    adopted.current = urlMode;
    if (urlMode !== saved) setMode(urlMode);
    // arriving on a store's own page (or a store-scoped link) is a choice: no first-visit picker
    if (urlMode !== "all") markStorePicked();
  }, [mode, urlMode, ready, saved, setMode]);
  return null;
}

/**
 * On a product page: if the shopper was browsing a different single store, follow the product's store so
 * the header switcher, search scope and accent match the item on screen. "All stores" stays as it is.
 */
export function FollowProductStore({ market }: { market: MarketId }) {
  const { ready, mode, enabled, setMode } = useStore();
  // once per product: a switch the shopper makes on this page afterwards is theirs to keep
  const followed = useRef<string | null>(null);
  useEffect(() => {
    if (!ready || followed.current === market) return;
    followed.current = market;
    if (mode !== "all" && mode !== market && enabled[market]) setMode(market);
  }, [ready, mode, market, enabled, setMode]);
  return null;
}
