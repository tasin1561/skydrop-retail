"use client";

import { useState } from "react";
import { useStore } from "@/state/store";
import { useToast } from "./Toast";
import { Icon } from "./Icon";

const SPARKS = [0, 60, 120, 180, 240, 300];

export function WishButton({ id, small }: { id: string; small?: boolean }) {
  const { wishlist, toggleWish } = useStore();
  const toast = useToast();
  const on = wishlist.includes(id);
  // burst only on the shopper's own tap (never when the page loads with saved items)
  const [burst, setBurst] = useState(0);
  return (
    <button
      type="button"
      data-on={on}
      aria-pressed={on}
      aria-label={on ? "Remove from wishlist" : "Save to wishlist"}
      onClick={(e) => {
        e.preventDefault();
        toggleWish(id);
        if (!on) setBurst((b) => b + 1);
        toast({
          kind: on ? "info" : "success",
          icon: "heart",
          title: on ? "Removed from wishlist" : "Saved to wishlist",
          action: on ? undefined : { label: "View wishlist", href: "/wishlist" },
        });
      }}
      className={
        "heart relative grid place-items-center rounded-full bg-surface/90 shadow-[var(--shadow-1)] backdrop-blur transition hover:scale-105 active:scale-90 " +
        (small ? "h-9 w-9 after:absolute after:-inset-1 after:content-['']" : "h-11 w-11") +
        (on ? " text-rose-500" : " text-ink-2")
      }
    >
      <span key={burst} className={burst && on ? "pop inline-grid" : "inline-grid"}>
        <Icon name="heart" size={small ? 17 : 20} fill={on ? "currentColor" : "none"} />
      </span>
      {burst > 0 && on && (
        <span key={"b" + burst} className="mo-burst" aria-hidden="true">
          {SPARKS.map((a, i) => (
            <i key={a} style={{ ["--a" as string]: `${a}deg`, ["--c" as string]: i % 2 ? "var(--brand-2)" : "rgb(244 63 94)" }} />
          ))}
        </span>
      )}
    </button>
  );
}
