import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { searchProducts } from "@/lib/data";
import { isStoreMode, STORES } from "@/lib/markets";
import { Icon } from "@/components/Icon";
import { EnabledGrid } from "@/components/home/Gates";
import { FilterSidebar, SortAndFilterBar } from "@/components/search/Filters";
import { TrackSearch } from "@/components/tracking/TrackSearch";
import { RequestCta } from "@/components/shopping/RequestLinks";
import { HelpMeChoose } from "@/components/assistant/AskAssistant";
import { LiveSearch } from "@/components/resilience/LiveSearch";
import { StaleNote } from "@/components/resilience/LiveRefresh";
import { SafeSection } from "@/components/resilience/SafeSection";
import { EmptyArt } from "@/components/motion/EmptyArt";
import { getT } from "@/i18n/server";
import { hasKey } from "@/i18n/core";

type SP = Record<string, string | string[] | undefined>;
const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);

export async function generateMetadata({ searchParams }: { searchParams: Promise<SP> }): Promise<Metadata> {
  const q = one((await searchParams).q);
  const { t } = await getT();
  return { title: q ? t("sp.metaQ", { q }) : t("sp.metaBrowse") };
}

export default async function SearchPage({ searchParams }: { searchParams: Promise<SP> }) {
  const sp = await searchParams;
  const { t, digits } = await getT();
  const q = one(sp.q) ?? "";
  const storeRaw = one(sp.store);
  const store = isStoreMode(storeRaw) ? storeRaw : "all";
  const page = Number(one(sp.page) ?? 1) || 1;
  const res = await searchProducts({
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

  const catKey = "store.cat." + one(sp.category);
  const heading = q ? t("sp.resultsFor", { q }) : one(sp.category) ? (hasKey(catKey) ? t(catKey) : `${one(sp.category)![0].toUpperCase()}${one(sp.category)!.slice(1)}`) : t("sp.allProducts");

  return (
    <div data-store={store} className="mx-auto max-w-7xl px-4 pb-24 pt-4 md:pb-8">
      <div className="mb-4 flex flex-col gap-3">
        <div>
          <p className="text-xs font-semibold text-accent">
            {store === "all" ? t("sp.searchingAll") : t("sp.searchingStore", { store: STORES[store].name, source: STORES[store].source })}
          </p>
          <h1 className="text-xl font-extrabold tracking-tight sm:text-2xl">{heading}</h1>
          <p className="text-sm text-ink-2">
            {res.status === "pending" && !res.total ? t("sp.looking") : t("sp.count", { count: res.total })}
{t("sp.priceNote")}
          </p>
        </div>
        <Suspense>
          <SortAndFilterBar total={res.total} />
        </Suspense>
        <TrackSearch q={q} store={store} ids={res.items.slice(0, 10).map((p) => p.id)} />
        {res.items.length > 1 && (
          <SafeSection label={t("pd.assistant")} quiet>
            <HelpMeChoose query={q} />
          </SafeSection>
        )}
        {res.stale && <StaleNote what="results" />}
        {res.status === "pending" && res.jobId && <LiveSearch jobId={res.jobId} q={q} store={store} empty={!res.items.length} />}
      </div>

      <div className="flex gap-6">
        <Suspense>
          <FilterSidebar />
        </Suspense>
        <div className="min-w-0 flex-1">
          {res.items.length ? (
            <EnabledGrid items={res.items} />
          ) : res.status === "pending" ? null : (
            <div className="card fade-in mx-auto flex max-w-md flex-col items-center p-8 text-center">
              <EmptyArt kind="search" />
              <h2 className="mt-3 text-lg font-extrabold">{t("sp.noMatches")}</h2>
              <p className="mt-1 text-sm text-ink-2">
                {t("sp.noMatchesHint")}
              </p>
              <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-center">
                <Link href={`/request?q=${encodeURIComponent(q)}`} className="btn btn-primary">
                  {t("sp.requestThis")} <Icon name="arrow" size={18} className="arrow" />
                </Link>
                <Link href="/search?q=&store=all" className="btn btn-ghost">
{t("sp.browseAll")}
</Link>
              </div>
            </div>
          )}

          <SafeSection label={t("common.requestProduct")} quiet>
            <RequestCta q={q} count={res.total} />
          </SafeSection>

          {res.pages > 1 && (
            <nav aria-label={t("pager.label")} className="mt-8 flex items-center justify-center gap-1">
              {page > 1 && (
                <Link href={linkFor(page - 1)} className="grid h-11 w-11 place-items-center rounded-full bg-surface shadow-[var(--shadow-1)]" aria-label={t("pager.prev")}>
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
                    {digits(String(n).padStart(2, "0"))}
                  </Link>
                ))}
              {page < res.pages && (
                <Link href={linkFor(page + 1)} className="grid h-11 w-11 place-items-center rounded-full bg-surface shadow-[var(--shadow-1)]" aria-label={t("pager.next")}>
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
