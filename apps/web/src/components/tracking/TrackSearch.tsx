"use client";

import { useEffect } from "react";
import { track } from "@/lib/track";

/** Search event for a results page (only for a typed query). Renders nothing. */
export function TrackSearch({ q, store, ids }: { q: string; store?: string; ids?: string[] }) {
  const query = q.trim();
  const key = (ids ?? []).slice(0, 10).join(",");
  useEffect(() => {
    if (!query) return;
    track("Search", {
      search: query,
      items: key ? key.split(",").map((id) => ({ id, category: store && store !== "all" ? store : undefined })) : undefined,
    });
  }, [query, store, key]);
  return null;
}
