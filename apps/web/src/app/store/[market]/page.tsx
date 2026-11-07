import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { byMarket, deals } from "@/lib/data";
import { isMarket, MARKET_IDS, STORES, storeText } from "@/lib/markets";
import { getT } from "@/i18n/server";
import { StoreHero } from "@/components/home/StoreHero";
import { CategoryChips } from "@/components/home/CategoryChips";
import { HowItWorks } from "@/components/home/HowItWorks";
import { PromoStrip } from "@/components/home/PromoStrip";
import { CmsFlash, CmsHero, CmsTiles } from "@/components/cms/CmsSlots";
import { EnabledGrid, EnabledRail, StoreGate } from "@/components/home/Gates";

export function generateStaticParams() {
  return MARKET_IDS.map((market) => ({ market }));
}

export async function generateMetadata({ params }: { params: Promise<{ market: string }> }): Promise<Metadata> {
  const { market } = await params;
  if (!isMarket(market)) return {};
  const { t } = await getT();
  return { title: t("storePage.metaTitle", { store: STORES[market].name, source: STORES[market].source }), description: storeText(market, t).tagline };
}

export default async function StorePage({ params }: { params: Promise<{ market: string }> }) {
  const { market } = await params;
  if (!isMarket(market)) notFound();
  const { t } = await getT();
  const s = storeText(market, t);
  const [top, dealItems, all, perCat] = await Promise.all([
    byMarket(market, 6),
    deals(market, 14),
    byMarket(market, 30),
    Promise.all(s.categories.map((c) => byMarket(market, 14, c.slug))),
  ]);
  const images = top.map((p) => p.image).slice(0, 3);
  return (
    <StoreGate market={market}>
      <div data-store={market} className="mx-auto max-w-7xl pt-3 sm:px-4">
        <StoreHero scenes={[{ mode: market, images }]} fixed />
        <CategoryChips mode={market} />
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
        <section className="mt-10 px-4 sm:px-0">
          <h2 className="mb-3 text-lg font-extrabold tracking-tight sm:text-xl">{t("storePage.everything", { store: s.name })}</h2>
          <EnabledGrid items={all} />
        </section>
      </div>
    </StoreGate>
  );
}
