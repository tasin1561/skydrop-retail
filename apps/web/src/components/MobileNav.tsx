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
  { href: "/account", label: "nav.account", icon: "user", match: (p: string) => p.startsWith("/account") || p.startsWith("/login") || p.startsWith("/wishlist") || p.startsWith("/notifications") },
];

// Bottom tab bar with a travelling bead that rises over the active tab.
export function MobileNav() {
  const path = usePathname();
  const { cartCount } = useStore();
  const { t, digits } = useT();
  // pages outside the five tabs (product, deals, help...) light none of them instead of pretending to be Home
  const found = ITEMS.findIndex((i) => i.match(path));
  const idx = Math.max(0, found);
  return (
    <nav
      aria-label={t("nav.main")}
      className="fixed inset-x-0 bottom-0 z-50 border-t border-line bg-surface/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-md md:hidden"
    >
      <div className="relative mx-auto grid max-w-md grid-cols-5">
        <span
          aria-hidden
          className="absolute top-0 h-0.5 w-8 rounded-full bg-accent transition-[transform,opacity] duration-200"
          style={{
            // active-tab indicator: travels on transform only; one cell is a fifth of the (max 28rem) bar
            left: "calc(10% - 16px)",
            transform: `translateX(calc(${idx} * min(100vw, 28rem) / 5))`,
            opacity: found < 0 ? 0 : 1,
            transitionTimingFunction: "var(--ease)",
          }}
        />
        {ITEMS.map((it, i) => {
          const on = i === found;
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
                  "relative grid h-7 w-7 place-items-center transition-colors duration-200 " +
                  (on ? "text-accent" : "text-ink-3")
                }
              >
                <Icon name={it.icon} size={22} stroke={on ? 2.2 : 1.8} />
                {it.href === "/cart" && cartCount > 0 && (
                  <span
                    key={cartCount}
                    className="mo-badge absolute -right-2 -top-1 grid h-4 min-w-4 place-items-center rounded-full bg-brand-2 px-1 text-[10px] text-white"
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
