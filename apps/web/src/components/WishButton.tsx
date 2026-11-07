"use client";

import { useStore } from "@/state/store";
import { useToast } from "./Toast";
import { Icon } from "./Icon";

export function WishButton({ id, small }: { id: string; small?: boolean }) {
  const { wishlist, toggleWish } = useStore();
  const toast = useToast();
  const on = wishlist.includes(id);
  return (
    <button
      type="button"
      data-on={on}
      aria-pressed={on}
      aria-label={on ? "Remove from wishlist" : "Save to wishlist"}
      onClick={(e) => {
        e.preventDefault();
        toggleWish(id);
        toast({ kind: on ? "info" : "success", title: on ? "Removed from wishlist" : "Saved to wishlist" });
      }}
      className={
        "heart grid place-items-center rounded-full bg-surface/90 shadow-[var(--shadow-1)] backdrop-blur transition hover:scale-105 " +
        (small ? "h-9 w-9" : "h-11 w-11") +
        (on ? " text-rose-500" : " text-ink-2")
      }
    >
      <Icon name="heart" size={small ? 17 : 20} fill={on ? "currentColor" : "none"} />
    </button>
  );
}
