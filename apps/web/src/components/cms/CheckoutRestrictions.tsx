"use client";

import { useT } from "@/i18n/client";
import "./cms.css";
import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import type { CartItem, Restriction } from "@/lib/types";
import { API_URL, apiEnabled } from "@/lib/apiClient";
import { checkRestriction } from "@/lib/restrictions";
import { useFeature } from "@/components/features/FeaturesProvider";
import { Icon } from "@/components/Icon";

type Hit = Restriction & { productId: string; title: string };

/**
 * Checkout warning for items that cannot (or may not) ship to Bangladesh. API mode reads `restrictions` from
 * POST /checkout/quote; demo mode applies the demo rules to the item titles. The API also rejects blocked items on order.
 */
export function CheckoutRestrictions({ items }: { items: CartItem[] }) {
  const on = useFeature("restrictions");
  const { t } = useT();
  const key = items.map((i) => `${i.productId}:${i.qty}`).join(",");
  const local = useMemo(
    () => (apiEnabled ? [] : items.flatMap((i) => {
      const r = checkRestriction(i.title);
      return r ? [{ ...r, productId: i.productId, title: i.title }] : [];
    })),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [key],
  );
  const [remote, setRemote] = useState<Hit[]>([]);

  useEffect(() => {
    if (!on || !apiEnabled || !items.length) return;
    const ctrl = new AbortController();
    fetch(`${API_URL}/checkout/quote`, {
      method: "POST",
      signal: ctrl.signal,
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify({ items: items.map((i) => ({ productId: i.productId, variant: i.variant, qty: i.qty })) }),
    })
      .then((r) => (r.ok ? r.json() : null))
      .then((d: { restrictions?: Hit[] } | null) => setRemote(d?.restrictions ?? []))
      .catch(() => {});
    return () => ctrl.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [on, key]);

  const hits = apiEnabled ? remote : local;
  if (!on || !hits.length) return null;
  const blocked = hits.filter((h) => h.action === "block");

  return (
    <div
      role={blocked.length ? "alert" : "note"}
      className={
        "rx-note rounded-2xl p-3 text-sm ring-1 " +
        (blocked.length
          ? "bg-[color-mix(in_srgb,var(--bad)_10%,var(--surface))] ring-[color-mix(in_srgb,var(--bad)_30%,transparent)]"
          : "bg-[color-mix(in_srgb,var(--warn)_10%,var(--surface))] ring-[color-mix(in_srgb,var(--warn)_28%,transparent)]")
      }
    >
      <p className="flex items-center gap-2 font-extrabold">
        <Icon name={blocked.length ? "x" : "info"} size={17} className={blocked.length ? "text-bad" : "text-warn"} />
        {blocked.length ? t("restrict.blocked", { count: blocked.length }) : t("restrict.notes")}
      </p>
      <ul className="mt-2 flex flex-col gap-1.5">
        {hits.map((h) => (
          <li key={h.productId} className="text-xs leading-relaxed text-ink-2">
            <span className="font-semibold text-ink">{h.title.length > 70 ? h.title.slice(0, 70) + "…" : h.title}:</span> {h.message}
          </li>
        ))}
      </ul>
      {blocked.length > 0 && <p className="mt-2 text-xs font-semibold text-bad">{t("restrict.remove")}</p>}
      <Link href="/legal/shipping#restricted" className="mt-1 inline-flex min-h-11 items-center gap-1 text-xs font-bold text-accent">
        {t("restrict.whatWeShip")} <Icon name="right" size={14} />
      </Link>
    </div>
  );
}
