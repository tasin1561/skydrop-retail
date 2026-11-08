"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { useStore } from "@/state/store";
import { Icon, Logo } from "./Icon";
import { SearchBox } from "./SearchBox";
import { MegaMenu } from "./categories/MegaMenu";
import { CategorySheetButton } from "./categories/CategorySheet";
import { NotificationBell } from "./notifications/NotificationBell";
import { StoreLiveRegion, StoreSwitcher } from "./store/StoreSwitch";
import { RequestHeaderLink } from "./shopping/RequestLinks";
import { LangSwitch } from "./LangSwitch";
import { useT } from "@/i18n/client";

export function Header() {
  const { cartCount, user, theme, setTheme } = useStore();
  const path = usePathname();
  const { t, digits } = useT();
  const [scrolled, setScrolled] = useState(false);
  const bar = useRef<HTMLElement>(null);
  // publish the sticky header height so page-level sticky bars (quick filters) sit right under it
  useEffect(() => {
    const el = bar.current;
    if (!el || typeof ResizeObserver === "undefined") return;
    const root = document.documentElement;
    const set = () => root.style.setProperty("--hdr-h", `${Math.round(el.getBoundingClientRect().height)}px`);
    set();
    const ro = new ResizeObserver(set);
    ro.observe(el);
    return () => ro.disconnect();
  }, []);
  useEffect(() => {
    const on = () => setScrolled(window.scrollY > 8);
    on();
    window.addEventListener("scroll", on, { passive: true });
    return () => window.removeEventListener("scroll", on);
  }, []);
  const checkout = path.startsWith("/checkout");

  return (
    <header
      ref={bar}
      className={
        // one clean white bar; the only per-store colour is the 2px rule on top (transparent on All stores)
        "sticky top-0 z-40 border-b border-t-2 border-t-[var(--store-rule)] bg-surface transition-[border-color,box-shadow] duration-200 " +
        (scrolled ? "border-b-line shadow-[0_1px_0_var(--line)]" : "border-b-line")
      }
    >
      <div className="border-b border-line bg-surface-2 px-4 py-1 text-center text-[11px] text-ink-2 sm:text-xs">
        <Icon name="truck" size={13} className="mr-1.5 inline -mt-0.5 text-ink-3" />
        <span className="sm:hidden">{t("header.strip.short")}</span>
        <span className="hidden sm:inline">{t("header.strip.long")}</span>
      </div>
      <div className="mx-auto flex max-w-7xl items-center gap-3 px-4 py-2.5">
        <Link href="/" aria-label={t("header.home")} className="shrink-0 text-[16px] sm:text-[17px]">
          <Logo />
        </Link>
        {!checkout && <MegaMenu />}
        {!checkout && (
          <div className="hidden min-w-0 flex-1 lg:block">
            <SearchBox />
          </div>
        )}
        <div className="ml-auto flex items-center gap-1">
          {!checkout && <CategorySheetButton />}
          <NotificationBell />
          <LangSwitch size="sm" className="hidden xl:grid" />
          <button
            className="grid h-11 w-11 place-items-center rounded-[8px] text-ink-2 transition hover:bg-surface-2 hover:text-ink"
            aria-label={t("header.darkMode")}
            onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
          >
            <Icon name={theme === "dark" ? "sun" : "moon"} size={19} />
          </button>
          {/* below 2xl the account, request and orders links are icon-only so the search box keeps its width */}
          <Link
            href={user ? "/account" : "/login"}
            aria-label={user ? t("nav.account") : t("nav.signIn")}
            title={user ? user.name : t("nav.signIn")}
            className="hidden h-11 min-w-11 items-center justify-center gap-1.5 rounded-[8px] px-2.5 text-sm font-medium text-ink-2 transition hover:bg-surface-2 hover:text-ink md:inline-flex"
          >
            <Icon name="user" size={19} />
            <span className="hidden max-w-[8rem] truncate xl:inline">{user ? user.name.split(" ")[0] : t("nav.signIn")}</span>
          </Link>
          <RequestHeaderLink />
          <Link
            href="/deals"
            className="hidden h-11 items-center gap-1.5 rounded-[8px] px-2.5 text-sm font-medium text-ink-2 transition hover:bg-surface-2 hover:text-ink md:inline-flex"
          >
            <Icon name="tag" size={18} /> {t("nav.deals")}
          </Link>
          <Link
            href="/orders"
            aria-label={t("nav.orders")}
            title={t("nav.orders")}
            className="hidden h-11 min-w-11 items-center justify-center gap-1.5 rounded-[8px] px-2.5 text-sm font-medium text-ink-2 transition hover:bg-surface-2 hover:text-ink lg:inline-flex"
          >
            <Icon name="receipt" size={19} /> <span className="hidden xl:inline">{t("nav.orders")}</span>
          </Link>
          <Link
            href="/cart"
            data-cart-target=""
            aria-label={cartCount > 0 ? t("nav.cartCount", { count: cartCount }) : t("nav.cart")}
            className="relative ml-1 hidden h-11 items-center gap-2 rounded-[8px] bg-surface px-3.5 text-sm font-medium text-ink shadow-[inset_0_0_0_1px_var(--line-2)] transition-colors hover:bg-surface-2 md:inline-flex"
          >
            <Icon name="cart" size={19} /> {t("nav.cart")}
            {cartCount > 0 && (
              <span key={cartCount} className="mo-badge grid h-5 min-w-5 place-items-center rounded-full bg-primary px-1 text-[11px] font-medium tabular-nums text-on-primary">
                {digits(cartCount)}
              </span>
            )}
          </Link>
        </div>
      </div>
      {!checkout && (
        // tablets get the full-width search row too; inline it is too narrow to type in below lg
        <div className="mx-auto max-w-7xl px-4 pb-2.5 lg:hidden">
          <SearchBox />
        </div>
      )}
      {!checkout && (
        <div className="mx-auto max-w-7xl px-4 pb-2.5 empty:hidden">
          <StoreSwitcher />
        </div>
      )}
      <StoreLiveRegion />
    </header>
  );
}
