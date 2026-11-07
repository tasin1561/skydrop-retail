"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { prettyQuery } from "@/lib/brands";

/**
 * Patch the current URL's query (the URL is the filter state): null/"" removes a key, page resets,
 * /search keeps its ?q= so the page stays a browse page. Pushes a history entry so back/forward walk the filters.
 */
export function useParamsUpdater() {
  const router = useRouter();
  const path = usePathname();
  const sp = useSearchParams();
  return (patch: Record<string, string | null>, { replace = false }: { replace?: boolean } = {}) => {
    const next = new URLSearchParams(sp.toString());
    for (const [k, v] of Object.entries(patch)) {
      if (v === null || v === "") next.delete(k);
      else next.set(k, v);
    }
    next.delete("page");
    if (path === "/search" && !next.has("q")) next.set("q", "");
    const qs = prettyQuery(next);
    const url = qs ? `${path}?${qs}` : path;
    if (replace) router.replace(url, { scroll: false });
    else router.push(url, { scroll: false });
  };
}
