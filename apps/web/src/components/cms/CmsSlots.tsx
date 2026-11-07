import { getCmsHome } from "@/lib/cms";
import type { StoreMode } from "@/lib/types";
import { BannerTiles, HeroCarousel } from "./HeroCarousel";
import { FlashSaleRail } from "./FlashSaleRail";

// Self-fetching server slots so pages only drop them in. getCmsHome is cached per store (fetch revalidate 60 s).

export async function CmsHero({ store }: { store: StoreMode }) {
  const { banners } = await getCmsHome(store);
  return <HeroCarousel banners={banners.filter((b) => b.placement === "hero")} />;
}

export async function CmsTiles({ store }: { store: StoreMode }) {
  const { banners } = await getCmsHome(store);
  return <BannerTiles banners={banners.filter((b) => b.placement !== "hero")} />;
}

export async function CmsFlash({ store }: { store: StoreMode }) {
  const { flashSales } = await getCmsHome(store);
  return <FlashSaleRail sales={flashSales} />;
}
