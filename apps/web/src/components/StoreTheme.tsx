"use client";

import { usePathname, useSearchParams } from "next/navigation";
import { useEffect } from "react";
import { isStoreMode } from "@/lib/markets";
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
