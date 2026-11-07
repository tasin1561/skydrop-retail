import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { byMarket, deals } from "@/lib/data";
import { isMarket, MARKET_IDS, STORES } from "@/lib/markets";
import { StoreHero } from "@/components/home/StoreHero";
import { CategoryChips } from "@/components/home/CategoryChips";
import { HowItWorks } from "@/components/home/HowItWorks";
import { PromoStrip } from "@/components/home/PromoStrip";
import { EnabledGrid, EnabledRail, StoreGate } from "@/components/home/Gates";

export function generateStaticParams() {
  return MARKET_IDS.map((market) => ({ market }));
}

export async function generateMetadata({ params }: { params: Promise<{ market: string }> }): Promise<Metadata> {
  const { market } = await params;
  if (!isMarket(market)) return {};
  return { title: `${STORES[market].name}: products from ${STORES[market].source}`, description: STORES[market].tagline };
}

export default async function StorePage({ params }: { params: Promise<{ market: string }> }) {
  const { market } = await params;
  if (!isMarket(market)) notFound();
  const s = STORES[market];
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
        <PromoStrip />
        <EnabledRail title="Deals of the day" note={`Biggest discounts on ${s.source}`} href={`/search?q=&store=${market}&sort=discount`} items={dealItems} />
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
          <h2 className="mb-3 text-lg font-extrabold tracking-tight sm:text-xl">Everything in {s.name}</h2>
          <EnabledGrid items={all} />
        </section>
      </div>
    </StoreGate>
  );
}
