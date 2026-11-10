"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useId, useRef, useState, useSyncExternalStore, type KeyboardEvent } from "react";
import { STORE_MODES, storeText } from "@/lib/markets";
import { isOn, type FeatureKey } from "@/lib/features";
import type { StoreMode } from "@/lib/types";
import { useStore } from "@/state/store";
import { useFeatures } from "@/components/features/FeaturesProvider";
import { useT } from "@/i18n/client";
import { useCurrentMode } from "../StoreTheme";
import { StoreName } from "./StoreName";
import { startRouteProgress } from "../motion/RouteProgress";

/** Store modes the shopper may pick: "all" plus every store that is on (marketplaces table AND the store.* feature). */
export function useEnabledModes(): StoreMode[] {
  const { enabled } = useStore();
  const f = useFeatures();
  return STORE_MODES.filter((m) => m === "all" || (enabled[m] && isOn(f, `store.${m}` as FeatureKey)));
}

// ---- screen-reader announcement ("Now shopping at Flipkart"), one polite live region in the header ----
let said = "";
const hear = new Set<() => void>();
export function announce(text: string) {
  // a trailing no-break space toggles so the same sentence twice is still read out
  said = said === text ? text + " " : text;
  hear.forEach((l) => l());
}
export function StoreLiveRegion() {
  const msg = useSyncExternalStore(
    (l) => {
      hear.add(l);
      return () => {
        hear.delete(l);
      };
    },
    () => said,
    () => "",
  );
  return (
    <p className="sr-only" role="status" aria-live="polite">
      {msg}
    </p>
  );
}

/**
 * Switch the store and go somewhere sensible: /store/x → /store/y (All → home), search and category pages keep
 * the query with the new store, everywhere else (home, product, cart, checkout…) only the mode changes.
 */
export function useSwitchStore() {
  const router = useRouter();
  const path = usePathname();
  const sp = useSearchParams();
  const { setMode } = useStore();
  const { t } = useT();
  return useCallback(
    (m: StoreMode) => {
      setMode(m);
      announce(t("header.nowShopping", { store: storeText(m, t).name }));
      let href: string | null = null;
      if (/^\/store\/[a-z]+/.test(path)) href = m === "all" ? "/" : `/store/${m}`;
      else if (path === "/search" || path.startsWith("/c/")) {
        const q = new URLSearchParams(sp.toString());
        q.set("store", m);
        q.delete("page");
        href = `${path}?${q.toString()}`;
      }
      if (href) {
        startRouteProgress(href);
        router.push(href, { scroll: !href.includes("?") });
      }
    },
    [path, sp, router, setMode, t],
  );
}

/** Arrow keys / Home / End move and select within a radiogroup (APG radio pattern). */
function radioKeys(e: KeyboardEvent<HTMLElement>, modes: StoreMode[], current: StoreMode, pick: (m: StoreMode) => void) {
  const i = modes.indexOf(current);
  let next = -1;
  if (e.key === "ArrowRight" || e.key === "ArrowDown") next = (i + 1) % modes.length;
  else if (e.key === "ArrowLeft" || e.key === "ArrowUp") next = (i - 1 + modes.length) % modes.length;
  else if (e.key === "Home") next = 0;
  else if (e.key === "End") next = modes.length - 1;
  if (next < 0) return;
  e.preventDefault();
  const group = e.currentTarget;
  group.querySelector<HTMLElement>(`[data-mode="${modes[next]}"]`)?.focus();
  pick(modes[next]);
}

