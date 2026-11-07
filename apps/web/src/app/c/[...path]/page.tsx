import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { getCategoryPage, searchProducts } from "@/lib/data";
import { isStoreMode, STORES, STORE_MODES } from "@/lib/markets";
import { formatBdt } from "@/lib/pricing";
import type { StoreMode } from "@/lib/types";
import { Icon } from "@/components/Icon";
import { EnabledGrid } from "@/components/home/Gates";
import { FilterSidebar, SortAndFilterBar } from "@/components/search/Filters";
import { SafeSection } from "@/components/resilience/SafeSection";
import { StaleNote } from "@/components/resilience/LiveRefresh";
import { Pager } from "@/components/categories/Pager";
import "@/components/categories/categories.css";

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
  const data = await getCategoryPage(path, store);
  if (!data) return { title: "Category not found" };
  const name = data.node.name;
  const parent = data.breadcrumbs.length > 1 ? data.breadcrumbs[data.breadcrumbs.length - 2].name : null;
  const title = `${name}${parent ? ` · ${parent}` : ""} from Indian stores`;
  const description = `Shop ${name.toLowerCase()} from amazon.in, Flipkart and BigBasket in Bangladesh. Prices in BDT with import duty included, delivered to your door by Skydrop.`;
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
  const data = await getCategoryPage(path, store);
  if (!data) notFound();

  const page = Number(one(sp.page) ?? 1) || 1;
  const res = await searchProducts({
    q: one(sp.q) || undefined,
    store,
    category: path,
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

      <nav aria-label="Breadcrumb" className="no-scrollbar -mx-4 overflow-x-auto px-4">
        <ol className="flex items-center gap-1 whitespace-nowrap text-xs font-semibold text-ink-3">
          <li>
            <Link href="/" className="inline-flex min-h-8 items-center hover:text-accent">
              Home
            </Link>
          </li>
          <li className="flex items-center gap-1">
            <Icon name="right" size={12} />
            <Link href="/categories" className="inline-flex min-h-8 items-center hover:text-accent">
              Categories
            </Link>
          </li>
          {breadcrumbs.map((b, i) => (
            <li key={b.path} className="flex items-center gap-1">
              <Icon name="right" size={12} />
              {i === breadcrumbs.length - 1 ? (
                <span aria-current="page" className="text-ink-2">
                  {b.name}
                </span>
              ) : (
                <Link href={href(b.path, store)} className="inline-flex min-h-8 items-center hover:text-accent">
                  {b.name}
                </Link>
              )}
            </li>
          ))}
        </ol>
      </nav>

      <header className="mt-2 flex items-start gap-3">
        <span className="chip-icon is-active h-12 w-12 shrink-0 rounded-2xl">
          <Icon name={node.icon} size={24} />
        </span>
        <div className="min-w-0">
          <h1 className="text-xl font-extrabold tracking-tight sm:text-2xl">{node.name}</h1>
          <p className="text-sm text-ink-2">
            {node.nameBn && (
              <span lang="bn" className="mr-2">
                {node.nameBn}
              </span>
            )}
            {res.total} {res.total === 1 ? "product" : "products"}
            {priceRange && priceRange.maxBdt > 0 && (
              <>
                {" "}
                · {formatBdt(priceRange.minBdt)} to {formatBdt(priceRange.maxBdt)}
              </>
            )}
          </p>
        </div>
      </header>

      {/* store tabs */}
      <nav aria-label="Store" className="no-scrollbar -mx-4 mt-4 flex gap-2 overflow-x-auto px-4">
        {STORE_MODES.map((m) => {
          const sells = m === "all" || node.stores.includes(m);
          const on = m === store;
          const label = m === "all" ? "All stores" : STORES[m].name;
          return sells ? (
            <Link
              key={m}
              href={href(path, m)}
              aria-current={on ? "page" : undefined}
              className={
                "inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-full px-4 text-sm font-semibold " +
                (on ? "bg-accent text-white shadow-[var(--glow)]" : "bg-surface text-ink-2 shadow-[var(--shadow-1)]")
              }
            >
              <Icon name={STORES[m].icon} size={15} /> {label}
            </Link>
          ) : (
            <span key={m} aria-disabled="true" title={`${STORES[m].source} doesn't sell this`} className="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-full px-4 text-sm text-ink-3 opacity-60">
              <Icon name={STORES[m].icon} size={15} /> {label}
            </span>
          );
        })}
      </nav>

      {/* child category rail (siblings on a leaf) */}
      {rail.length > 0 && (
        <nav aria-label={children.length ? "Subcategories" : "Related categories"} className="no-scrollbar snap-x-rail -mx-4 mt-4 flex gap-2 overflow-x-auto px-4 pb-1">
          {rail.map((c, i) => {
            const n = children.length ? (childCount.get(c.path) ?? c.count) : c.count;
            return (
              <Link
                key={c.path}
                href={href(c.path, store)}
                className="cm-chip rise flex min-h-11 shrink-0 items-center gap-2 rounded-2xl bg-surface py-1.5 pl-1.5 pr-3 shadow-[var(--shadow-1)]"
                style={{ ["--i" as string]: i }}
              >
                <span className="chip-icon h-8 w-8 rounded-xl">
                  <Icon name={c.icon} size={16} />
                </span>
                <span className="text-sm font-semibold">{c.name}</span>
                {!!n && <span className="text-xs text-ink-3">{n}</span>}
              </Link>
            );
          })}
        </nav>
      )}

      {brands.length > 0 && (
        <SafeSection label="Top brands" quiet>
          <div className="mt-4 flex flex-wrap items-center gap-2">
            <span className="text-xs font-bold uppercase tracking-wide text-ink-3">Top brands</span>
            {brands.slice(0, 8).map((b) => (
              <Link
                key={b.name}
                href={href(path, store, { q: b.name })}
                className="cm-chip inline-flex min-h-9 items-center gap-1 rounded-full bg-surface-2 px-3 text-xs font-semibold text-ink-2"
              >
                {b.name} <span className="text-ink-3">{b.count}</span>
              </Link>
            ))}
          </div>
        </SafeSection>
      )}

      <div className="mt-4 flex flex-col gap-3">
        <Suspense>
          <SortAndFilterBar total={res.total} hideCategory />
        </Suspense>
        {res.stale && <StaleNote what="results" />}
      </div>

      <div className="mt-4 flex gap-6">
        <Suspense>
          <FilterSidebar hideCategory />
        </Suspense>
        <div className="min-w-0 flex-1">
          {res.items.length ? (
            <EnabledGrid items={res.items} />
          ) : (
            <div className="card mx-auto max-w-md p-8 text-center">
              <span className="chip-icon mx-auto h-14 w-14">
                <Icon name={node.icon} size={26} />
              </span>
              <h2 className="mt-4 text-lg font-extrabold">Nothing in {node.name} yet</h2>
              <p className="mt-1 text-sm text-ink-2">
                {store === "all" ? "We haven't brought these in yet." : `${STORES[store].name} has nothing here in our catalog yet.`} Tell us what you need and
                we&apos;ll source it from India and send you a price.
              </p>
              <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-center">
                <Link href={`/request?from=category&category=${encodeURIComponent(path)}&q=${encodeURIComponent(node.name)}`} className="btn btn-primary">
                  Request a product <Icon name="arrow" size={18} className="arrow" />
                </Link>
                {store !== "all" ? (
                  <Link href={href(path, "all")} className="btn btn-ghost">
                    Try every store
                  </Link>
                ) : (
                  <Link href="/categories" className="btn btn-ghost">
                    All categories
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
