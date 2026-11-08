"use client";

import { startTransition, useId, useOptimistic, useState } from "react";
import { useT } from "@/i18n/client";
import { MAX_BRANDS, sameBrand, toggleBrand, type BrandFacet } from "@/lib/brands";
import { Icon } from "../Icon";
import { useParamsUpdater } from "./params";

const SHOWN = 6;
const SEARCH_FROM = 10;

/**
 * Brand checkboxes for the search filters, the phone sheet and the store-page rail. The URL (?brand=A,B) is the
 * state: ticking a box pushes a new URL (back/forward step through it); the box flips at once while the page loads.
 * `brands` = top brands of the result without the brand filter (API facets.brands); selected ones not in that list
 * still show so they can be unticked.
 */
export function BrandFilter({
  brands,
  selected,
  variant = "panel",
  id,
}: {
  brands: BrandFacet[];
  selected: string[];
  /** panel: search sidebar and phone sheet (44px rows); rail: compact desktop store rail */
  variant?: "panel" | "rail";
  id?: string;
}) {
  const { t, num } = useT();
  const set = useParamsUpdater();
  const [picked, setPicked] = useOptimistic(selected);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const uid = useId();

  const extra = selected.filter((s) => !brands.some((b) => sameBrand(b.name, s))).map((name) => ({ name, count: 0 }));
  const all: BrandFacet[] = [...extra, ...brands];
  if (!all.length) return null;

  const isOn = (name: string) => picked.some((s) => sameBrand(s, name));
  const full = picked.length >= MAX_BRANDS;
  const q = query.trim().toLowerCase();
  const matches = q ? all.filter((b) => b.name.toLowerCase().includes(q)) : all;
  const visible = q || open ? matches : matches.filter((b, i) => i < SHOWN || isOn(b.name));
  const hidden = matches.length - visible.length;

  const toggle = (name: string) => {
    const next = toggleBrand(picked, name);
    startTransition(() => {
      setPicked(next ? next.split(",") : []);
      set({ brand: next || null, sheet: null });
    });
  };

  const rail = variant === "rail";
  const row = rail ? "min-h-9 -mx-2 px-2 rounded-md" : "min-h-11 px-3 rounded-xl";

  return (
    <fieldset id={id} className="scroll-mt-4">
      <legend
        className={
          rail ? "mb-1 text-[11px] font-semibold uppercase tracking-wide text-ink-3" : "mb-2 text-xs font-bold uppercase tracking-wide text-ink-3"
        }
      >
        {t("f.brand")}
      </legend>
      {all.length > SEARCH_FROM && (
        <label className={"relative mb-1 block " + (rail ? "" : "px-1")}>
          <span className="sr-only">{t("f.brandSearch")}</span>
          <Icon name="search" size={14} className={"pointer-events-none absolute top-1/2 -translate-y-1/2 text-ink-3 " + (rail ? "left-2.5" : "left-3.5")} />
          <input
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder={t("f.brandSearch")}
            maxLength={60}
            className={
              "w-full rounded-full border border-line bg-surface pl-8 pr-3 text-sm text-ink outline-none transition-colors placeholder:text-ink-3 focus:border-accent " +
              (rail ? "h-9" : "h-11")
            }
          />
        </label>
      )}
      <ul id={uid} className={rail ? "space-y-0.5" : ""}>
        {visible.map((b) => {
          const on = isOn(b.name);
          const off = !on && full;
          return (
            <li key={b.name}>
              <label
                className={
                  "group flex items-center gap-2.5 text-sm transition-colors duration-[var(--dur-1)] ease-[var(--ease)] " +
                  row +
                  (off ? " cursor-not-allowed opacity-50" : " cursor-pointer hover:bg-surface-2") +
                  (on ? " font-semibold text-ink" : " text-ink-2")
                }
              >
                <input type="checkbox" className="peer sr-only" checked={on} disabled={off} onChange={() => toggle(b.name)} />
                <span
                  aria-hidden="true"
                  className={
                    "grid h-[18px] w-[18px] shrink-0 place-items-center rounded-[5px] border-2 transition-colors duration-[var(--dur-1)] ease-[var(--ease)] peer-focus-visible:ring-2 peer-focus-visible:ring-accent peer-focus-visible:ring-offset-1 " +
                    (on ? "border-accent bg-accent text-white" : "border-line bg-surface")
                  }
                >
                  {on && <Icon name="check" size={11} stroke={3} />}
                </span>
                <span className="min-w-0 flex-1 truncate">{b.name}</span>
                {b.count > 0 && (
                  <span className="shrink-0 text-xs tabular-nums text-ink-3">
                    <span aria-hidden="true">{num(b.count)}</span>
                    <span className="sr-only">{t("f.productCount", { count: b.count })}</span>
                  </span>
                )}
              </label>
            </li>
          );
        })}
      </ul>
      {q && !matches.length && <p className={"py-2 text-sm text-ink-3 " + (rail ? "" : "px-3")}>{t("f.brandNone", { q: query.trim() })}</p>}
      {full && <p className={"pt-1 text-xs text-ink-3 " + (rail ? "" : "px-3")}>{t("f.brandMax", { count: MAX_BRANDS })}</p>}
      {!q && (hidden > 0 || open) && matches.length > SHOWN && (
        <button
          type="button"
          aria-expanded={open}
          aria-controls={uid}
          onClick={() => setOpen((v) => !v)}
          className={
            "inline-flex items-center gap-1 text-sm font-semibold text-accent transition-colors hover:text-ink " + (rail ? "mt-1 min-h-8" : "min-h-11 px-3")
          }
        >
          {open ? t("f.brandLess") : t("f.brandMore", { count: hidden })}
          <Icon name="chevron" size={14} className={open ? "rotate-180" : ""} />
        </button>
      )}
    </fieldset>
  );
}
