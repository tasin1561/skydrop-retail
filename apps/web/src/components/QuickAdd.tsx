"use client";

import type { Product } from "@/lib/types";
import { priceBdt, weightKg } from "@/lib/pricing";
import { useStore } from "@/state/store";
import { Icon } from "./Icon";

// Grocery-style add-to-basket on the card: "Add" becomes a +/- stepper.
export function QuickAdd({ p }: { p: Product }) {
  const { cart, addToCart, setQty, removeFromCart } = useStore();
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
  if (!line)
    return (
      <button
        type="button"
        onClick={(e) => {
          e.preventDefault();
          add();
        }}
        className="btn btn-primary mt-2 min-h-10 w-full rounded-xl text-sm"
        aria-label={`Add ${p.title} to basket`}
      >
        <Icon name="plus" size={16} stroke={2.4} /> Add
      </button>
    );
  return (
    <div className="pop mt-2 flex min-h-10 items-center justify-between rounded-xl bg-accent text-white shadow-[var(--glow)]" onClick={(e) => e.preventDefault()}>
      <button
        type="button"
        className="grid h-10 w-11 place-items-center"
        aria-label="One less"
        onClick={() => (line.qty <= 1 ? removeFromCart(p.id) : setQty(p.id, undefined, line.qty - 1))}
      >
        <Icon name={line.qty <= 1 ? "trash" : "minus"} size={16} stroke={2.4} />
      </button>
      <span key={line.qty} className="pop text-sm font-extrabold tabular-nums" aria-live="polite">
        {line.qty} in basket
      </span>
      <button type="button" className="grid h-10 w-11 place-items-center" aria-label="One more" onClick={() => setQty(p.id, undefined, line.qty + 1)}>
        <Icon name="plus" size={16} stroke={2.4} />
      </button>
    </div>
  );
}
