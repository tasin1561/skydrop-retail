"use client";

import { Icon } from "@/components/Icon";
import { formatBdt } from "@/lib/pricing";
import { bundleOf } from "./useBundleTotals";
import { useT } from "@/i18n/client";

/** "Bundle saving" line for SummaryLines (renders nothing without a bundle in the cart). */
export function BundleSavingRow({ t }: { t: object }) {
  const { t: tr, bdt } = useT();
  const d = bundleOf(t);
  if (!d) return null;
  const titles = "bundleTitles" in t ? (t.bundleTitles as string[]) : [];
  return (
    <div className="flex justify-between gap-3 text-good">
      <dt className="inline-flex min-w-0 items-center gap-1.5">
        <Icon name="package" size={14} stroke={2} /> <span className="truncate">{tr("bundle.saving")}{titles.length ? ` · ${titles.join(", ")}` : ""}</span>
      </dt>
      <dd key={d} className="co-bump font-semibold tabular-nums">
        −{bdt(d)}
      </dd>
    </div>
  );
}
