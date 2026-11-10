import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { getCategoryPage, searchProducts } from "@/lib/data";
import { isStoreMode, STORES, STORE_MODES } from "@/lib/markets";
import type { StoreMode } from "@/lib/types";
import { Icon } from "@/components/Icon";
import { EnabledGrid } from "@/components/home/Gates";
import { FilterSidebar, SortAndFilterBar } from "@/components/search/Filters";
import { SafeSection } from "@/components/resilience/SafeSection";
import { StaleNote } from "@/components/resilience/LiveRefresh";
import { Pager } from "@/components/categories/Pager";
import "@/components/categories/categories.css";
import { getT } from "@/i18n/server";
import { catLabel } from "@/lib/categories";
import { parseBrands } from "@/lib/brands";
import { ActiveFilters } from "@/components/search/ActiveFilters";
import { StoreName } from "@/components/store/StoreName";
import { CatThumb } from "@/components/categories/CatThumb";

type SP = Record<string, string | string[] | undefined>;
type Params = { path: string[] };
const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);
const BASE = "https://retail.skydrop.online";

const joinPath = (segs: string[]) => segs.map((s) => decodeURIComponent(s).toLowerCase()).join("/");
const storeOf = (sp: SP): StoreMode => {
  const s = one(sp.store);
  return isStoreMode(s) ? s : "all";
};
const href = (path: string, store: StoreMode, extra: Record<string, string> = {}) => {
  const q = new URLSearchParams({ ...(store !== "all" ? { store } : {}), ...extra }).toString();
  return `/c/${path}${q ? `?${q}` : ""}`;
};

export async function generateMetadata({ params, searchParams }: { params: Promise<Params>; searchParams: Promise<SP> }): Promise<Metadata> {
  const path = joinPath((await params).path);
  const store = storeOf(await searchParams);
  const [data, { t, lang }] = await Promise.all([getCategoryPage(path, store), getT()]);
  if (!data) return { title: t("c.notFound") };
  const bn = lang === "bn";
  const name = catLabel(data.node, bn);
  const parentCrumb = data.breadcrumbs.length > 1 ? data.breadcrumbs[data.breadcrumbs.length - 2] : null;
  const parent = parentCrumb ? catLabel(parentCrumb, bn) : null;
  const title = t("c.metaTitle", { name: `${name}${parent ? ` · ${parent}` : ""}` });
  const description = t("c.metaDesc", { name: bn ? name : name.toLowerCase() });
  return {
    title,
    description,
    alternates: { canonical: `/c/${path}` },
    openGraph: { title, description, url: `${BASE}/c/${path}` },
  };
}

