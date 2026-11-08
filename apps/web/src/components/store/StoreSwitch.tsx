"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useId, useRef, useState, useSyncExternalStore, type KeyboardEvent } from "react";
import { STORE_MODES, storeText } from "@/lib/markets";
import { isOn, type FeatureKey } from "@/lib/features";
import type { StoreMode } from "@/lib/types";
import { useStore } from "@/state/store";
import { useFeatures } from "@/components/features/FeaturesProvider";
import { useT } from "@/i18n/client";
import { Icon } from "../Icon";
import { useCurrentMode } from "../StoreTheme";
import { StoreName } from "./StoreName";
import { startRouteProgress } from "../motion/RouteProgress";
import { ModalShell, useClosing } from "./ModalShell";

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

/** Phone: "Shopping at: Amazon ▾" button that opens a bottom sheet with the choices. */
function StoreSwitchButton() {
  const mode = useCurrentMode();
  const modes = useEnabledModes();
  const { t } = useT();
  const [open, setOpen] = useState(false);
  const shown = modes.includes(mode) ? mode : "all";
  const s = storeText(shown, t);
  return (
    <>
      <button
        type="button"
        aria-haspopup="dialog"
        aria-expanded={open}
        onClick={() => setOpen(true)}
        className="flex min-h-11 max-w-full items-center gap-2 rounded-full bg-surface-2 py-1.5 pl-3 pr-2.5 text-sm ring-1 ring-line/60 transition-colors duration-200 active:bg-line/40 md:hidden"
      >
        <span className="shrink-0 font-medium text-ink-2">{t("header.shoppingAt")}</span>
        <StoreName mode={shown} variant="pill" name={shown === "all" ? t("search.allStores") : s.name} className="min-w-0 truncate" />
        <Icon name="chevron" size={16} className="shrink-0 text-ink-3" />
      </button>
      {open && <StoreSheet onClose={() => setOpen(false)} current={shown} modes={modes} />}
    </>
  );
}

function StoreSheet({ onClose, current, modes }: { onClose: () => void; current: StoreMode; modes: StoreMode[] }) {
  const { t } = useT();
  const pick = useSwitchStore();
  const titleId = useId();
  const { closing, close } = useClosing(onClose);
  const checked = useRef<HTMLButtonElement>(null);
  const choose = (m: StoreMode) => {
    if (m !== current) pick(m);
    close();
  };
  return (
    <ModalShell labelledBy={titleId} onEscape={close} onBackdrop={close} closing={closing} initialFocus={checked} width="sm:max-w-md">
      <div className="flex items-center gap-2 border-b border-line py-2 pl-5 pr-2">
        <h2 id={titleId} className="min-w-0 flex-1 text-base font-extrabold">
          {t("header.chooseStore")}
        </h2>
        <button type="button" onClick={close} aria-label={t("common.close")} className="grid h-11 w-11 place-items-center rounded-full text-ink-2 hover:bg-surface-2">
          <Icon name="x" size={20} />
        </button>
      </div>
      <div
        role="radiogroup"
        aria-labelledby={titleId}
        className="grid gap-2 overflow-y-auto overscroll-contain p-3"
        onKeyDown={(e) => {
          // arrows move focus here; Enter/Space chooses (choosing closes the sheet)
          const i = modes.indexOf((document.activeElement as HTMLElement | null)?.dataset.mode as StoreMode);
          let n = -1;
          if (e.key === "ArrowDown" || e.key === "ArrowRight") n = (i + 1) % modes.length;
          else if (e.key === "ArrowUp" || e.key === "ArrowLeft") n = (i - 1 + modes.length) % modes.length;
          if (n < 0) return;
          e.preventDefault();
          e.currentTarget.querySelector<HTMLElement>(`[data-mode="${modes[n]}"]`)?.focus();
        }}
      >
        {modes.map((m) => {
          const on = m === current;
          const s = storeText(m, t);
          return (
            <button
              key={m}
              ref={on ? checked : undefined}
              type="button"
              role="radio"
              aria-checked={on}
              tabIndex={on ? 0 : -1}
              data-mode={m}
              data-store={m}
              onClick={() => choose(m)}
              className="sp-card flex min-h-[64px] w-full items-center gap-3 rounded-[14px] border border-line bg-surface px-3 py-2.5 text-left"
            >
              <span className="min-w-0 flex-1 pl-1">
                <span className="flex flex-wrap items-baseline gap-x-2">
                  <StoreName mode={m} name={m === "all" ? t("search.allStores") : s.name} size="text-[17px]" className="tracking-tight" />
                  <span className="text-xs font-medium text-ink-2">{m === "all" ? s.source : t("picker.from", { source: s.source })}</span>
                </span>
                <span className="mt-0.5 block text-xs leading-snug text-ink-2">{s.tagline}</span>
              </span>
              <span
                aria-hidden
                data-store={m}
                className={"sn grid h-6 w-6 shrink-0 place-items-center rounded-full text-white " + (on ? "" : "ring-1 ring-line")}
                style={on ? { background: "var(--sn-fill)" } : undefined}
              >
                {on && <Icon name="check" size={14} stroke={2.6} />}
              </span>
            </button>
          );
        })}
      </div>
    </ModalShell>
  );
}

/** The header store switcher: segmented control from md, compact button + bottom sheet on phones. */
export function StoreSwitcher() {
  const modes = useEnabledModes();
  if (modes.length < 2) return null;
  return (
    <>
      <StoreSegmented />
      <StoreSwitchButton />
    </>
  );
}