/** Desktop/tablet: "Shopping at:" + segmented control with a sliding indicator in the active store's accent. */
function StoreSegmented() {
  const mode = useCurrentMode();
  const modes = useEnabledModes();
  const pick = useSwitchStore();
  const { t } = useT();
  const labelId = useId();
  const wrap = useRef<HTMLDivElement>(null);
  const [pill, setPill] = useState({ x: 0, w: 0, ok: false });
  const shown = modes.includes(mode) ? mode : "all";

  useEffect(() => {
    const box = wrap.current;
    const el = box?.querySelector<HTMLElement>(`[data-mode="${shown}"]`);
    if (!box || !el) return;
    const measure = () => setPill({ x: el.offsetLeft, w: el.offsetWidth, ok: true });
    measure();
    // labels change width when the web fonts (or the Bangla font) finish loading: keep the pill on its option
    const ro = new ResizeObserver(measure);
    ro.observe(el);
    return () => ro.disconnect();
  }, [shown, modes.length]);

  return (
    <div className="hidden items-center gap-3 md:flex">
      <span id={labelId} className="shrink-0 text-sm font-semibold text-ink-2">
        {t("header.shoppingAt")}
      </span>
      <div
        ref={wrap}
        role="radiogroup"
        aria-labelledby={labelId}
        onKeyDown={(e) => radioKeys(e, modes, shown, pick)}
        className="relative flex min-w-0 items-center gap-0.5 rounded-full bg-surface-2 p-1 ring-1 ring-line/60"
      >
        <span
          aria-hidden
          data-store={shown}
          className="sn seg-ind absolute bottom-1 left-0 top-1 w-[100px] origin-left rounded-full shadow-[0_1px_2px_rgb(0_0_0/0.12)]"
          style={{ transform: `translateX(${pill.x}px) scaleX(${pill.w / 100})`, opacity: pill.ok ? 1 : 0, background: "var(--sn-fill)" }}
        />
        {modes.map((m) => {
          const on = m === shown;
          const s = storeText(m, t);
          return (
            <button
              key={m}
              type="button"
              role="radio"
              aria-checked={on}
              tabIndex={on ? 0 : -1}
              data-mode={m}
              data-store={m}
              onClick={() => !on && pick(m)}
              className="sn relative z-10 inline-flex min-h-9 shrink-0 items-center whitespace-nowrap rounded-full px-3.5 text-sm focus-visible:outline-offset-1"
              // until the indicator is measured (server HTML) the checked option paints its own fill
              style={on && !pill.ok ? { background: "var(--sn-fill)" } : undefined}
            >
              <StoreName mode={m} variant="pill" fill={on} bare name={m === "all" ? t("search.allStores") : s.name} />
              {m !== "all" && <span className={"ml-1.5 hidden text-xs font-medium lg:inline " + (on ? "text-white/85" : "text-ink-2")}>· {s.source}</span>}
            </button>
          );
        })}
      </div>
    </div>
  );
}

/**
 * Phone: one row of store chips (All · Amazon · Flipkart · Myntra), one tap to switch. Replaces a "Shopping at ▾"
 * button + bottom sheet: two taps and a sheet were too slow for something shoppers do all the time.
 */
function StoreChips() {
  const mode = useCurrentMode();
  const modes = useEnabledModes();
  const pick = useSwitchStore();
  const { t } = useT();
  const shown = modes.includes(mode) ? mode : "all";
  const row = useRef<HTMLDivElement>(null);
  // keep the selected chip in view when the store changes (the row scrolls sideways on narrow phones)
  useEffect(() => {
    row.current?.querySelector<HTMLElement>('[aria-checked="true"]')?.scrollIntoView({ block: "nearest", inline: "nearest" });
  }, [shown]);
  return (
    <div
      ref={row}
      role="radiogroup"
      aria-label={t("header.shoppingAt")}
      className="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 md:hidden"
    >
      {modes.map((m) => {
        const on = m === shown;
        const s = storeText(m, t);
        return (
          <button
            key={m}
            type="button"
            role="radio"
            aria-checked={on}
            data-store={m}
            onClick={() => !on && pick(m)}
            className={
              "sn inline-flex min-h-10 shrink-0 items-center whitespace-nowrap rounded-full px-4 text-sm transition-[background-color,box-shadow] duration-200 active:scale-[0.97] " +
              (on ? "shadow-[var(--shadow-1)]" : "bg-surface ring-1 ring-line")
            }
            style={on ? { background: "var(--sn-fill)" } : undefined}
          >
            <StoreName mode={m} variant="pill" fill={on} bare name={m === "all" ? t("search.allStores") : s.name} />
          </button>
        );
      })}
    </div>
  );
}

/** The header store switcher: segmented control from md, compact button + bottom sheet on phones. */
export function StoreSwitcher() {
  const modes = useEnabledModes();
  if (modes.length < 2) return null;
  return (
    <>
      <StoreSegmented />
      <StoreChips />
    </>
  );
}
