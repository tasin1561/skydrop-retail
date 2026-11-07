"use client";

import Link from "next/link";
import { Icon } from "@/components/Icon";
import { useFeature } from "@/components/features/FeaturesProvider";
import { useT } from "@/i18n/client";

/** Header link to /request (feature "product_requests"). */
export function RequestHeaderLink() {
  const { t } = useT();
  if (!useFeature("product_requests")) return null;
  return (
    <Link
      href="/request?from=header"
      className="hidden h-11 items-center gap-2 rounded-full px-3 text-sm font-semibold text-ink-2 transition hover:bg-surface-2 hover:text-ink xl:inline-flex"
    >
      <Icon name="help" size={19} /> {t("req.header")}
    </Link>
  );
}

/** Search: "Can't find it? We'll source it" when there are no or few results. */
export function RequestCta({ q, count }: { q: string; count: number }) {
  const on = useFeature("product_requests");
  const { t } = useT();
  if (!on || count >= 6) return null;
  return (
    <div className="rise card mt-4 flex flex-wrap items-center gap-3 p-4">
      <span className="chip-icon shrink-0">
        <Icon name="search" size={18} />
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-bold">{count ? t("req.notQuite") : t("req.cantFind")}</p>
        <p className="text-xs text-ink-2">{t("req.ctaText")}</p>
      </div>
      <Link href={`/request?from=search&q=${encodeURIComponent(q)}`} className="btn btn-primary min-h-11">
        {q ? t("req.requestQ", { q: q.length > 24 ? q.slice(0, 22) + "…" : q }) : t("common.requestProduct")}
      </Link>
    </div>
  );
}
