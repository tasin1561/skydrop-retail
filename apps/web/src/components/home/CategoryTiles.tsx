import Link from "next/link";
import { searchProducts } from "@/lib/data";
import { getT } from "@/i18n/server";
import type { MsgKey } from "@/i18n/core";
import { Icon } from "../Icon";
import { FxImg } from "../motion/FxImg";

/**
 * Category-first home: the shopper (mostly women, mostly on a phone) sees photo tiles for what she shops most before
 * anything else. Paths are nodes of the unified category tree; each tile's photo is the top-rated product of that
 * category from our own database (an empty-query search never triggers a live store fetch).
 */
export const HOME_TILES: { path: string; label: MsgKey; icon: string }[] = [
  { path: "fashion/women/clothing/kurtas-kurtis", label: "tile.kurtas", icon: "dress" },
  { path: "fashion/women/clothing/sarees", label: "tile.sarees", icon: "dress" },
  { path: "fashion/women/clothing/dresses", label: "tile.dresses", icon: "dress" },
  { path: "fashion/women/clothing/tops-t-shirts", label: "tile.tops", icon: "dress" },
  { path: "fashion/women/bags", label: "tile.bags", icon: "bag" },
  { path: "fashion/jewellery", label: "tile.jewellery", icon: "gem" },
  { path: "beauty-personal-care/makeup", label: "tile.makeup", icon: "beauty" },
  { path: "beauty-personal-care/skin-care", label: "tile.skincare", icon: "smile" },
  { path: "fashion/women/footwear", label: "tile.footwear", icon: "shoe" },
  { path: "fashion/kids/girls-clothing", label: "tile.kids", icon: "baby" },
  { path: "fashion/watches", label: "tile.watches", icon: "watch" },
  { path: "home-kitchen/home-decor", label: "tile.decor", icon: "frame" },
];

async function tileImage(path: string): Promise<string | null> {
  try {
    const r = await searchProducts({ q: "", category: path, sort: "rating" });
    return r.items.find((p) => p.image)?.image ?? null;
  } catch {
    return null;
  }
}

export async function CategoryTiles() {
  const [{ t }, images] = await Promise.all([getT(), Promise.all(HOME_TILES.map((c) => tileImage(c.path)))]);
  return (
    <section aria-labelledby="shop-cats" className="px-4 pt-4 sm:px-0">
      <div className="mb-3 flex items-end justify-between gap-3">
        <h2 id="shop-cats" className="text-lg font-extrabold tracking-tight sm:text-xl">
          {t("home.shopByCategory")}
        </h2>
        <Link href="/categories" className="-my-3 -mr-2 inline-flex min-h-11 items-center gap-1 px-2 text-sm font-semibold text-accent">
          {t("card.seeAll")} <Icon name="right" size={16} />
        </Link>
      </div>
      <ul className="grid grid-cols-4 gap-x-2.5 gap-y-3.5 sm:grid-cols-6 lg:grid-cols-12">
        {HOME_TILES.map((c, i) => (
          <li key={c.path}>
            <Link href={`/c/${c.path}`} className="group flex flex-col items-center gap-1.5 text-center" prefetch={false}>
              <span className="relative block aspect-square w-full overflow-hidden rounded-2xl bg-white shadow-[var(--shadow-1)] ring-1 ring-line transition duration-200 group-hover:-translate-y-0.5 group-hover:ring-accent group-active:scale-[0.97]">
                {images[i] ? (
                  <FxImg src={images[i]!} alt="" loading={i < 8 ? "eager" : "lazy"} decoding="async" className="h-full w-full object-cover object-top" />
                ) : (
                  <span className="grid h-full w-full place-items-center bg-surface-2 text-accent">
                    <Icon name={c.icon} size={26} stroke={1.6} />
                  </span>
                )}
              </span>
              <span className="line-clamp-2 text-[12px] font-semibold leading-tight text-ink">{t(c.label)}</span>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
