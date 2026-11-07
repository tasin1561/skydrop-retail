"use client";

import Link from "next/link";
import { Icon } from "@/components/Icon";
import { useFeature } from "@/components/features/FeaturesProvider";

/** Header link to /request (feature "product_requests"). */
export function RequestHeaderLink() {
  if (!useFeature("product_requests")) return null;
  return (
    <Link
      href="/request?from=header"
      className="hidden h-11 items-center gap-2 rounded-full px-3 text-sm font-semibold text-ink-2 transition hover:bg-surface-2 hover:text-ink xl:inline-flex"
    >
      <Icon name="help" size={19} /> Request
    </Link>
  );
}

/** Search: "Can't find it? We'll source it" when there are no or few results. */
export function RequestCta({ q, count }: { q: string; count: number }) {
  const on = useFeature("product_requests");
  if (!on || count >= 6) return null;
  return (
    <div className="rise card mt-4 flex flex-wrap items-center gap-3 p-4">
      <span className="chip-icon shrink-0">
        <Icon name="search" size={18} />
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-bold">{count ? "Not quite what you wanted?" : "Can't find it?"}</p>
        <p className="text-xs text-ink-2">Paste a link or describe it, and we&apos;ll source it from India and send you a price.</p>
      </div>
      <Link href={`/request?from=search&q=${encodeURIComponent(q)}`} className="btn btn-primary min-h-11">
        Request {q ? `"${q.length > 24 ? q.slice(0, 22) + "…" : q}"` : "a product"}
      </Link>
    </div>
  );
}
