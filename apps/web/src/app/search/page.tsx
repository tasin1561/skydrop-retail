import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { searchProducts } from "@/lib/catalog";
import { isStoreMode, STORES } from "@/lib/markets";
import { Icon } from "@/components/Icon";
import { EnabledGrid } from "@/components/home/Gates";
import { FilterSidebar, SortAndFilterBar } from "@/components/search/Filters";

type SP = Record<string, string | string[] | undefined>;
const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);

export async function generateMetadata({ searchParams }: { searchParams: Promise<SP> }): Promise<Metadata> {
  const q = one((await searchParams).q);
  return { title: q ? `"${q}" from Indian stores` : "Browse Indian stores" };
}

export default async function SearchPage({ searchParams }: { searchParams: Promise<SP> }) {
  const sp = await searchParams;
  const q = one(sp.q) ?? "";
  const storeRaw = one(sp.store);
  const store = isStoreMode(storeRaw) ? storeRaw : "all";
  const page = Number(one(sp.page) ?? 1) || 1;
  const res = searchProducts({
    q,
    store,
    category: one(sp.category) || undefined,
    sort: (one(sp.sort) as never) || undefined,
    min: Number(one(sp.min)) || undefined,
    max: Number(one(sp.max)) || undefined,
    rating: Number(one(sp.rating)) || undefined,
    fast: one(sp.fast) === "1",
    page,
    perPage: 24,
  });

  const linkFor = (p: number) => {
    const next = new URLSearchParams();
    for (const [k, v] of Object.entries(sp)) {
      const val = one(v);
      if (val !== undefined) next.set(k, val);
    }
    next.set("page", String(p));
    return `/search?${next.toString()}`;
  };

  const heading = q ? `Results for "${q}"` : one(sp.category) ? `${one(sp.category)![0].toUpperCase()}${one(sp.category)!.slice(1)}` : "All products";

  return (
    <div data-store={store} className="mx-auto max-w-7xl px-4 pb-24 pt-4 md:pb-8">
      <div className="mb-4 flex flex-col gap-3">
        <div>
          <p className="text-xs font-semibold text-accent">
            {store === "all" ? "Searching all connected stores" : `Searching ${STORES[store].name} · ${STORES[store].source}`}
          </p>
          <h1 className="text-xl font-extrabold tracking-tight sm:text-2xl">{heading}</h1>
          <p className="text-sm text-ink-2">
            {res.total} {res.total === 1 ? "product" : "products"} · prices in BDT include import duty and service
          </p>
        </div>
        <Suspense>
          <SortAndFilterBar total={res.total} />
        </Suspense>
      </div>

      <div className="flex gap-6">
        <Suspense>
          <FilterSidebar />
        </Suspense>
        <div className="min-w-0 flex-1">
          {res.items.length ? (
            <EnabledGrid items={res.items} />
          ) : (
            <div className="card mx-auto max-w-md p-8 text-center">
              <span className="chip-icon mx-auto h-14 w-14">
                <Icon name="search" size={26} />
              </span>
              <h2 className="mt-4 text-lg font-extrabold">Nothing in our catalog yet</h2>
              <p className="mt-1 text-sm text-ink-2">
                Once live search is connected, we fetch this straight from the Indian store for you. Try a broader word for now.
              </p>
              <Link href="/search?q=&store=all" className="btn btn-primary mt-5">
                Browse everything <Icon name="arrow" size={18} className="arrow" />
              </Link>
            </div>
          )}

          {res.pages > 1 && (
            <nav aria-label="Pages" className="mt-8 flex items-center justify-center gap-1">
              {page > 1 && (
                <Link href={linkFor(page - 1)} className="grid h-11 w-11 place-items-center rounded-full bg-surface shadow-[var(--shadow-1)]" aria-label="Previous page">
                  <Icon name="left" />
                </Link>
              )}
              {Array.from({ length: res.pages }, (_, i) => i + 1)
                .filter((n) => Math.abs(n - page) < 3 || n === 1 || n === res.pages)
                .map((n) => (
                  <Link
                    key={n}
                    href={linkFor(n)}
                    aria-current={n === page ? "page" : undefined}
                    className={
                      "grid h-11 min-w-11 place-items-center rounded-full px-3 text-sm font-bold transition " +
                      (n === page ? "bg-accent text-white shadow-[var(--glow)]" : "bg-surface text-ink-2 shadow-[var(--shadow-1)] hover:text-accent")
                    }
                  >
                    {String(n).padStart(2, "0")}
                  </Link>
                ))}
              {page < res.pages && (
                <Link href={linkFor(page + 1)} className="grid h-11 w-11 place-items-center rounded-full bg-surface shadow-[var(--shadow-1)]" aria-label="Next page">
                  <Icon name="right" />
                </Link>
              )}
            </nav>
          )}
        </div>
      </div>
    </div>
  );
}
