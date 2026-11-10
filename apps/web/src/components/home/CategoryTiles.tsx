import Link from "next/link";
import { getCategoryTree, searchProducts } from "@/lib/data";
import type { CatNode } from "@/lib/categories";
import { getT } from "@/i18n/server";
import type { MsgKey } from "@/i18n/core";
import { Icon } from "../Icon";
import { CatThumb } from "../categories/CatThumb";
import "../categories/categories.css";

/**
 * Category-first home: the shopper (mostly women, mostly on a phone) sees photo tiles for what she shops most before
 * anything else. Paths are nodes of the unified category tree; each tile's photo is the category's cover from the tree
 * (admin image, else its best product: the API's CategoryCovers). Without one (demo mode, a fresh database), the
 * top-rated product of the category from our own database (an empty-query search never triggers a live store fetch).
 */
export const HOME_TILES: { path: string; label: MsgKey; icon: string }[] = [
  { path: "fashion/women/clothing/kurtas-kurtis", label: "tile.kurtas", icon: "kurta" },
  { path: "fashion/women/clothing/sarees", label: "tile.sarees", icon: "saree" },
  { path: "fashion/women/clothing/dresses", label: "tile.dresses", icon: "dress" },
  { path: "fashion/women/clothing/tops-t-shirts", label: "tile.tops", icon: "top" },
  { path: "fashion/women/bags", label: "tile.bags", icon: "handbag" },
  { path: "fashion/jewellery", label: "tile.jewellery", icon: "necklace" },
  { path: "beauty-personal-care/makeup", label: "tile.makeup", icon: "lipstick" },
  { path: "beauty-personal-care/skin-care", label: "tile.skincare", icon: "jar" },
  { path: "fashion/women/footwear", label: "tile.footwear", icon: "heels" },
  { path: "fashion/kids/girls-clothing", label: "tile.kids", icon: "romper" },
  { path: "fashion/watches", label: "tile.watches", icon: "watch" },
  { path: "home-kitchen/home-decor", label: "tile.decor", icon: "vase" },
];

function findNode(nodes: CatNode[], path: string): CatNode | null {
  for (const n of nodes) {
    if (n.path === path) return n;
    if (path.startsWith(n.path + "/")) return findNode(n.children, path);
  }
  return null;
}

async function tileImage(tree: CatNode[], path: string): Promise<string | null> {
  const n = findNode(tree, path);
  const cover = n?.images?.[0] ?? n?.image;
  if (cover) return cover;
  try {
    const r = await searchProducts({ q: "", category: path, sort: "rating" });
    return r.items.find((p) => p.image)?.image ?? null;
  } catch {
    return null;
  }
}

export async function CategoryTiles() {
  const tree = await getCategoryTree("all").catch(() => [] as CatNode[]);
  const [{ t }, images] = await Promise.all([getT(), Promise.all(HOME_TILES.map((c) => tileImage(tree, c.path)))]);
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
            <Link href={`/c/${c.path}`} className="flex flex-col items-center gap-1.5 text-center" prefetch={false}>
              <CatThumb
                node={{ icon: c.icon, image: images[i] }}
                className="aspect-square w-full rounded-2xl shadow-[var(--shadow-1)]"
                iconSize={28}
                eager={i < 8}
              />
              <span className="line-clamp-2 text-[12px] font-semibold leading-tight text-ink">{t(c.label)}</span>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}
