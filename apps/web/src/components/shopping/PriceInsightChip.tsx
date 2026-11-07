"use client";

import { Icon } from "@/components/Icon";
import { useT } from "@/i18n/client";
import { apiEnabled } from "@/lib/apiClient";
import { demoInsight } from "@/lib/shopping/history";
import type { Product } from "@/lib/types";

/** Product-card chip: "Lowest price in 30 days" / "Price dropped ৳X this week" (API `priceInsight`, seeded in demo mode). */
export function PriceInsightChip({ p }: { p: Product }) {
  const { t, bdt } = useT();
  let insight = p.priceInsight;
  if (insight === undefined && !apiEnabled) {
    try {
      insight = demoInsight(p);
    } catch {
      insight = null;
    }
  }
  if (!insight) return null;
  const lowest = insight.type === "lowest";
  return (
    <span
      className={
        "inline-flex w-fit items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] font-bold " +
        (lowest ? "bg-[color-mix(in_srgb,var(--good)_14%,transparent)] text-good" : "bg-[var(--accent-soft)] text-accent")
      }
    >
      <Icon name={lowest ? "verified" : "tag"} size={11} stroke={2.4} />
      {lowest ? t("insight.lowest") : t("insight.dropped", { amount: bdt(insight.dropBdt ?? 0) })}
    </span>
  );
}
