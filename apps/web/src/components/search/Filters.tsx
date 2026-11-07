"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { STORES, STORE_MODES } from "@/lib/markets";
import { useStore } from "@/state/store";
import { Icon } from "../Icon";

const SORTS = [
  ["relevance", "Best match"],
  ["discount", "Biggest discount"],
  ["price_asc", "Price: low to high"],
  ["price_desc", "Price: high to low"],
  ["rating", "Top rated"],
] as const;

const CATS = [
  ["", "All categories"],
  ["electronics", "Electronics"],
  ["fashion", "Fashion"],
  ["beauty", "Beauty"],
  ["grocery", "Grocery"],
  ["home", "Home"],
] as const;

const PRICES = [
  ["", "", "Any price"],
  ["", "1000", "Under ৳1,000"],
  ["1000", "3000", "৳1,000 - ৳3,000"],
  ["3000", "10000", "৳3,000 - ৳10,000"],
  ["10000", "", "Over ৳10,000"],
] as const;

function useParamsUpdater() {
  const router = useRouter();
  const path = usePathname();
  const sp = useSearchParams();
  return (patch: Record<string, string | null>) => {
    const next = new URLSearchParams(sp.toString());
    for (const [k, v] of Object.entries(patch)) {
      if (v === null || v === "") next.delete(k);
      else next.set(k, v);
    }
    next.delete("page");
    if (!next.has("q")) next.set("q", "");
    router.push(`${path}?${next.toString()}`, { scroll: false });
  };
}

/** hideCategory: on /c/{path} the URL already scopes the category, so the pricing-group list is hidden. */
function FilterBody({ hideCategory = false }: { hideCategory?: boolean }) {
  const sp = useSearchParams();
  const set = useParamsUpdater();
  const { enabled } = useStore();
  const store = sp.get("store") ?? "all";
  const cat = sp.get("category") ?? "";
  const min = sp.get("min") ?? "";
  const max = sp.get("max") ?? "";
  const rating = sp.get("rating") ?? "";
  const fast = sp.get("fast") === "1";

  const Opt = ({ on, onClick, children }: { on: boolean; onClick: () => void; children: React.ReactNode }) => (
    <button
      type="button"
      aria-pressed={on}
      onClick={onClick}
      className={
        "flex min-h-11 w-full items-center gap-2 rounded-xl px-3 text-left text-sm transition " +
        (on ? "bg-[var(--accent-soft)] font-semibold text-accent" : "text-ink-2 hover:bg-surface-2 hover:text-ink")
      }
    >
      <span className={"grid h-5 w-5 place-items-center rounded-full border-2 transition " + (on ? "border-accent bg-accent text-white" : "border-line")}>
        {on && <Icon name="check" size={12} stroke={3} />}
      </span>
      {children}
    </button>
  );

  return (
    <div className="space-y-6">
      <fieldset>
        <legend className="mb-2 text-xs font-bold uppercase tracking-wide text-ink-3">Store</legend>
        {STORE_MODES.filter((m) => m === "all" || enabled[m]).map((m) => (
          <Opt key={m} on={store === m} onClick={() => set({ store: m })}>
            {m === "all" ? "All stores" : `${STORES[m].name} (${STORES[m].source})`}
          </Opt>
        ))}
      </fieldset>
      {!hideCategory && (
        <fieldset>
          <legend className="mb-2 text-xs font-bold uppercase tracking-wide text-ink-3">Category</legend>
          {CATS.map(([v, l]) => (
            <Opt key={v} on={cat === v} onClick={() => set({ category: v })}>
              {l}
            </Opt>
          ))}
        </fieldset>
      )}
      <fieldset>
        <legend className="mb-2 text-xs font-bold uppercase tracking-wide text-ink-3">Price</legend>
        {PRICES.map(([a, b, l]) => (
          <Opt key={l} on={min === a && max === b} onClick={() => set({ min: a, max: b })}>
            {l}
          </Opt>
        ))}
      </fieldset>
      <fieldset>
        <legend className="mb-2 text-xs font-bold uppercase tracking-wide text-ink-3">Rating</legend>
        {[["", "Any rating"], ["4", "4 stars and up"], ["3", "3 stars and up"]].map(([v, l]) => (
          <Opt key={l} on={rating === v} onClick={() => set({ rating: v })}>
            {l}
          </Opt>
        ))}
      </fieldset>
      <label className="flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-xl bg-surface-2 px-3">
        <span className="text-sm font-semibold">Fast delivery to our Kolkata hub</span>
        <button
          type="button"
          role="switch"
          aria-checked={fast}
          onClick={() => set({ fast: fast ? null : "1" })}
          className={"relative h-7 w-14 rounded-full text-[10px] font-bold transition " + (fast ? "bg-accent text-white" : "bg-line text-ink-2")}
        >
          <span className={"absolute top-1 h-5 w-5 rounded-full bg-white shadow transition-transform " + (fast ? "translate-x-8" : "translate-x-1")} />
          <span className={"absolute top-1.5 " + (fast ? "left-2" : "right-2")}>{fast ? "ON" : "OFF"}</span>
        </button>
      </label>
    </div>
  );
}

