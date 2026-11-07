"use client";

import "./shopping.css";
import Link from "next/link";
import { useEffect, useState } from "react";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { useFeature } from "@/components/features/FeaturesProvider";
import { STORES } from "@/lib/markets";
import { cartLine } from "@/lib/shopping/cart";
import { cheaperListings, groupSavingPreview } from "@/lib/shopping/client";
import type { Alternative } from "@/lib/shopping/types";
import type { CartItem } from "@/lib/types";
import { useStore } from "@/state/store";
import { useT } from "@/i18n/client";

/** Cart helpers: "same item is cheaper on another store" switch, and a group-buy invite to share shipping. */
export function CartShoppingExtras({ items }: { items: CartItem[] }) {
  const compareOn = useFeature("compare_stores");
  const groupsOn = useFeature("group_orders");
  const { removeFromCart, addToCart } = useStore();
  const toast = useToast();
  const { t, bdt } = useT();
  const [alts, setAlts] = useState<{ key: string; list: Alternative[] } | null>(null);
  const key = items.map((i) => `${i.productId}:${i.qty}`).join("|");

  useEffect(() => {
    if (!compareOn || !key) return;
    let live = true;
    const id = setTimeout(() => {
      cheaperListings(items)
        .then((list) => live && setAlts({ key, list }))
        .catch(() => live && setAlts({ key, list: [] }));
    }, 300);
    return () => {
      live = false;
      clearTimeout(id);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, compareOn]);

  const list = alts && alts.key === key ? alts.list : [];
  // Biggest single-store basket is the natural group-buy candidate.
  const byMarket = new Map<string, CartItem[]>();
  for (const i of items) byMarket.set(i.market, [...(byMarket.get(i.market) ?? []), i]);
  const [market, mine] = [...byMarket.entries()].sort((a, b) => b[1].length - a[1].length)[0] ?? [];
  const groupSave = groupsOn && mine ? groupSavingPreview(mine) : 0;

  function switchTo(a: Alternative) {
    const line = items.find((i) => i.productId === a.productId);
    removeFromCart(a.productId, line?.variant);
    addToCart(cartLine(a.alternative, a.qty));
    toast({ kind: "success", title: t("alt.switched", { store: STORES[a.alternativeMarket].name }), body: t("alt.save", { amount: bdt(a.savingBdt) }) });
  }

  if (!list.length && !groupSave) return null;
  return (
    <div className="flex flex-col gap-3">
      {list.map((a) => (
        <div key={a.productId} className="rise card flex flex-wrap items-center gap-3 border border-[color-mix(in_srgb,var(--good)_35%,transparent)] p-3 sm:p-4">
          <span className="chip-icon shrink-0 bg-[color-mix(in_srgb,var(--good)_14%,transparent)] text-good">
            <Icon name="tag" size={18} />
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-sm font-bold">
              {t("alt.cheaper", { amount: bdt(a.savingBdt), store: STORES[a.alternativeMarket].name })}
            </p>
            <p className="line-clamp-1 text-xs text-ink-2">
              {t("alt.instead", { title: a.title, price: bdt(a.alternativeBdt), old: bdt(a.unitBdt), min: a.delivery.min, max: a.delivery.max })}
            </p>
          </div>
          <div className="flex gap-2">
            <Link href={`/p/${encodeURIComponent(a.alternativeId)}`} className="btn btn-ghost min-h-11">
{t("common.view")}
</Link>
            <button type="button" className="btn btn-primary min-h-11" onClick={() => switchTo(a)}>
{t("alt.switch")}
</button>
          </div>
        </div>
      ))}
      {groupSave > 0 && market && (
        <Link
          href={`/group?market=${market}`}
          className="card group flex items-center gap-3 p-3 transition-transform duration-300 hover:-translate-y-0.5 sm:p-4"
        >
          <span className="chip-icon shrink-0">
            <Icon name="user" size={18} />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block text-sm font-bold">{t("grp.share")}</span>
            <span className="block text-xs text-ink-2">
              {t("grp.startOn", { store: STORES[market as keyof typeof STORES].name, amount: bdt(groupSave) })}
            </span>
          </span>
          <Icon name="arrow" size={18} className="shrink-0 text-accent transition-transform group-hover:translate-x-1" />
        </Link>
      )}
    </div>
  );
}
