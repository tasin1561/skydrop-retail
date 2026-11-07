"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useStore } from "@/state/store";
import { Icon } from "./Icon";
import { useT } from "@/i18n/client";
import type { MsgKey } from "@/i18n/core";

const ITEMS: { href: string; label: MsgKey; icon: string; match: (p: string) => boolean }[] = [
  { href: "/", label: "nav.home", icon: "home", match: (p: string) => p === "/" || p.startsWith("/store") },
  { href: "/categories", label: "nav.categories", icon: "grid", match: (p: string) => p.startsWith("/categories") || p.startsWith("/c/") || p.startsWith("/search") },
  { href: "/cart", label: "nav.cart", icon: "cart", match: (p: string) => p.startsWith("/cart") || p.startsWith("/checkout") },
  { href: "/orders", label: "nav.orders", icon: "receipt", match: (p: string) => p.startsWith("/orders") },
  { href: "/account", label: "nav.account", icon: "user", match: (p: string) => p.startsWith("/account") || p.startsWith("/login") || p.startsWith("/wishlist") },
];

// Bottom tab bar with a travelling bead that rises over the active tab.
export function MobileNav() {
  const path = usePathname();
  const { cartCount } = useStore();
  const { t, digits } = useT();
  const idx = Math.max(0, ITEMS.findIndex((i) => i.match(path)));
  return (
    <nav
      aria-label={t("nav.main")}
      className="fixed inset-x-0 bottom-0 z-50 border-t border-line bg-surface/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-md md:hidden"
    >
      <div className="relative mx-auto grid max-w-md grid-cols-5">
        <span
          aria-hidden
          className="absolute -top-3 h-12 w-12 rounded-full bg-accent shadow-[var(--glow)] transition-transform duration-500"
          style={{
            // travels on transform only: one cell is a fifth of the (max 28rem) bar
            left: "calc(10% - 24px)",
            transform: `translateX(calc(${idx} * min(100vw, 28rem) / 5))`,
            transitionTimingFunction: "var(--spring)",
          }}
        />
        {ITEMS.map((it, i) => {
          const on = i === idx;
          return (
            <Link
              key={it.href}
              href={it.href}
              aria-current={on ? "page" : undefined}
              className="relative flex h-16 flex-col items-center justify-end gap-0.5 pb-2 text-[11px] font-semibold"
            >
              <span
                data-cart-target={it.href === "/cart" ? "" : undefined}
                className={
                  "relative grid h-7 w-7 place-items-center transition-all duration-500 " +
                  (on ? "-translate-y-4 text-white" : "text-ink-3")
                }
                style={{ transitionTimingFunction: "var(--spring)" }}
              >
                <Icon name={it.icon} size={22} stroke={on ? 2.2 : 1.8} />
                {it.href === "/cart" && cartCount > 0 && (
                  <span
                    key={cartCount}
                    className="mo-badge pop absolute -right-2 -top-1 grid h-4 min-w-4 place-items-center rounded-full bg-brand-2 px-1 text-[10px] text-white"
                  >
                    {digits(cartCount)}
                  </span>
                )}
              </span>
              <span className={on ? "text-accent" : "text-ink-3"}>{t(it.label)}</span>
            </Link>
          );
        })}
      </div>
    </nav>
  );
}
