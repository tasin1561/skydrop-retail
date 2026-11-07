"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { STORES, STORE_MODES } from "@/lib/markets";
import { useStore } from "@/state/store";
import { Icon, Logo } from "./Icon";
import { SearchBox } from "./SearchBox";
import { MegaMenu } from "./categories/MegaMenu";
import { CategorySheetButton } from "./categories/CategorySheet";
import { NotificationBell } from "./notifications/NotificationBell";
import { useCurrentMode } from "./StoreTheme";
import { RequestHeaderLink } from "./shopping/RequestLinks";
import { LangSwitch } from "./LangSwitch";
import { useT } from "@/i18n/client";

export function StoreSwitcher({ className }: { className?: string }) {
  const mode = useCurrentMode();
  const { enabled } = useStore();
  const modes = STORE_MODES.filter((m) => m === "all" || enabled[m]);
  const { t } = useT();
  const wrap = useRef<HTMLDivElement>(null);
  const [pill, setPill] = useState({ x: 0, w: 0, ok: false });

  useEffect(() => {
    const el = wrap.current?.querySelector<HTMLElement>(`[data-mode="${mode}"]`);
    if (el) {
      setPill({ x: el.offsetLeft, w: el.offsetWidth, ok: true });
      const box = wrap.current!;
      box.scrollTo({ left: Math.max(0, el.offsetLeft - (box.clientWidth - el.offsetWidth) / 2), behavior: "smooth" });
    }
  }, [mode, modes.length]);

  return (
    <div
      ref={wrap}
      role="tablist"
      aria-label={t("header.chooseStore")}
      className={"no-scrollbar relative flex items-center gap-1 overflow-x-auto rounded-full bg-surface-2 p-1 " + (className ?? "")}
    >
      <span
        aria-hidden
        className="absolute bottom-1 top-1 w-[100px] origin-left rounded-full bg-accent shadow-[var(--glow)] transition-transform duration-[420ms]"
        style={{
          // transform only: travel with translateX, size with scaleX against a 100px base
          transform: `translateX(${pill.x - 4}px) scaleX(${pill.w / 100})`,
          opacity: pill.ok ? 1 : 0,
          transitionTimingFunction: "var(--spring)",
        }}
      />
      {modes.map((m) => {
        const on = m === mode;
        return (
          <Link
            key={m}
            data-mode={m}
            role="tab"
            aria-selected={on}
            href={m === "all" ? "/" : `/store/${m}`}
            className={
              "relative z-10 inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors sm:text-sm " +
              (on ? "text-white" : "text-ink-2 hover:text-ink")
            }
          >
            <Icon name={STORES[m].icon} size={15} stroke={2} />
            {m === "all" ? t("store.all.name") : STORES[m].name}
          </Link>
        );
      })}
    </div>
  );
}

export function Header() {
  const { cartCount, user, theme, setTheme } = useStore();
  const path = usePathname();
  const { t, digits } = useT();
  const [scrolled, setScrolled] = useState(false);
  useEffect(() => {
    const on = () => setScrolled(window.scrollY > 8);
    on();
    window.addEventListener("scroll", on, { passive: true });
    return () => window.removeEventListener("scroll", on);
  }, []);
  const checkout = path.startsWith("/checkout");

  return (
    <header
      className={
        "sticky top-0 z-40 border-b transition-[background,box-shadow,border-color] duration-300 " +
        (scrolled ? "border-line bg-surface/85 shadow-[var(--shadow-1)] backdrop-blur-md" : "border-transparent bg-bg")
      }
    >
      <div className="bg-gradient-to-r from-[var(--hero-a)] to-[var(--hero-b)] px-4 py-1.5 text-center text-[11px] font-medium text-white sm:text-xs">
        <Icon name="truck" size={13} className="mr-1 inline -mt-0.5" />
        <span className="sm:hidden">{t("header.strip.short")}</span>
        <span className="hidden sm:inline">{t("header.strip.long")}</span>
      </div>
      <div className="mx-auto flex max-w-7xl items-center gap-3 px-4 py-2.5">
        <Link href="/" aria-label={t("header.home")} className="shrink-0 text-[17px] sm:text-lg">
          <Logo />
        </Link>
        {!checkout && <MegaMenu />}
        {!checkout && (
          <div className="hidden min-w-0 flex-1 md:block">
            <SearchBox />
          </div>
        )}
        <div className="ml-auto flex items-center gap-1">
          {!checkout && <CategorySheetButton />}
          <NotificationBell />
          <LangSwitch size="sm" className="hidden lg:grid" />
          <button
            className="grid h-11 w-11 place-items-center rounded-full text-ink-2 transition hover:bg-surface-2 hover:text-ink"
            aria-label={t("header.darkMode")}
            onClick={() => setTheme(theme === "dark" ? "light" : "dark")}
          >
            <Icon name={theme === "dark" ? "sun" : "moon"} size={19} />
          </button>
          <Link
            href={user ? "/account" : "/login"}
            className="hidden h-11 items-center gap-2 rounded-full px-3 text-sm font-semibold text-ink-2 transition hover:bg-surface-2 hover:text-ink md:inline-flex"
          >
            <Icon name="user" size={19} />
            {user ? user.name.split(" ")[0] : t("nav.signIn")}
          </Link>
          <RequestHeaderLink />
          <Link
            href="/deals"
            className="hidden h-11 items-center gap-2 rounded-full px-3 text-sm font-semibold text-bad transition hover:bg-surface-2 md:inline-flex"
          >
            <Icon name="bolt" size={19} /> {t("nav.deals")}
          </Link>
          <Link
            href="/orders"
            className="hidden h-11 items-center gap-2 rounded-full px-3 text-sm font-semibold text-ink-2 transition hover:bg-surface-2 hover:text-ink lg:inline-flex"
          >
            <Icon name="receipt" size={19} /> {t("nav.orders")}
          </Link>
          <Link
            href="/cart"
            data-cart-target=""
            aria-label={cartCount > 0 ? t("nav.cartCount", { count: cartCount }) : t("nav.cart")}
            className="relative hidden h-11 items-center gap-2 rounded-full bg-accent px-4 text-sm font-semibold text-white shadow-[var(--glow)] transition hover:brightness-110 md:inline-flex"
          >
            <Icon name="cart" size={19} /> {t("nav.cart")}
            {cartCount > 0 && (
              <span key={cartCount} className="mo-badge pop grid h-5 min-w-5 place-items-center rounded-full bg-white px-1 text-[11px] font-bold text-accent">
                {digits(cartCount)}
              </span>
            )}
          </Link>
        </div>
      </div>
      {!checkout && (
        <div className="mx-auto max-w-7xl px-4 pb-2.5 md:hidden">
          <SearchBox />
        </div>
      )}
      {!checkout && (
        <div className="mx-auto max-w-7xl px-4 pb-2.5">
          <StoreSwitcher className="w-fit max-w-full" />
        </div>
      )}
    </header>
  );
}
