import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { byMarket, deals, searchProducts } from "@/lib/data";
import { parseBrands } from "@/lib/brands";
import { isMarket, MARKET_IDS, STORES, storeText } from "@/lib/markets";
import { getT } from "@/i18n/server";
import { StoreHero } from "@/components/home/StoreHero";
import { CategoryChips } from "@/components/home/CategoryChips";
import { HowItWorks } from "@/components/home/HowItWorks";
import { PromoStrip } from "@/components/home/PromoStrip";
import { CmsFlash, CmsHero, CmsTiles } from "@/components/cms/CmsSlots";
import { EnabledGrid, EnabledRail, StoreGate } from "@/components/home/Gates";
import { StoreBrowse, StoreQuickBar } from "@/components/home/StoreBrowse";
import Link from "next/link";
import { Icon } from "@/components/Icon";

export function generateStaticParams() {
  return MARKET_IDS.map((market) => ({ market }));
}

export async function generateMetadata({ params }: { params: Promise<{ market: string }> }): Promise<Metadata> {
  const { market } = await params;
  if (!isMarket(market)) return {};
  const { t } = await getT();
  return { title: t("storePage.metaTitle", { store: STORES[market].name, source: STORES[market].source }), description: storeText(market, t).tagline };
}

export default async function StorePage({
  params,
  searchParams,
}: {
  params: Promise<{ market: string }>;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  const { market } = await params;
  if (!isMarket(market)) notFound();
  const { t } = await getT();
  const s = storeText(market, t);
  // ?brand=A,B narrows the "Everything" grid (the URL is the state; the rail's Brand section edits it).
  const brand = parseBrands((await searchParams).brand);
  const [top, dealItems, all, perCat, facets] = await Promise.all([
    byMarket(market, 6),
    deals(market, 14),
    byMarket(market, 30, undefined, brand),
    Promise.all(s.categories.map((c) => byMarket(market, 14, c.slug))),
    // store-wide top brands for the rail (search facets ignore the brand filter, so no brand here)
    searchProducts({ q: "", store: market, page: 1 }).then((r) => r.facets?.brands ?? []),
  ]);
  const images = top.map((p) => p.image).slice(0, 3);
  return (
    <StoreGate market={market}>
      <div data-store={market} className="mx-auto max-w-7xl pt-3 sm:px-4">
        <nav aria-label={t("pd.breadcrumb")} className="mb-3 px-4 sm:px-0">
          <ol className="flex items-center gap-1 whitespace-nowrap text-xs font-medium text-ink-3">
            <li>
              <Link href="/" className="inline-flex min-h-8 items-center hover:text-accent">
                {t("nav.home")}
              </Link>
            </li>
            <li className="flex items-center gap-1">
              <Icon name="right" size={12} />
              <span aria-current="page" className="text-ink-2">
                {s.name}
              </span>
            </li>
          </ol>
        </nav>
        <StoreHero scenes={[{ mode: market, images }]} fixed />
        <CategoryChips mode={market} />
        <StoreQuickBar market={market} hasBrands={facets.length > 0} />
        <CmsHero store={market} />
        <PromoStrip />
        <CmsTiles store={market} />
        <CmsFlash store={market} />
        <EnabledRail title={t("storePage.dealsOfDay")} note={t("storePage.biggestDiscounts", { source: s.source })} href={`/search?q=&store=${market}&sort=discount`} items={dealItems} />
        {s.categories.map((c, i) => (
          <EnabledRail
            key={c.slug}
            title={c.label}
            href={`/search?q=&store=${market}&category=${c.slug}`}
            items={perCat[i]}
          />
        ))}
        <HowItWorks />
        <section id="everything" className="mt-10 scroll-mt-[calc(var(--hdr-h,0px)+1rem)]">
          <h2 className="px-4 text-lg font-bold tracking-tight sm:px-0 sm:text-xl">{t("storePage.everything", { store: s.name })}</h2>
          <StoreBrowse market={market} brands={facets} selected={brand}>
            {all.length ? <EnabledGrid items={all} /> : <p className="card p-6 text-center text-sm text-ink-2">{t("sp.noMatches")}</p>}
          </StoreBrowse>
        </section>
      </div>
    </StoreGate>
  );
}
