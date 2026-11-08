"use client";

import { useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { STORES, STORE_MODES } from "@/lib/markets";
import { useStore } from "@/state/store";
import { Icon } from "../Icon";
import { useT } from "@/i18n/client";
import type { MsgKey } from "@/i18n/core";
import { parseBrands, type BrandFacet } from "@/lib/brands";
import { BrandFilter } from "./BrandFilter";
import { useParamsUpdater } from "./params";

const SORTS: [string, MsgKey][] = [
  ["relevance", "f.best"],
  ["discount", "f.discount"],
  ["price_asc", "f.priceAsc"],
  ["price_desc", "f.priceDesc"],
  ["rating", "f.rating"],
];

const CATS: [string, MsgKey][] = [
  ["", "cat.allCategories"],
  ["electronics", "store.cat.electronics"],
  ["fashion", "store.cat.fashion"],
  ["beauty", "store.cat.beauty"],
  ["grocery", "store.cat.grocery"],
  ["home", "store.cat.home"],
];

const PRICES = [
  ["", ""],
  ["", "1000"],
  ["1000", "3000"],
  ["3000", "10000"],
  ["10000", ""],
] as const;

type FilterProps = {
  /** on /c/{path} the URL already scopes the category, so the pricing-group list is hidden */
  hideCategory?: boolean;
  /** top brands of the result without the brand filter (search facets.brands) */
  brands?: BrandFacet[];
};

function FilterBody({ hideCategory = false, brands = [], brandId }: FilterProps & { brandId?: string }) {
  const sp = useSearchParams();
  const set = useParamsUpdater();
  const { enabled } = useStore();
  const { t, bdt } = useT();
  const priceLabel = (a: string, b: string) =>
    !a && !b ? t("f.anyPrice") : !a ? t("f.under", { amount: bdt(+b) }) : !b ? t("f.over", { amount: bdt(+a) }) : t("f.range", { a: bdt(+a), b: bdt(+b) });
  const store = sp.get("store") ?? "all";
  const cat = sp.get("category") ?? "";
  const min = sp.get("min") ?? "";
  const max = sp.get("max") ?? "";
  const rating = sp.get("rating") ?? "";
  const fast = sp.get("fast") === "1";
  const selectedBrands = parseBrands(sp.get("brand"));

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
      <span className={"grid h-5 w-5 place-items-center rounded-full border-2 transition " + (on ? "border-primary bg-primary text-on-primary" : "border-line")}>
        {on && <Icon name="check" size={12} stroke={3} />}
      </span>
      {children}
    </button>
  );

  return (
    <div className="space-y-6">
      <fieldset>
        <legend className="mb-2 text-xs font-bold uppercase tracking-wide text-ink-3">{t("f.store")}</legend>
        {STORE_MODES.filter((m) => m === "all" || enabled[m]).map((m) => (
          <Opt key={m} on={store === m} onClick={() => set({ store: m })}>
            {m === "all" ? t("search.allStores") : `${STORES[m].name} (${STORES[m].source})`}
          </Opt>
        ))}
      </fieldset>
      {!hideCategory && (
        <fieldset>
          <legend className="mb-2 text-xs font-bold uppercase tracking-wide text-ink-3">{t("f.category")}</legend>
          {CATS.map(([v, l]) => (
<Opt key={v} on={cat === v} onClick={() => set({ category: v })}>
{t(l)}
            </Opt>
          ))}
        </fieldset>
      )}
      <BrandFilter brands={brands} selected={selectedBrands} id={brandId} />
      <fieldset>
        <legend className="mb-2 text-xs font-bold uppercase tracking-wide text-ink-3">{t("f.price")}</legend>
        {PRICES.map(([a, b]) => (
<Opt key={a + "-" + b} on={min === a && max === b} onClick={() => set({ min: a, max: b })}>
{priceLabel(a, b)}
          </Opt>
        ))}
      </fieldset>
      <fieldset>
        <legend className="mb-2 text-xs font-bold uppercase tracking-wide text-ink-3">{t("f.ratingLegend")}</legend>
        {[["", t("f.anyRating")], ["4", t("f.4up")], ["3", t("f.3up")]].map(([v, l]) => (
          <Opt key={l} on={rating === v} onClick={() => set({ rating: v })}>
            {l}
          </Opt>
        ))}
      </fieldset>
      <label className="flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-xl bg-surface-2 px-3">
        <span className="text-sm font-semibold">{t("f.fast")}</span>
        <button
          type="button"
          role="switch"
          aria-checked={fast}
          onClick={() => set({ fast: fast ? null : "1" })}
          className={"relative h-7 w-14 rounded-full text-[10px] font-bold transition " + (fast ? "bg-primary text-on-primary" : "bg-line text-ink-2")}
        >
          <span className={"absolute top-1 h-5 w-5 rounded-full bg-surface shadow transition-transform " + (fast ? "translate-x-8" : "translate-x-1")} />
          <span className={"absolute top-1.5 " + (fast ? "left-2" : "right-2")}>{fast ? t("f.on") : t("f.off")}</span>
        </button>
      </label>
    </div>
  );
}