export default async function CategoryPage({ params, searchParams }: { params: Promise<Params>; searchParams: Promise<SP> }) {
  const path = joinPath((await params).path);
  const sp = await searchParams;
  const store = storeOf(sp);
  const { t, bdt, num, lang } = await getT();
  const bn = lang === "bn";
  const data = await getCategoryPage(path, store);
  if (!data) notFound();

  const page = Number(one(sp.page) ?? 1) || 1;
  const res = await searchProducts({
    q: one(sp.q) || undefined,
    store,
    category: path,
    brand: parseBrands(one(sp.brand)),
    sort: (one(sp.sort) as never) || undefined,
    min: Number(one(sp.min)) || undefined,
    max: Number(one(sp.max)) || undefined,
    rating: Number(one(sp.rating)) || undefined,
    fast: one(sp.fast) === "1",
    page,
    perPage: 24,
  });

  const { node, breadcrumbs, children, siblings, brands, priceRange } = data;
  const childCount = new Map((res.facets?.categories ?? []).map((f) => [f.path, f.count]));
  const rail = children.length ? children : siblings;
  const pageHref = (p: number) => {
    const next = new URLSearchParams();
    for (const [k, v] of Object.entries(sp)) {
      const val = one(v);
      if (val !== undefined && k !== "page") next.set(k, val);
    }
    if (p > 1) next.set("page", String(p));
    const s = next.toString();
    return `/c/${path}${s ? `?${s}` : ""}`;
  };
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: [{ name: "Home", path: "" }, ...breadcrumbs.map((b) => ({ name: b.name, path: `/c/${b.path}` }))].map((b, i) => ({
      "@type": "ListItem",
      position: i + 1,
      name: b.name,
      item: `${BASE}${b.path}`,
    })),
  };

  return (
    <div data-store={store} className="mx-auto max-w-7xl px-4 pb-24 pt-4 md:pb-8">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />

      <nav aria-label={t("pd.breadcrumb")} className="no-scrollbar -mx-4 overflow-x-auto px-4">
        <ol className="flex items-center gap-1 whitespace-nowrap text-xs font-semibold text-ink-3">
          <li>
            <Link href="/" className="inline-flex min-h-8 items-center hover:text-accent">
{t("nav.home")}
</Link>
          </li>
          <li className="flex items-center gap-1">
            <Icon name="right" size={12} />
            <Link href="/categories" className="inline-flex min-h-8 items-center hover:text-accent">
{t("nav.categories")}
</Link>
          </li>
          {breadcrumbs.map((b, i) => (
            <li key={b.path} className="flex items-center gap-1">
              <Icon name="right" size={12} />
              {i === breadcrumbs.length - 1 ? (
                <span aria-current="page" className="text-ink-2">
{catLabel(b, bn)}
                </span>
              ) : (
                <Link href={href(b.path, store)} className="inline-flex min-h-8 items-center hover:text-accent">
                  {catLabel(b, bn)}
</Link>
              )}
            </li>
          ))}
        </ol>
      </nav>

      <header className="mt-2 flex items-start gap-3">
        <CatThumb node={node} className="h-14 w-14 rounded-2xl" eager />
        <div className="min-w-0">
          <h1 className="text-xl font-extrabold tracking-tight sm:text-2xl">{catLabel(node, bn)}</h1>
          <p className="text-sm text-ink-2">
            {(bn ? node.name : node.nameBn) && (
<span lang={bn ? "en" : "bn"} className="mr-2">
{bn ? node.name : node.nameBn}
</span>
)}
{t("sp.count", { count: res.total })}
            {priceRange && priceRange.maxBdt > 0 && (
              <>
                {" "}
                {t("c.range", { min: bdt(priceRange.minBdt), max: bdt(priceRange.maxBdt) })}
              </>
            )}
          </p>
        </div>
      </header>

      {/* store tabs */}
      <nav aria-label={t("cat.store")} className="no-scrollbar -mx-4 mt-4 flex gap-2 overflow-x-auto px-4">
        {STORE_MODES.map((m) => {
          const sells = m === "all" || node.stores.includes(m);
          const on = m === store;
          const label = m === "all" ? t("search.allStores") : STORES[m].name;
          return sells ? (
            <Link
              key={m}
              href={href(path, m)}
              aria-current={on ? "page" : undefined}
              data-store={m}
              className={"sn inline-flex min-h-11 shrink-0 items-center rounded-full px-4 text-sm " + (on ? "shadow-[var(--glow)]" : "bg-surface shadow-[var(--shadow-1)]")}
              style={on ? { background: "var(--sn-fill)" } : undefined}
            >
              <StoreName mode={m} name={label} fill={on} />
            </Link>
          ) : (
            <span key={m} aria-disabled="true" title={t("c.notSold", { source: STORES[m].source })} className="inline-flex min-h-11 shrink-0 items-center rounded-full px-4 text-sm opacity-50">
              <StoreName mode={m} name={label} />
            </span>
          );
        })}
      </nav>

      {/* sub-categories as photo tiles (siblings on a leaf), like Myntra and MoveOn: a picture says "saree" faster than a word */}
      {rail.length > 0 && (
        <nav aria-label={children.length ? t("c.sub") : t("c.related")} className="no-scrollbar snap-x-rail -mx-4 mt-4 flex gap-3 overflow-x-auto px-4 pb-1">
          {rail.map((c, i) => {
            const n = children.length ? (childCount.get(c.path) ?? c.count) : c.count;
            return (
              <Link
                key={c.path}
                href={href(c.path, store)}
                className="rise group flex w-[76px] shrink-0 flex-col items-center gap-1.5 text-center sm:w-[88px]"
                style={{ ["--i" as string]: Math.min(i, 10) }}
              >
                <CatThumb node={c} className="h-[72px] w-[72px] rounded-2xl sm:h-[84px] sm:w-[84px]" eager={i < 6} />
                <span className="line-clamp-2 text-[12px] font-semibold leading-tight text-ink">{catLabel(c, bn)}</span>
                {!!n && <span className="-mt-1 text-[11px] text-ink-3">{num(n)}</span>}
              </Link>
            );
          })}
        </nav>
      )}

      {brands.length > 0 && (
        <SafeSection label={t("c.brands")} quiet>
          <div className="mt-4 flex flex-wrap items-center gap-2">
            <span className="text-xs font-bold uppercase tracking-wide text-ink-3">{t("c.brands")}</span>
            {brands.slice(0, 8).map((b) => (
              <Link
                key={b.name}
                href={`/search?${new URLSearchParams({ q: "", ...(store !== "all" ? { store } : {}), category: path, brand: b.name }).toString()}`}
                aria-label={t("c.shopBrand", { brand: b.name, category: catLabel(node, bn) })}
                className="cm-chip inline-flex min-h-11 items-center gap-1 rounded-full bg-surface-2 px-3 text-xs font-semibold text-ink-2 sm:min-h-9"
              >
                {b.name} <span className="text-ink-3">{b.count}</span>
              </Link>
            ))}
          </div>
        </SafeSection>
      )}

      <div className="mt-4 flex flex-col gap-3">
        <Suspense>
          <SortAndFilterBar total={res.total} hideCategory brands={res.facets?.brands} />
          <ActiveFilters />
        </Suspense>
        {res.stale && <StaleNote what="results" />}
      </div>

      <div className="mt-4 flex gap-6">
        <Suspense>
          <FilterSidebar hideCategory brands={res.facets?.brands} />
        </Suspense>
        <div className="min-w-0 flex-1">
          {res.items.length ? (
            <EnabledGrid items={res.items} />
          ) : (
            <div className="card mx-auto max-w-md p-8 text-center">
              <CatThumb node={{ icon: node.icon }} className="mx-auto h-16 w-16 rounded-2xl" iconSize={30} />
              <h2 className="mt-4 text-lg font-extrabold">{t("c.nothing", { name: catLabel(node, bn) })}</h2>
              <p className="mt-1 text-sm text-ink-2">
                {store === "all" ? t("c.notYet") : t("c.storeNothing", { store: STORES[store].name })}{t("c.tellUs")}
              </p>
              <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-center">
                <Link href={`/request?from=category&category=${encodeURIComponent(path)}&q=${encodeURIComponent(node.name)}`} className="btn btn-primary">
                  {t("common.requestProduct")} <Icon name="arrow" size={18} className="arrow" />
                </Link>
                {store !== "all" ? (
                  <Link href={href(path, "all")} className="btn btn-ghost">
{t("c.tryEvery")}
</Link>
                ) : (
                  <Link href="/categories" className="btn btn-ghost">
{t("cat.allCategories")}
</Link>
                )}
              </div>
            </div>
          )}
          <Pager page={res.page || page} pages={res.pages} href={pageHref} />
        </div>
      </div>
    </div>
  );
}
