import Link from "next/link";
import { Suspense } from "react";
import { getT } from "@/i18n/server";
import { storeText } from "@/lib/markets";
import type { MarketId } from "@/lib/types";
import { Icon } from "../Icon";
import { BrandFilter } from "../search/BrandFilter";
import { ActiveFilters } from "../search/ActiveFilters";
import type { BrandFacet } from "@/lib/brands";

// Marketplace-style browsing for a store landing page: a left refine rail on desktop and a sticky
// quick-filter bar on phones. Category / price / rating / speed are plain links into /search (carrying any
// selected brands); Brand filters the store's "Everything" grid in place (?brand=A,B on the store URL).

const PRICES = [
  ["", "1000"],
  ["1000", "3000"],
  ["3000", "10000"],
  ["10000", ""],
] as const;

function searchHref(market: MarketId, extra: Record<string, string>) {
  const q = new URLSearchParams({ q: "", store: market, ...extra });
  for (const [k, v] of [...q.entries()]) if (k !== "q" && !v) q.delete(k);
  return `/search?${q.toString().replace(/%2C/gi, ",")}`;
}

export async function StoreBrowse({
  market,
  brands = [],
  selected = [],
  children,
}: {
  market: MarketId;
  /** store-wide top brands (search facets) */
  brands?: BrandFacet[];
  /** ?brand= on the store URL */
  selected?: string[];
  children: React.ReactNode;
}) {
  const { t, bdt } = await getT();
  const s = storeText(market, t);
  const priceLabel = (a: string, b: string) =>
    !a ? t("f.under", { amount: bdt(+b) }) : !b ? t("f.over", { amount: bdt(+a) }) : t("f.range", { a: bdt(+a), b: bdt(+b) });
  const brand = selected.join(",");
  const href = (extra: Record<string, string>) => searchHref(market, { ...extra, brand });

  const rail = (
    <nav aria-label={t("sb.refine", { store: s.name })} className="no-scrollbar sticky top-[calc(var(--hdr-h,0px)+1rem)] hidden max-h-[calc(100dvh-var(--hdr-h,0px)-2rem)] w-56 shrink-0 self-start overflow-y-auto overscroll-contain lg:block">
      <div className="rounded-xl border border-line bg-surface p-4">
        <p className="text-sm font-semibold text-ink">{t("sb.refineTitle")}</p>
        <RailGroup title={t("f.category")}>
          {s.categories.map((c) => (
            <RailLink key={c.slug} href={href({ category: c.slug })}>
              {c.label}
            </RailLink>
          ))}
        </RailGroup>
        <RailGroup title={t("f.price")}>
          {PRICES.map(([a, b]) => (
            <RailLink key={a + "-" + b} href={href({ min: a, max: b })}>
              {priceLabel(a, b)}
            </RailLink>
          ))}
        </RailGroup>
        <RailGroup title={t("f.ratingLegend")}>
          <RailLink href={href({ rating: "4" })}>{t("f.4up")}</RailLink>
          <RailLink href={href({ rating: "3" })}>{t("f.3up")}</RailLink>
        </RailGroup>
        <RailGroup title={t("sb.delivery")}>
          <RailLink href={href({ fast: "1" })}>{t("sb.fast")}</RailLink>
        </RailGroup>
        {(brands.length > 0 || selected.length > 0) && (
          <div className="mt-4 border-t border-line pt-3">
            <Suspense>
              <BrandFilter brands={brands} selected={selected} variant="rail" />
            </Suspense>
          </div>
        )}
        <Link href={href({})} className="btn btn-ghost mt-4 min-h-10 w-full text-sm">
          {t("sb.allFilters")}
        </Link>
      </div>
    </nav>
  );

  return (
    <div className="mt-3 flex gap-6 px-4 sm:px-0">
      {rail}
      <div className="min-w-0 flex-1">
        <Suspense>
          <ActiveFilters keys={["brand"]} className="mb-3" />
        </Suspense>
        {children}
      </div>
    </div>
  );
}

/** Phone/tablet: sticky quick-filter bar (sits under the sticky header for the whole store page). */
export async function StoreQuickBar({ market, hasBrands = false }: { market: MarketId; hasBrands?: boolean }) {
  const { t, bdt } = await getT();
  const chips: [string, string, string?][] = [
    [searchHref(market, {}), t("f.filters"), "filter"],
    // opens the search filter sheet straight at the Brand section
    ...(hasBrands ? [[searchHref(market, { sheet: "brand" }), t("f.brand")] as [string, string]] : []),
    [searchHref(market, { sort: "discount" }), t("f.discount")],
    [searchHref(market, { rating: "4" }), t("f.4up")],
    [searchHref(market, { max: "1000" }), t("f.under", { amount: bdt(1000) })],
    [searchHref(market, { fast: "1" }), t("sb.fast")],
  ];
  return (
    <nav
      aria-label={t("sb.quick")}
      className="no-scrollbar sticky top-[var(--hdr-h,0px)] z-30 mt-3 flex gap-2 overflow-x-auto border-y border-line bg-bg px-4 py-2 sm:-mx-4 lg:hidden"
    >
      {chips.map(([href, label, icon]) => (
        <Link
          key={href}
          href={href}
          className="inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-full border border-line bg-surface px-3 text-xs font-semibold text-ink transition-colors hover:border-accent hover:text-accent"
        >
          {icon && <Icon name={icon} size={14} stroke={2} />}
          {label}
        </Link>
      ))}
    </nav>
  );
}

function RailGroup({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="mt-4 border-t border-line pt-3">
      <p className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-ink-3">{title}</p>
      <ul className="space-y-0.5">{children}</ul>
    </div>
  );
}

function RailLink({ href, children }: { href: string; children: React.ReactNode }) {
  return (
    <li>
      <Link
        href={href}
        className="-mx-2 flex min-h-8 items-center rounded-md px-2 text-sm text-ink-2 transition-colors hover:bg-surface-2 hover:text-ink"
      >
        {children}
      </Link>
    </li>
  );
}
