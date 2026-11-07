"use client";

import { useEffect, useRef, useState } from "react";
import type { Product } from "@/lib/types";
import { priceBdt, weightKg } from "@/lib/pricing";
import { useStore } from "@/state/store";
import { Icon } from "./Icon";
import { restrictionOf } from "@/lib/restrictions";
import { flyToCart } from "./motion/flyToCart";
import { useT } from "@/i18n/client";

// Grocery-style add-to-basket on the card: "Add" -> the product arcs into the cart -> a +/- stepper.
export function QuickAdd({ p }: { p: Product }) {
  const { cart, addToCart, setQty, removeFromCart } = useStore();
  const [busy, setBusy] = useState(false);
  const { t } = useT();
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  useEffect(() => () => {
    if (timer.current) clearTimeout(timer.current);
  }, []);
  const line = cart.find((c) => c.productId === p.id && !c.variant);
  const add = () =>
    addToCart({
      productId: p.id,
      market: p.market,
      title: p.title,
      image: p.image,
      placeholder: p.placeholder,
      unitBdt: priceBdt(p),
      weightKg: weightKg(p),
      qty: 1,
    });
  const blocked = restrictionOf(p)?.action === "block";
  if (blocked)
    return (
      <button type="button" disabled className="btn btn-ghost mt-2 min-h-10 w-full rounded-xl text-xs" aria-label={t("qa.cantShip", { title: p.title })}>
        {t("qa.cantShipShort")}
      </button>
    );
  if (!line)
    return (
      <button
        type="button"
        onClick={(e) => {
          e.preventDefault();
          if (busy) return;
          setBusy(true);
          const from = e.currentTarget.closest("article")?.querySelector("img") ?? e.currentTarget;
          let done = false;
          const land = () => {
            if (done) return;
            done = true;
            add();
            setBusy(false);
          };
          const ms = flyToCart(from, p.image, land);
          timer.current = setTimeout(land, Math.max(ms, 0) + 200);
        }}
        aria-busy={busy}
        className="btn btn-primary mt-2 min-h-11 w-full rounded-xl text-sm"
        aria-label={t("qa.addTo", { title: p.title })}
      >
        <span className="roll" aria-hidden="true">
          <span data-on={!busy} data-gone={busy} className="inline-flex items-center gap-1.5">
            <Icon name="plus" size={16} stroke={2.4} /> {t("qa.add")}
          </span>
          <span data-on={busy} className="inline-flex items-center gap-1.5">
            <Icon name="package" size={16} stroke={2.2} /> {t("qa.adding")}
          </span>
        </span>
      </button>
    );
  return (
    <div className="pop mt-2 flex min-h-11 items-center justify-between rounded-xl bg-accent text-white shadow-[var(--glow)]" onClick={(e) => e.preventDefault()}>
      <button
        type="button"
        className="grid h-11 w-11 place-items-center rounded-xl transition-transform active:scale-90"
        aria-label={line.qty <= 1 ? t("qa.remove", { title: p.title }) : t("qa.less")}
        onClick={() => (line.qty <= 1 ? removeFromCart(p.id) : setQty(p.id, undefined, line.qty - 1))}
      >
        <Icon name={line.qty <= 1 ? "trash" : "minus"} size={16} stroke={2.4} />
      </button>
      <span key={line.qty} className="pop text-sm font-extrabold tabular-nums" aria-live="polite">
        {t("qa.inBasket", { count: line.qty })}
      </span>
      <button
        type="button"
        className="grid h-11 w-11 place-items-center rounded-xl transition-transform active:scale-90"
        aria-label={t("qa.more")}
        onClick={() => setQty(p.id, undefined, line.qty + 1)}
      >
        <Icon name="plus" size={16} stroke={2.4} />
      </button>
    </div>
  );
}
