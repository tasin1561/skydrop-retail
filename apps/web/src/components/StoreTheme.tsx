"use client";

import { usePathname, useSearchParams } from "next/navigation";
import { useEffect } from "react";
import { isStoreMode } from "@/lib/markets";
import type { MarketId } from "@/lib/types";
import { useStore } from "@/state/store";

// Works out which store mode the shopper is in (from the URL, else their last choice)
// and sets data-store on <html> so the accent theme follows.
export function useCurrentMode() {
  const path = usePathname();
  const sp = useSearchParams();
  const { mode } = useStore();
  const m = path.match(/^\/store\/([a-z]+)/)?.[1];
  if (isStoreMode(m)) return m;
  const q = sp.get("store");
  if ((path.startsWith("/search") || path.startsWith("/c/")) && isStoreMode(q)) return q;
  return mode;
}

export function StoreTheme() {
  const mode = useCurrentMode();
  const { setMode, mode: saved } = useStore();
  useEffect(() => {
    document.documentElement.dataset.store = mode;
    if (mode !== saved) setMode(mode);
  }, [mode, saved, setMode]);
  return null;
}

/**
 * On a product page: if the shopper was browsing a different single store, follow the product's store so
 * the header switcher, search scope and accent match the item on screen. "All stores" stays as it is.
 */
export function FollowProductStore({ market }: { market: MarketId }) {
  const { ready, mode, enabled, setMode } = useStore();
  useEffect(() => {
    if (ready && mode !== "all" && mode !== market && enabled[market]) setMode(market);
  }, [ready, mode, market, enabled, setMode]);
  return null;
}
