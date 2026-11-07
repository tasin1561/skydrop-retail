import { byMarket, offersFeed } from "@/lib/data";
import { MARKET_IDS, STORES } from "@/lib/markets";
import type { StoreMode } from "@/lib/types";
import { StoreHero } from "@/components/home/StoreHero";
import { StoreCards } from "@/components/home/StoreCards";
import { CategoryChips } from "@/components/home/CategoryChips";
import { PromoStrip } from "@/components/home/PromoStrip";
import { HowItWorks } from "@/components/home/HowItWorks";
import { EnabledRail } from "@/components/home/Gates";
import { RecentlyViewed } from "@/components/home/RecentlyViewed";
import { CmsFlash, CmsHero, CmsTiles } from "@/components/cms/CmsSlots";

async function heroImages(m: StoreMode) {
  return (await byMarket(m, 6))
    .map((p) => p.image)
    .filter(Boolean)
    .slice(0, 3);
}

export default async function Home() {
  const modes = ["all", ...MARKET_IDS] as StoreMode[];
  const [scenes, dealItems, popular, electronics, grocery] = await Promise.all([
    Promise.all(modes.map(async (mode) => ({ mode, images: await heroImages(mode === "all" ? "amazon" : mode) }))),
    offersFeed("all", 14),
    Promise.all(MARKET_IDS.map((m) => byMarket(m, 14))),
    byMarket("all", 14, "electronics"),
    byMarket("all", 14, "grocery"),
  ]);
  return (
    <div className="mx-auto max-w-7xl pt-3 sm:px-4">
      <StoreHero scenes={scenes} />
      <CategoryChips mode="all" />
      <CmsHero store="all" />
      <StoreCards />
      <RecentlyViewed />
      <PromoStrip />
      <CmsTiles store="all" />
      <CmsFlash store="all" />
      <EnabledRail title="Live deals and offers" note="Coupons, deals and bank offers from every connected store" href="/deals" items={dealItems} />
      <HowItWorks />
      {MARKET_IDS.map((m, i) => (
        <EnabledRail
          key={m}
          title={`Popular in ${STORES[m].name}`}
          note={`Sourced from ${STORES[m].source}`}
          href={`/store/${m}`}
          items={popular[i]}
        />
      ))}
      <EnabledRail title="Phones and audio" href="/search?q=&store=all&category=electronics" items={electronics} />
      <EnabledRail title="Indian pantry favourites" href="/search?q=&store=all&category=grocery" items={grocery} />
    </div>
  );
}
