import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { productsByIds } from "@/lib/data";
import { getFeatures, isOn } from "@/lib/features";
import { demoWishlistIds, type SharedWishlist } from "@/lib/growth";
import type { Product } from "@/lib/types";
import { ProductCard } from "@/components/ProductBits";
import { Icon } from "@/components/Icon";

type Props = { params: Promise<{ code: string }> };

const API = process.env.API_URL?.replace(/\/$/, "");

/** Public wishlist: /w/{code} from POST /wishlist/share, or /w/d-<ids> links made in demo mode / signed out. */
async function load(code: string): Promise<{ owner: string | null; items: Product[] } | null> {
  if (code.startsWith("d-")) {
    const ids = demoWishlistIds(code);
    return ids.length ? { owner: null, items: await productsByIds(ids) } : null;
  }
  if (!API || !/^[a-z0-9]{4,16}$/i.test(code)) return null;
  try {
    const res = await fetch(`${API}/wishlists/${encodeURIComponent(code)}`, { headers: { Accept: "application/json" }, next: { revalidate: 60 } });
    if (!res.ok) return null;
    const d = (await res.json()) as SharedWishlist;
    return { owner: d.owner?.name ?? null, items: d.items ?? [] };
  } catch {
    return null;
  }
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { code } = await params;
  const w = await load(code);
  if (!w) return { title: "Wishlist not found", robots: { index: false } };
  const title = w.owner ? `${w.owner}'s wishlist` : "A shared wishlist";
  const description = `${w.items.length} product${w.items.length === 1 ? "" : "s"} from Indian stores, priced in taka and delivered to Bangladesh by Skydrop.`;
  const image = w.items.find((p) => p.image)?.image;
  return {
    title,
    description,
    robots: { index: false },
    openGraph: { title, description, type: "website", images: image ? [{ url: image }] : undefined },
    twitter: { card: image ? "summary_large_image" : "summary", title, description, images: image ? [image] : undefined },
  };
}

export default async function SharedWishlistPage({ params }: Props) {
  const { code } = await params;
  if (!isOn(await getFeatures(), "share")) notFound();
  const w = await load(code);
  if (!w) notFound();
  const title = w.owner ? `${w.owner}'s wishlist` : "A shared wishlist";

  return (
    <div className="mx-auto max-w-7xl px-4 pb-24 pt-6 md:pb-12">
      <section
        className="rise relative mb-6 overflow-hidden rounded-[20px] p-5 text-white shadow-[var(--glow)] sm:p-7"
        style={{ background: "linear-gradient(135deg, var(--hero-a), var(--hero-b))" }}
      >
        <div aria-hidden className="absolute -right-10 -top-12 h-40 w-40 rounded-full bg-white/10" />
        <div className="relative flex items-center gap-4">
          <span className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-white/20 backdrop-blur-sm">
            <Icon name="heart" size={22} />
          </span>
          <div className="min-w-0">
            <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">{title}</h1>
            <p className="text-sm text-white/85">
              {String(w.items.length).padStart(2, "0")} saved item{w.items.length === 1 ? "" : "s"} · prices in taka, delivered by Skydrop
            </p>
          </div>
        </div>
      </section>

      {w.items.length === 0 ? (
        <div className="card mx-auto flex max-w-md flex-col items-center p-8 text-center">
          <p className="font-bold">This wishlist is empty right now</p>
          <p className="mt-1 text-sm text-ink-2">The items may have been bought already.</p>
          <Link href="/" className="btn btn-primary mt-4">
            Browse the store <Icon name="arrow" size={18} className="arrow" />
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
          {w.items.map((p, i) => (
            <ProductCard key={p.id} p={p} i={i} />
          ))}
        </div>
      )}

      <p className="mt-6 flex items-center gap-2 text-xs text-ink-2">
        <Icon name="shield" size={14} className="text-good" /> Prices are checked live with the Indian store before you pay.
      </p>
    </div>
  );
}
