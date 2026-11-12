import Link from "next/link";
import { Icon } from "@/components/Icon";
import { byMarket, offersFeed, searchProducts } from "@/lib/data";
import { MARKET_IDS, STORES } from "@/lib/markets";
import type { Product, StoreMode } from "@/lib/types";
import { StoreHero } from "@/components/home/StoreHero";
import { StoreCards } from "@/components/home/StoreCards";
import { CategoryTiles } from "@/components/home/CategoryTiles";
import { PromoStrip } from "@/components/home/PromoStrip";
import { HowItWorks } from "@/components/home/HowItWorks";
import { EnabledGrid, EnabledRail } from "@/components/home/Gates";
import { RecentlyViewed } from "@/components/home/RecentlyViewed";
import { CmsFlash, CmsHero, CmsTiles } from "@/components/cms/CmsSlots";
import { DealOfTheDay } from "@/components/home/DealOfTheDay";
import { SafeSection } from "@/components/resilience/SafeSection";
import { getT } from "@/i18n/server";

const renderedAt = () => Date.now();

async function heroImages(m: StoreMode) {
  return (await byMarket(m, 6))
    .map((p) => p.image)
    .filter(Boolean)
    .slice(0, 3);
}

/** Top-rated products of a category (our database only: an empty query never starts a live store fetch). */
async function top(category: string): Promise<Product[]> {
  try {
    return (await searchProducts({ q: "", category, sort: "rating" })).items;
  } catch {
    return [];
  }
}

/** Women's clothing, women's fashion and makeup, mixed so no single store or category fills the grid, no repeats. */
function interleave(lists: Product[][], limit: number): Product[] {
  const out: Product[] = [];
  const seen = new Set<string>();
  for (let i = 0; out.length < limit && lists.some((l) => i < l.length); i++) {
    for (const l of lists) {
      const p = l[i];
      if (p && !seen.has(p.id) && out.length < limit) {
        seen.add(p.id);
        out.push(p);
      }
    }
  }
  return out;
}

export default async function Home() {
  const modes = ["all", ...MARKET_IDS] as StoreMode[];
  const [{ t }, scenes, dealItems, popular, electronics, womenClothing, women, makeup] = await Promise.all([
    getT(),
    Promise.all(modes.map(async (mode) => ({ mode, images: await heroImages(mode === "all" ? "amazon" : mode) }))),
    offersFeed("all", 14),
    Promise.all(MARKET_IDS.map((m) => byMarket(m, 14))),
    byMarket("all", 14, "electronics"),
    top("fashion/women/clothing"),
    top("fashion/women"),
    top("beauty-personal-care/makeup"),
  ]);
  const forYou = interleave([womenClothing, women, makeup], 12);
  // Phone first: categories with photos open the page; the big store banner is for wider screens.
  return (
    <div className="mx-auto max-w-7xl pt-1 sm:px-4 sm:pt-3">
      <div className="hidden sm:block">
        <StoreHero scenes={scenes} />
      </div>
      <SafeSection label={t("home.shopByCategory")} quiet>
        <CategoryTiles />
      </SafeSection>
      <CmsFlash store="all" />
      <SafeSection label={t("home.dealOfDay")} quiet>
        <DealOfTheDay items={[...dealItems, ...popular.flat()]} now={renderedAt()} />
      </SafeSection>
      {forYou.length > 0 && (
        <section className="mt-8" aria-labelledby="for-you">
          <div className="mb-3 px-4 sm:px-0">
            <h2 id="for-you" className="text-lg font-extrabold tracking-tight sm:text-xl">
              {t("home.forYou")}
            </h2>
            <p className="text-xs text-ink-2 sm:text-sm">{t("home.forYouNote")}</p>
          </div>
          <div className="px-2 sm:px-0">
            <EnabledGrid items={forYou} />
          </div>
        </section>
      )}
      <CmsHero store="all" />
      <RecentlyViewed />
      <EnabledRail title={t("home.liveDeals")} note={t("home.liveDealsNote")} href="/deals" items={dealItems} />
      <StoreCards />
      <CmsTiles store="all" />
      {MARKET_IDS.map((m, i) => (
        <EnabledRail
          key={m}
          title={t("home.popularIn", { store: STORES[m].name })}
          note={t("home.sourcedFrom", { source: STORES[m].source })}
          href={`/store/${m}`}
          items={popular[i]}
        />
      ))}
      <EnabledRail title={t("home.phonesAudio")} href="/search?q=&store=all&category=electronics" items={electronics} />
      <PromoStrip />
      {/* cost calculator: the "what will it really cost me?" question, answered before the shopper has to ask */}
      <Link
        href="/calculator"
        className="card mx-4 mt-8 flex items-center gap-3 p-4 transition-transform active:scale-[0.99] sm:mx-0 sm:p-5"
      >
        <span className="chip-icon h-12 w-12 shrink-0 rounded-2xl">
          <Icon name="calc" size={22} />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block font-extrabold tracking-tight">{t("calc.title")}</span>
          <span className="line-clamp-2 text-sm text-ink-2">{t("calc.sub")}</span>
        </span>
        <Icon name="right" size={18} className="shrink-0 text-accent" />
      </Link>
      <HowItWorks />
    </div>
  );
}
