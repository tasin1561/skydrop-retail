import { byMarket, deals } from "@/lib/catalog";
import { MARKET_IDS, STORES } from "@/lib/markets";
import type { StoreMode } from "@/lib/types";
import { StoreHero } from "@/components/home/StoreHero";
import { StoreCards } from "@/components/home/StoreCards";
import { CategoryChips } from "@/components/home/CategoryChips";
import { PromoStrip } from "@/components/home/PromoStrip";
import { HowItWorks } from "@/components/home/HowItWorks";
import { EnabledRail } from "@/components/home/Gates";

function heroImages(m: StoreMode) {
  return byMarket(m, 6)
    .map((p) => p.image)
    .filter(Boolean)
    .slice(0, 3);
}

export default function Home() {
  const scenes = (["all", ...MARKET_IDS] as StoreMode[]).map((mode) => ({
    mode,
    images: heroImages(mode === "all" ? "amazon" : mode),
  }));
  return (
    <div className="mx-auto max-w-7xl pt-3 sm:px-4">
      <StoreHero scenes={scenes} />
      <CategoryChips mode="all" />
      <StoreCards />
      <PromoStrip />
      <EnabledRail title="Today's biggest savings" note="Across every connected store" href="/search?q=&store=all&sort=discount" items={deals("all", 14)} />
      <HowItWorks />
      {MARKET_IDS.map((m) => (
        <EnabledRail
          key={m}
          title={`Popular in ${STORES[m].name}`}
          note={`Sourced from ${STORES[m].source}`}
          href={`/store/${m}`}
          items={byMarket(m, 14)}
        />
      ))}
      <EnabledRail title="Phones and audio" href="/search?q=&store=all&category=electronics" items={byMarket("all", 14, "electronics")} />
      <EnabledRail title="Indian pantry favourites" href="/search?q=&store=all&category=grocery" items={byMarket("all", 14, "grocery")} />
    </div>
  );
}
