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

// What shoppers look for most (women's fashion and beauty first): shown as one-tap chips when the box is empty.
const POPULAR = ["Kurta set", "Saree", "Lipstick", "Handbag", "Earrings", "Sneakers", "Smart watch", "Earbuds"];

// The shopper's own last searches, kept on this device only.
const RECENT_KEY = "sr-recent-searches";
function readRecent(): string[] {
  try {
    const v: unknown = JSON.parse(localStorage.getItem(RECENT_KEY) ?? "[]");
    return Array.isArray(v) ? v.filter((x): x is string => typeof x === "string").slice(0, 6) : [];
  } catch {
    return [];
  }
}
function saveRecent(list: string[]) {
  try {
    localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, 6)));
  } catch {
    /* private mode: recent searches just do not persist */
  }
}

// Example searches that cycle in the empty field (stops while focused or typed in).
const EXAMPLES: Record<StoreMode, MsgKey[]> = {
  all: ["search.ex.iphone", "search.ex.amazonLink", "search.ex.rice", "search.ex.kurta", "search.ex.earbuds"],
  amazon: ["search.ex.iphone", "search.ex.amazonLink", "search.ex.earbuds", "search.ex.cooker"],
  flipkart: ["search.ex.kurta", "search.ex.flipkartLink", "search.ex.sneakers", "search.ex.saree"],
  myntra: ["search.ex.kurta", "search.ex.myntraLink", "search.ex.sneakers", "search.ex.saree"],
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
  const [recent, setRecent] = useState<string[]>([]);
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
    const term = query.trim();
    // product links are not searches worth offering again
    if (!/^https?:\/\//i.test(term)) {
      const next = [term, ...readRecent().filter((x) => x.toLowerCase() !== term.toLowerCase())];
      saveRecent(next);
      setRecent(next.slice(0, 6));
    }
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
      className="relative flex w-full items-stretch rounded-2xl bg-surface shadow-[var(--shadow-1)] ring-1 ring-line transition-shadow duration-200 focus-within:shadow-[0_0_0_3px_var(--accent-soft)] focus-within:ring-2 focus-within:ring-accent"
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
      {/* phones search the store you are in (the store chips sit right below): the box keeps its full width */}
      <label className="relative hidden shrink-0 items-center border-r border-line md:flex">
        <span className="sr-only">{t("search.storeToSearch")}</span>
        <select
          value={store}
          onChange={(e) => setStore(e.target.value as StoreMode)}
          className="h-full max-w-[96px] cursor-pointer appearance-none truncate bg-transparent py-2 pl-3 pr-7 text-xs font-semibold text-ink outline-none sm:max-w-[150px] sm:text-sm md:max-w-[112px] xl:max-w-none"
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
          setRecent(readRecent());
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
      <button type="submit" className="btn btn-primary m-1 min-h-0 rounded-xl px-3 sm:px-4" aria-label={t("search.submit")}>
        <Icon name="search" size={18} stroke={2.2} />
      </button>

      {/* empty box: the shopper's recent searches and popular ones, one tap each (like Amazon, Flipkart and Myntra) */}
      {open && q.trim().length < 2 && (
        <div className="sb-pop absolute left-0 right-0 top-full z-50 mt-2 rounded-2xl bg-surface p-3 shadow-[var(--shadow-2)] ring-1 ring-line">
          {recent.length > 0 && (
            <div className="mb-3">
              <div className="mb-1 flex items-center justify-between">
                <p className="text-[11px] font-bold uppercase tracking-wider text-ink-3">{t("search.recent")}</p>
                <button
                  type="button"
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={() => {
                    saveRecent([]);
                    setRecent([]);
                  }}
                  className="-my-2 -mr-1 min-h-9 px-2 text-xs font-semibold text-accent"
                >
                  {t("search.clearRecent")}
                </button>
              </div>
              <ul>
                {recent.map((r) => (
                  <li key={r}>
                    <button
                      type="button"
                      onMouseDown={(e) => e.preventDefault()}
                      onClick={() => {
                        setQ(r);
                        go(r);
                      }}
                      className="flex min-h-11 w-full items-center gap-3 rounded-xl px-2 text-left text-sm hover:bg-surface-2"
                    >
                      <Icon name="clock" size={16} className="shrink-0 text-ink-3" />
                      <span className="line-clamp-1 flex-1">{r}</span>
                      <Icon name="right" size={14} className="shrink-0 text-ink-3" />
                    </button>
                  </li>
                ))}
              </ul>
            </div>
          )}
          <p className="mb-2 text-[11px] font-bold uppercase tracking-wider text-ink-3">{t("search.popular")}</p>
          <div className="flex flex-wrap gap-2">
            {POPULAR.map((term) => (
              <button
                key={term}
                type="button"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => {
                  setQ(term);
                  go(term);
                }}
                className="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-surface-2 px-3.5 text-sm font-medium text-ink transition-transform active:scale-[0.97]"
              >
                <Icon name="trend" size={14} className="text-accent" />
                {term}
              </button>
            ))}
          </div>
        </div>
      )}

      {open && q.trim().length >= 2 && items.length > 0 && (
        <ul
          id={listId}
          role="listbox"
          className="sb-pop absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-2xl bg-surface p-1.5 shadow-[var(--shadow-2)] ring-1 ring-line"
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