export function FilterSidebar({ hideCategory, brands }: FilterProps = {}) {
  return (
    <aside className="sticky top-40 hidden max-h-[calc(100dvh-11rem)] w-64 shrink-0 overflow-y-auto pr-2 lg:block">
      <FilterBody hideCategory={hideCategory} brands={brands} />
    </aside>
  );
}

export function SortAndFilterBar({ total, hideCategory, brands = [] }: FilterProps & { total: number }) {
  const sp = useSearchParams();
  const set = useParamsUpdater();
  // ?sheet=brand (the store page's "Brand" quick chip) opens the phone sheet at the Brand section, server-rendered.
  const [open, setOpen] = useState<false | "all" | "brand">(sp.get("sheet") === "brand" ? "brand" : false);
  const { t, digits } = useT();
  const sort = sp.get("sort") ?? "relevance";
  const active = ["category", "min", "max", "rating", "fast", "brand"].filter((k) => sp.get(k)).length;
  const brandCount = parseBrands(sp.get("brand")).length;
  const showBrand = brands.length > 0 || brandCount > 0;

  const close = () => setOpen(false);

  useEffect(() => {
    if (!open) return;
    // ?sheet= did its job (opened the sheet): drop it without a server round trip so reloads and shares stay clean.
    const url = new URL(window.location.href);
    if (url.searchParams.has("sheet")) {
      url.searchParams.delete("sheet");
      window.history.replaceState(null, "", url.pathname + url.search.replace(/%2C/gi, ","));
    }
    // The sheet only exists below lg; a ?sheet= link opened on a desktop must not lock the page.
    if (window.matchMedia("(min-width: 1024px)").matches) return;
    const esc = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    document.addEventListener("keydown", esc);
    document.body.style.overflow = "hidden";
    if (open === "brand") document.getElementById("f-brand-sheet")?.scrollIntoView({ block: "start" });
    return () => {
      document.removeEventListener("keydown", esc);
      document.body.style.overflow = "";
    };
  }, [open]);

  return (
    <div className="flex items-center gap-2">
      <button type="button" onClick={() => setOpen("all")} className="btn btn-ghost min-h-11 shrink-0 px-4 text-sm lg:hidden">
        <Icon name="filter" size={16} /> {t("f.filters")}
        {active > 0 && <span className="grid h-5 min-w-5 place-items-center rounded-full bg-primary px-1 text-[11px] text-on-primary">{digits(active)}</span>}
      </button>
      {showBrand && (
        <button
          type="button"
          onClick={() => setOpen("brand")}
          aria-haspopup="dialog"
          className={"btn min-h-11 shrink-0 px-3.5 text-sm lg:hidden " + (brandCount ? "btn-ghost border-accent text-accent" : "btn-ghost")}
        >
          {t("f.brand")}
          {brandCount > 0 && <span className="grid h-5 min-w-5 place-items-center rounded-full bg-primary px-1 text-[11px] text-on-primary">{digits(brandCount)}</span>}
          <Icon name="chevron" size={14} />
        </button>
      )}
      <label className="relative ml-auto min-w-0">
        <span className="sr-only">{t("f.sortBy")}</span>
        <select
          value={sort}
          onChange={(e) => set({ sort: e.target.value === "relevance" ? null : e.target.value })}
          className="min-h-11 w-full appearance-none truncate rounded-full bg-surface-2 py-2 pl-9 pr-8 text-sm font-semibold text-ink outline-none focus:ring-2 focus:ring-accent lg:min-h-10"
        >
          {SORTS.map(([v, l]) => (
<option key={v} value={v}>
{t(l)}
            </option>
          ))}
        </select>
        <Icon name="sort" size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
        <Icon name="chevron" size={14} className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-ink-3" />
      </label>

      {open && (
        <div className="fixed inset-0 z-[60] lg:hidden" role="dialog" aria-modal="true" aria-label={t("f.filters")}>
          <button aria-label={t("f.close")} className="fade-in absolute inset-0 bg-black/40" onClick={close} />
          <div className="sheet-enter absolute inset-x-0 bottom-0 max-h-[85dvh] overflow-y-auto rounded-t-[16px] bg-surface p-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
            <div className="mx-auto mb-3 h-1.5 w-12 rounded-full bg-line" />
            <div className="mb-3 flex items-center justify-between">
              <h2 className="text-lg font-extrabold">{t("f.filters")}</h2>
              <button className="grid h-11 w-11 place-items-center rounded-full hover:bg-surface-2" onClick={close} aria-label={t("common.close")}>
                <Icon name="x" />
              </button>
            </div>
            <FilterBody hideCategory={hideCategory} brands={brands} brandId="f-brand-sheet" />
            <button className="btn btn-primary mt-6 w-full" onClick={close}>
              {t("f.show", { count: total })}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
