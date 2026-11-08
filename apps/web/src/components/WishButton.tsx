"use client";

import { useState } from "react";
import { useStore } from "@/state/store";
import { useToast } from "./Toast";
import { Icon } from "./Icon";
import { useT } from "@/i18n/client";


export function WishButton({ id, small }: { id: string; small?: boolean }) {
  const { wishlist, toggleWish } = useStore();
  const toast = useToast();
  const { t } = useT();
  const on = wishlist.includes(id);
  // a short fill pop only on the shopper's own tap (never when the page loads with saved items)
  const [burst, setBurst] = useState(0);
  return (
    <button
      type="button"
      data-on={on}
      aria-pressed={on}
      aria-label={on ? t("wish.remove") : t("wish.save")}
      onClick={(e) => {
        e.preventDefault();
        toggleWish(id);
        if (!on) setBurst((b) => b + 1);
        toast({
          kind: on ? "info" : "success",
          icon: "heart",
          title: on ? t("wish.removed") : t("wish.saved"),
          action: on ? undefined : { label: t("wish.view"), href: "/wishlist" },
        });
      }}
      className={
        "heart relative grid place-items-center rounded-full bg-surface/90 backdrop-blur transition hover:bg-surface active:scale-[0.98] " +
        (small ? "h-9 w-9 after:absolute after:-inset-1 after:content-['']" : "h-11 w-11") +
        (on ? " text-ink" : " text-ink-3")
      }
    >
      <span key={burst} className={burst && on ? "pop inline-grid" : "inline-grid"}>
        <Icon name="heart" size={small ? 17 : 20} fill={on ? "currentColor" : "none"} />
      </span>
    </button>
  );
}
