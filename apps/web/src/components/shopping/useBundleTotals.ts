"use client";

import { useEffect, useMemo, useState } from "react";
import { useFeature } from "@/components/features/FeaturesProvider";
import { bundleSaving, type BundleSaving } from "@/lib/shopping/client";
import type { CartItem } from "@/lib/types";

type TotalsLike = { discount: number; total: number };

/** Saving from curated bundles in the cart (0 when none / feature off / API slow). */
export const bundleOf = (t: object) => ("bundleDiscount" in t ? Number((t as { bundleDiscount: unknown }).bundleDiscount) || 0 : 0);

/**
 * Adds the curated-bundle saving to cart totals (server value from POST /checkout/quote in API mode, the demo
 * bundles otherwise). The saving is part of `discount`; SummaryLines shows it as its own "Bundle saving" line.
 */
export function useBundleTotals<T extends TotalsLike>(t: T, items: CartItem[]): T & { bundleDiscount: number; bundleTitles: string[] } {
  const on = useFeature("bundles");
  const key = on ? items.map((i) => `${i.productId}:${i.qty}:${i.unitBdt}`).join("|") : "";
  const [saving, setSaving] = useState<{ key: string; data: BundleSaving } | null>(null);

  useEffect(() => {
    if (!key || items.length < 2) return;
    let live = true;
    const id = setTimeout(() => {
      bundleSaving(items)
        .then((data) => live && setSaving({ key, data }))
        .catch(() => live && setSaving({ key, data: { discount: 0, bundles: [] } }));
    }, 250);
    return () => {
      live = false;
      clearTimeout(id);
    };
    // items are captured through `key`
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  return useMemo(() => {
    const d = saving && saving.key === key ? Math.min(saving.data.discount, t.total) : 0;
    return { ...t, discount: t.discount + d, total: Math.max(0, t.total - d), bundleDiscount: d, bundleTitles: d ? saving!.data.bundles.map((b) => b.title) : [] };
  }, [t, saving, key]);
}
