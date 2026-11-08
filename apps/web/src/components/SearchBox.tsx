"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useEffect, useId, useRef, useState } from "react";
import { STORES, STORE_MODES } from "@/lib/markets";
import type { StoreMode } from "@/lib/types";
import { useStore } from "@/state/store";
import { Icon } from "./Icon";
import { useCurrentMode } from "./StoreTheme";
import { startRouteProgress } from "./motion/RouteProgress";
import { useT } from "@/i18n/client";
import type { MsgKey } from "@/i18n/core";

type Sug = { id: string; title: string; market: string };

// Example searches that cycle in the empty field (stops while focused or typed in).
const EXAMPLES: Record<StoreMode, MsgKey[]> = {
  all: ["search.ex.iphone", "search.ex.amazonLink", "search.ex.rice", "search.ex.kurta", "search.ex.earbuds"],
  amazon: ["search.ex.iphone", "search.ex.amazonLink", "search.ex.earbuds", "search.ex.cooker"],
  flipkart: ["search.ex.kurta", "search.ex.flipkartLink", "search.ex.sneakers", "search.ex.saree"],
  bigbasket: ["search.ex.rice", "search.ex.haldiram", "search.ex.chai", "search.ex.bigbasketLink"],
};

/** Cycles placeholder examples every 2.8s while the field is idle, visible and the tab is shown. */
// The placeholder shows one static example per store (no rotating text): calm and readable.
function usePlaceholderCycle(active: boolean, count: number, el: React.RefObject<HTMLElement | null>) {
  void active;
  void count;
  void el;
  return 0;
}

