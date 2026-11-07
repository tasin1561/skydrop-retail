"use client";

import "./shopping.css";
import Link from "next/link";
import { useState } from "react";
import { Icon } from "@/components/Icon";
import { ProductImage } from "@/components/ProductBits";
import { useToast } from "@/components/Toast";
import { formatBdt, priceBdt } from "@/lib/pricing";
import { cartLine } from "@/lib/shopping/cart";
import type { BoughtTogether as Data } from "@/lib/shopping/types";
import { useStore } from "@/state/store";

/** Frequently bought together: a checklist with a live total and "Add all N to cart". */
export function BoughtTogether({ data, canBuyAnchor }: { data: Data; canBuyAnchor: boolean }) {
  const { addToCart } = useStore();
  const toast = useToast();
  const all = [data.product, ...data.items];
  const [on, setOn] = useState<Record<string, boolean>>(() => Object.fromEntries(all.map((p, i) => [p.id, i > 0 || canBuyAnchor])));
  const picked = all.filter((p) => on[p.id]);
  const total = picked.reduce((s, p) => s + priceBdt(p), 0);

  function addAll() {
    for (const p of picked) addToCart(cartLine(p));
    toast({ kind: "success", title: `Added ${picked.length} ${picked.length === 1 ? "item" : "items"} to your cart`, body: formatBdt(total) + " in total" });
  }

  return (
    <section className="card p-4 sm:p-5" aria-labelledby="sh-fbt-h">
      <h2 id="sh-fbt-h" className="flex items-center gap-3 text-lg font-extrabold tracking-tight">
        <span className="chip-icon">
          <Icon name="basket" size={18} />
        </span>
        {data.source === "copurchase" ? "Frequently bought together" : "Goes well with"}
      </h2>
      <ul className="mt-3 flex flex-col gap-2">
        {all.map((p, i) => {
          const disabled = i === 0 && !canBuyAnchor;
          return (
            <li key={p.id}>
              <label
                data-off={!on[p.id]}
                className={"sh-check flex min-h-[64px] items-center gap-3 rounded-2xl bg-surface-2/70 p-2 pr-3 " + (disabled ? "cursor-not-allowed" : "cursor-pointer")}
              >
                <input
                  type="checkbox"
                  className="sr-only"
                  checked={!!on[p.id]}
                  disabled={disabled}
                  onChange={(e) => setOn((s) => ({ ...s, [p.id]: e.target.checked }))}
                />
                <span aria-hidden className={"grid h-6 w-6 shrink-0 place-items-center rounded-lg border-2 " + (on[p.id] ? "border-accent bg-accent text-white" : "border-line bg-surface")}>
                  <Icon name="check" size={14} stroke={3} className="sh-tick" />
                </span>
                <span className="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-white p-1">
                  <ProductImage p={p} className="h-full w-full" />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="line-clamp-2 text-[13px] font-medium leading-snug text-ink">
                    {i === 0 ? <b>This item: </b> : null}
                    {p.title}
                  </span>
                  {i > 0 && (
                    <Link href={`/p/${encodeURIComponent(p.id)}`} className="text-[11px] font-semibold text-accent hover:underline" onClick={(e) => e.stopPropagation()}>
                      View
                    </Link>
                  )}
                </span>
                <span className="shrink-0 text-sm font-bold tabular-nums">{formatBdt(priceBdt(p))}</span>
              </label>
            </li>
          );
        })}
      </ul>
      <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-ink-2">
          Total for {picked.length}:{" "}
          <span key={total} className="sh-bump text-lg font-extrabold tabular-nums text-ink">
            {formatBdt(total)}
          </span>
        </p>
        <button type="button" className="btn btn-primary min-h-11" disabled={!picked.length} onClick={addAll}>
          <Icon name="cart" size={18} /> Add {picked.length === all.length ? "all " : ""}
          {picked.length} to cart
        </button>
      </div>
    </section>
  );
}