export function FilterSidebar({ hideCategory }: { hideCategory?: boolean } = {}) {
  return (
    <aside className="sticky top-40 hidden max-h-[calc(100dvh-11rem)] w-64 shrink-0 overflow-y-auto pr-2 lg:block">
      <FilterBody hideCategory={hideCategory} />
    </aside>
  );
}

export function SortAndFilterBar({ total, hideCategory }: { total: number; hideCategory?: boolean }) {
  const sp = useSearchParams();
  const set = useParamsUpdater();
  const [open, setOpen] = useState(false);
  const sort = sp.get("sort") ?? "relevance";
  const active = ["category", "min", "max", "rating", "fast"].filter((k) => sp.get(k)).length;

  useEffect(() => {
    if (!open) return;
    const esc = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    document.addEventListener("keydown", esc);
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", esc);
      document.body.style.overflow = "";
    };
  }, [open]);

  return (
    <div className="flex items-center gap-2">
      <button type="button" onClick={() => setOpen(true)} className="btn btn-ghost min-h-10 px-4 text-sm lg:hidden">
        <Icon name="filter" size={16} /> Filters
        {active > 0 && <span className="grid h-5 min-w-5 place-items-center rounded-full bg-accent px-1 text-[11px] text-white">{active}</span>}
      </button>
      <label className="relative ml-auto">
        <span className="sr-only">Sort by</span>
        <select
          value={sort}
          onChange={(e) => set({ sort: e.target.value === "relevance" ? null : e.target.value })}
          className="min-h-10 appearance-none rounded-full bg-surface-2 py-2 pl-9 pr-8 text-sm font-semibold text-ink outline-none focus:ring-2 focus:ring-accent"
        >
          {SORTS.map(([v, l]) => (
            <option key={v} value={v}>
              {l}
            </option>
          ))}
        </select>
        <Icon name="sort" size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
        <Icon name="chevron" size={14} className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-ink-3" />
      </label>

      {open && (
        <div className="fixed inset-0 z-[60] lg:hidden" role="dialog" aria-modal="true" aria-label="Filters">
          <button aria-label="Close filters" className="fade-in absolute inset-0 bg-black/40" onClick={() => setOpen(false)} />
          <div className="sheet-enter absolute inset-x-0 bottom-0 max-h-[85dvh] overflow-y-auto rounded-t-[28px] bg-surface p-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
            <div className="mx-auto mb-3 h-1.5 w-12 rounded-full bg-line" />
            <div className="mb-3 flex items-center justify-between">
              <h2 className="text-lg font-extrabold">Filters</h2>
              <button className="grid h-11 w-11 place-items-center rounded-full hover:bg-surface-2" onClick={() => setOpen(false)} aria-label="Close">
                <Icon name="x" />
              </button>
            </div>
            <FilterBody hideCategory={hideCategory} />
            <button className="btn btn-primary mt-6 w-full" onClick={() => setOpen(false)}>
              Show {total} results
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
