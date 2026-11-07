import Link from "next/link";
import { getT } from "@/i18n/server";
import { storeText } from "@/lib/markets";
import type { MarketId } from "@/lib/types";
import { Icon } from "../Icon";

// Marketplace-style browsing for a store landing page: a left refine rail on desktop and a sticky
// quick-filter bar on phones. Plain links into the existing /search parameters (store, category,
// min/max, rating, fast, sort), so no new API surface. Brand is not a search parameter yet.

const PRICES = [
  ["", "1000"],
  ["1000", "3000"],
  ["3000", "10000"],
  ["10000", ""],
] as const;

function searchHref(market: MarketId, extra: Record<string, string>) {
  const q = new URLSearchParams({ q: "", store: market, ...extra });
  for (const [k, v] of [...q.entries()]) if (k !== "q" && !v) q.delete(k);
  return `/search?${q.toString()}`;
}

export async function StoreBrowse({ market, children }: { market: MarketId; children: React.ReactNode }) {
  const { t, bdt } = await getT();
  const s = storeText(market, t);
  const priceLabel = (a: string, b: string) =>
    !a ? t("f.under", { amount: bdt(+b) }) : !b ? t("f.over", { amount: bdt(+a) }) : t("f.range", { a: bdt(+a), b: bdt(+b) });

  const rail = (
    <nav aria-label={t("sb.refine", { store: s.name })} className="sticky top-[calc(var(--hdr-h,0px)+1rem)] hidden w-56 shrink-0 self-start lg:block">
      <div className="rounded-xl border border-line bg-surface p-4">
        <p className="text-sm font-semibold text-ink">{t("sb.refineTitle")}</p>
        <RailGroup title={t("f.category")}>
          {s.categories.map((c) => (
            <RailLink key={c.slug} href={searchHref(market, { category: c.slug })}>
              {c.label}
            </RailLink>
          ))}
        </RailGroup>
        <RailGroup title={t("f.price")}>
          {PRICES.map(([a, b]) => (
            <RailLink key={a + "-" + b} href={searchHref(market, { min: a, max: b })}>
              {priceLabel(a, b)}
            </RailLink>
          ))}
        </RailGroup>
        <RailGroup title={t("f.ratingLegend")}>
          <RailLink href={searchHref(market, { rating: "4" })}>{t("f.4up")}</RailLink>
          <RailLink href={searchHref(market, { rating: "3" })}>{t("f.3up")}</RailLink>
        </RailGroup>
        <RailGroup title={t("sb.delivery")}>
          <RailLink href={searchHref(market, { fast: "1" })}>{t("sb.fast")}</RailLink>
        </RailGroup>
        <Link href={searchHref(market, {})} className="btn btn-ghost mt-4 min-h-10 w-full text-sm">
          {t("sb.allFilters")}
        </Link>
      </div>
    </nav>
  );

  return (
    <div className="mt-3 flex gap-6 px-4 sm:px-0">
      {rail}
      <div className="min-w-0 flex-1">{children}</div>
    </div>
  );
}

/** Phone/tablet: sticky quick-filter bar (sits under the sticky header for the whole store page). */
export async function StoreQuickBar({ market }: { market: MarketId }) {
  const { t, bdt } = await getT();
  const chips: [string, string, string?][] = [
    [searchHref(market, {}), t("f.filters"), "filter"],
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