export function SearchBox({ autoFocus, onDone }: { autoFocus?: boolean; onDone?: () => void }) {
  const router = useRouter();
  const current = useCurrentMode();
  const { enabled } = useStore();
  const { t } = useT();
  const [picked, setPicked] = useState<{ store: StoreMode; for: StoreMode } | null>(null);
  // the shopper's pick applies until they move to another store page
  const store = picked && picked.for === current ? picked.store : current;
  const setStore = (s: StoreMode) => setPicked({ store: s, for: current });
  // on the results page the box keeps the query, so the shopper can refine it instead of retyping
  const path = usePathname();
  const params = useSearchParams();
  const urlQ = path === "/search" ? (params.get("q") ?? "") : "";
  const [q, setQ] = useState(urlQ);
  const [seenUrlQ, setSeenUrlQ] = useState(urlQ);
  if (urlQ !== seenUrlQ) {
    setSeenUrlQ(urlQ);
    setQ(urlQ);
  }
  const [items, setItems] = useState<Sug[]>([]);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const [focused, setFocused] = useState(false);
  const listId = useId();
  const ref = useRef<HTMLInputElement>(null);
  const formRef = useRef<HTMLFormElement>(null);
  const examples = EXAMPLES[store] ?? EXAMPLES.all;
  const idle = !focused && !q;
  const tick = usePlaceholderCycle(idle, examples.length, formRef);
  const example = t(examples[tick % examples.length]);

  useEffect(() => {
    // only while the list can show (a query prefilled from the URL does not fetch until focused)
    if (!open || q.trim().length < 2) return;
    const ctrl = new AbortController();
    const t = setTimeout(() => {
      fetch(`/api/suggest?q=${encodeURIComponent(q)}&store=${store}`, { signal: ctrl.signal })
        .then((r) => r.json())
        .then((d) => setItems(d.items ?? []))
        .catch(() => {});
    }, 160);
    return () => {
      clearTimeout(t);
      ctrl.abort();
    };
  }, [q, store, open]);

  const modes = STORE_MODES.filter((m) => m === "all" || enabled[m]);

  function go(query: string) {
    if (!query.trim()) return;
    setOpen(false);
    onDone?.();
    const href = `/search?q=${encodeURIComponent(query.trim())}&store=${store}`;
    startRouteProgress(href);
    router.push(href);
  }

  return (
    <form
      ref={formRef}
      role="search"
      className="relative flex w-full items-stretch rounded-[8px] bg-surface ring-1 ring-line-2 transition-shadow duration-200 focus-within:shadow-[0_0_0_3px_var(--accent-soft)] focus-within:ring-accent"
      onSubmit={(e) => {
        e.preventDefault();
        if (active >= 0 && items[active]) {
          setOpen(false);
          onDone?.();
          startRouteProgress(`/p/${items[active].id}`);
          router.push(`/p/${items[active].id}`);
        } else go(q);
      }}
    >
      <label className="relative flex shrink-0 items-center border-r border-line">
        <span className="sr-only">{t("search.storeToSearch")}</span>
        <select
          value={store}
          onChange={(e) => setStore(e.target.value as StoreMode)}
          className="h-full max-w-[96px] cursor-pointer appearance-none truncate bg-transparent py-2 pl-3 pr-7 text-xs font-medium text-ink-2 outline-none sm:max-w-[150px] sm:text-sm md:max-w-[112px] xl:max-w-none"
        >
          {modes.map((m) => (
            <option key={m} value={m}>
              {m === "all" ? t("search.allStores") : STORES[m].name}
            </option>
          ))}
        </select>
        <Icon name="chevron" size={14} className="pointer-events-none absolute right-2 text-ink-3" />
      </label>
      <span className="relative flex min-w-0 flex-1">
      {idle && (
        <span className="sb-ph px-3 text-[15px]" aria-hidden="true">
          <span key={example}>{example}</span>
        </span>
      )}
      <input
        ref={ref}
        autoFocus={autoFocus}
        value={q}
        onChange={(e) => {
          setQ(e.target.value);
          setOpen(true);
          setActive(-1);
        }}
        onFocus={() => {
          setOpen(true);
          setFocused(true);
        }}
        onBlur={() => {
          setFocused(false);
          setTimeout(() => setOpen(false), 150);
        }}
        onKeyDown={(e) => {
          if (e.key === "ArrowDown") {
            e.preventDefault();
            setActive((a) => Math.min(items.length - 1, a + 1));
          } else if (e.key === "ArrowUp") {
            e.preventDefault();
            setActive((a) => Math.max(-1, a - 1));
          } else if (e.key === "Escape") setOpen(false);
        }}
        placeholder={store === "all" ? t("search.placeholderAll") : t("search.placeholderStore", { store: STORES[store].name })}
        aria-label={store === "all" ? t("search.placeholderAll") : t("search.placeholderStore", { store: STORES[store].name })}
        className={
          "w-0 min-w-0 flex-1 bg-transparent px-3 py-2.5 text-[15px] text-ink outline-none placeholder:text-ink-3 " +
          (idle ? "placeholder:text-transparent" : "")
        }
        role="combobox"
        aria-expanded={open && q.trim().length >= 2 && items.length > 0}
        aria-controls={listId}
        aria-activedescendant={open && active >= 0 && items[active] ? `${listId}-${active}` : undefined}
        aria-autocomplete="list"
        enterKeyHint="search"
      />
      </span>
      <button type="submit" className="btn btn-primary m-1 min-h-0 rounded-[6px] px-3 sm:px-4" aria-label={t("search.submit")}>
        <Icon name="search" size={18} stroke={2.2} />
      </button>

      {open && q.trim().length >= 2 && items.length > 0 && (
        <ul
          id={listId}
          role="listbox"
          className="sb-pop absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-[10px] bg-surface p-1.5 shadow-[var(--shadow-2)]"
        >
          {items.map((s, i) => (
            <li key={s.id} id={`${listId}-${i}`} role="option" aria-selected={i === active} style={{ ["--i" as string]: i }}>
              <button
                type="button"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => {
                  setOpen(false);
                  onDone?.();
                  startRouteProgress(`/p/${s.id}`);
                  router.push(`/p/${s.id}`);
                }}
                className={
                  "group/opt flex min-h-11 w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition-colors " +
                  (i === active ? "bg-[var(--accent-soft)]" : "hover:bg-surface-2")
                }
              >
                <span className={"chip-icon h-7 w-7 shrink-0 rounded-lg " + (i === active ? "is-active" : "")}>
                  <Icon name="search" size={14} />
                </span>
                <span className="line-clamp-1 flex-1">{s.title}</span>
                <Icon name="right" size={14} className="shrink-0 text-ink-3 transition-transform group-hover/opt:translate-x-0.5" />
                <span className="shrink-0 text-[11px] font-semibold text-ink-3">
                  {STORES[s.market as keyof typeof STORES]?.source}
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </form>
  );
}
