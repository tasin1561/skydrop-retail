import { getCmsHome } from "@/lib/cms";
import { getFeatures, isOn } from "@/lib/features";
import type { StoreMode } from "@/lib/types";
import { BannerTiles, HeroCarousel } from "./HeroCarousel";
import { FlashSaleRail } from "./FlashSaleRail";

// Self-fetching server slots so pages only drop them in. getCmsHome is cached per store (fetch revalidate 60 s).
// Gated by Admin → Features: "banners" and "flash_sales".

export async function CmsHero({ store }: { store: StoreMode }) {
  if (!isOn(await getFeatures(), "banners")) return null;
  const { banners } = await getCmsHome(store);
  return <HeroCarousel banners={banners.filter((b) => b.placement === "hero")} />;
}

export async function CmsTiles({ store }: { store: StoreMode }) {
  if (!isOn(await getFeatures(), "banners")) return null;
  const { banners } = await getCmsHome(store);
  return <BannerTiles banners={banners.filter((b) => b.placement !== "hero")} />;
}

export async function CmsFlash({ store }: { store: StoreMode }) {
  if (!isOn(await getFeatures(), "flash_sales")) return null;
  const { flashSales } = await getCmsHome(store);
  return <FlashSaleRail sales={flashSales} />;
}
