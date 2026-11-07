"use client";

import { useRouter } from "next/navigation";
import { useEffect, useId, useRef, useState } from "react";
import { STORES, STORE_MODES } from "@/lib/markets";
import type { StoreMode } from "@/lib/types";
import { useStore } from "@/state/store";
import { Icon } from "./Icon";
import { useCurrentMode } from "./StoreTheme";

type Sug = { id: string; title: string; market: string };

export function SearchBox({ autoFocus, onDone }: { autoFocus?: boolean; onDone?: () => void }) {
  const router = useRouter();
  const current = useCurrentMode();
  const { enabled } = useStore();
  const [picked, setPicked] = useState<{ store: StoreMode; for: StoreMode } | null>(null);
  // the shopper's pick applies until they move to another store page
  const store = picked && picked.for === current ? picked.store : current;
  const setStore = (s: StoreMode) => setPicked({ store: s, for: current });
  const [q, setQ] = useState("");
  const [items, setItems] = useState<Sug[]>([]);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const listId = useId();
  const ref = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (q.trim().length < 2) return;
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
  }, [q, store]);

  const modes = STORE_MODES.filter((m) => m === "all" || enabled[m]);

  function go(query: string) {
    if (!query.trim()) return;
    setOpen(false);
    onDone?.();
    router.push(`/search?q=${encodeURIComponent(query.trim())}&store=${store}`);
  }

  return (
    <form
      role="search"
      className="relative flex w-full items-stretch rounded-2xl bg-surface shadow-[var(--shadow-1)] ring-1 ring-line transition focus-within:shadow-[0_0_0_4px_var(--accent-soft)] focus-within:ring-accent"
      onSubmit={(e) => {
        e.preventDefault();
        if (active >= 0 && items[active]) {
          setOpen(false);
          onDone?.();
          router.push(`/p/${items[active].id}`);
        } else go(q);
      }}
    >
      <label className="relative flex shrink-0 items-center border-r border-line">
        <span className="sr-only">Store to search</span>
        <select
          value={store}
          onChange={(e) => setStore(e.target.value as StoreMode)}
          className="h-full max-w-[96px] cursor-pointer appearance-none truncate bg-transparent py-2 pl-3 pr-7 text-xs font-semibold text-ink outline-none sm:max-w-none sm:text-sm"
        >
          {modes.map((m) => (
            <option key={m} value={m}>
              {m === "all" ? "All stores" : STORES[m].name}
            </option>
          ))}
        </select>
        <Icon name="chevron" size={14} className="pointer-events-none absolute right-2 text-ink-3" />
      </label>
      <input
        ref={ref}
        autoFocus={autoFocus}
        value={q}
        onChange={(e) => {
          setQ(e.target.value);
          setOpen(true);
          setActive(-1);
        }}
        onFocus={() => setOpen(true)}
        onBlur={() => setTimeout(() => setOpen(false), 150)}
        onKeyDown={(e) => {
          if (e.key === "ArrowDown") {
            e.preventDefault();
            setActive((a) => Math.min(items.length - 1, a + 1));
          } else if (e.key === "ArrowUp") {
            e.preventDefault();
            setActive((a) => Math.max(-1, a - 1));
          } else if (e.key === "Escape") setOpen(false);
        }}
        placeholder={store === "all" ? "Search Indian stores" : `Search ${STORES[store].name}`}
        className="min-w-0 flex-1 bg-transparent px-3 py-2.5 text-[15px] text-ink outline-none placeholder:text-ink-3"
        role="combobox"
        aria-expanded={open && q.trim().length >= 2 && items.length > 0}
        aria-controls={listId}
        aria-autocomplete="list"
        enterKeyHint="search"
      />
      <button type="submit" className="btn btn-primary m-1 min-h-0 rounded-xl px-3 sm:px-4" aria-label="Search">
        <Icon name="search" size={18} stroke={2.2} />
      </button>

      {open && q.trim().length >= 2 && items.length > 0 && (
        <ul
          id={listId}
          role="listbox"
          className="fade-in absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-2xl bg-surface p-1.5 shadow-[var(--shadow-2)] ring-1 ring-line"
        >
          {items.map((s, i) => (
            <li key={s.id} role="option" aria-selected={i === active}>
              <button
                type="button"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => {
                  setOpen(false);
                  onDone?.();
                  router.push(`/p/${s.id}`);
                }}
                className={
                  "flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm transition " +
                  (i === active ? "bg-[var(--accent-soft)]" : "hover:bg-surface-2")
                }
              >
                <Icon name="search" size={16} className="shrink-0 text-ink-3" />
                <span className="line-clamp-1 flex-1">{s.title}</span>
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
