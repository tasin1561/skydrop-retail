"use client";

import { useSearchParams } from "next/navigation";
import { startTransition, useOptimistic } from "react";
import { useT } from "@/i18n/client";
import { parseBrands, toggleBrand } from "@/lib/brands";
import { Icon } from "../Icon";
import { useParamsUpdater } from "./params";

type Key = "brand" | "price" | "rating" | "fast";

/**
 * Active-filters row: one removable chip per active filter (each brand on its own) and "Clear all".
 * Reads the URL, so it is server-rendered with the page and follows back/forward. Renders nothing when no filter is on.
 */
export function ActiveFilters({ keys = ["brand", "price", "rating", "fast"], className = "" }: { keys?: Key[]; className?: string }) {
  const sp = useSearchParams();
  const set = useParamsUpdater();
  const { t, bdt, digits } = useT();
  const brands = keys.includes("brand") ? parseBrands(sp.get("brand")) : [];
  // Removed chips disappear at once while the next page loads.
  const [gone, hide] = useOptimistic<string[], string>([], (s, id) => [...s, id]);

  const chips: { id: string; label: string; patch: Record<string, string | null> }[] = [];
  // a chip removed while the previous removal is still loading must not bring that brand back
  const left = brands.filter((b) => !gone.includes("brand:" + b));
  for (const b of brands) chips.push({ id: "brand:" + b, label: b, patch: { brand: toggleBrand(left, b) || null } });
  const min = sp.get("min") ?? "";
  const max = sp.get("max") ?? "";
  if (keys.includes("price") && (min || max)) {
    const label = !min ? t("f.under", { amount: bdt(+max) }) : !max ? t("f.over", { amount: bdt(+min) }) : t("f.range", { a: bdt(+min), b: bdt(+max) });
    chips.push({ id: "price", label, patch: { min: null, max: null } });
  }
  const rating = sp.get("rating");
  if (keys.includes("rating") && rating) chips.push({ id: "rating", label: rating === "4" ? t("f.4up") : rating === "3" ? t("f.3up") : `${digits(rating)}+ ★`, patch: { rating: null } });
  if (keys.includes("fast") && sp.get("fast") === "1") chips.push({ id: "fast", label: t("sb.fast"), patch: { fast: null } });

  const shown = chips.filter((c) => !gone.includes(c.id));
  if (!shown.length) return null;

  const run = (ids: string[], patch: Record<string, string | null>) =>
    startTransition(() => {
      ids.forEach(hide);
      set(patch);
    });
  const clearAll = Object.fromEntries(
    keys.flatMap((k) => (k === "price" ? ["min", "max"] : [k])).map((k) => [k, null] as [string, null]),
  );

  return (
    <div role="group" aria-label={t("f.active")} className={"flex flex-wrap items-center gap-2 " + className}>
      {shown.map((c) => (
        <button
          key={c.id}
          type="button"
          onClick={() => run([c.id], c.patch)}
          aria-label={t("f.removeFilter", { label: c.label })}
          className="inline-flex min-h-11 max-w-full items-center gap-1.5 rounded-full border border-line bg-surface pl-3 pr-2 text-xs font-semibold text-ink transition-colors duration-[var(--dur-1)] ease-[var(--ease)] hover:border-accent hover:text-accent lg:min-h-8"
        >
          <span className="truncate">{c.label}</span>
          <Icon name="x" size={13} stroke={2.4} className="shrink-0 text-ink-3" />
        </button>
      ))}
      <button
        type="button"
        onClick={() => run(shown.map((c) => c.id), clearAll)}
        className="inline-flex min-h-11 items-center px-1 text-xs font-semibold text-accent underline-offset-2 transition-colors hover:underline lg:min-h-8"
      >
        {t("f.clearAll")}
      </button>
    </div>
  );
}
