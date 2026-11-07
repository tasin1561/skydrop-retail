import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getProduct, related } from "@/lib/data";
import { STORES } from "@/lib/markets";
import { formatBdt, priceBdt } from "@/lib/pricing";
import { Rail } from "@/components/ProductBits";
import { ProductDetail } from "@/components/product/ProductDetail";
import { isUnavailableStock } from "@/components/product/DeliveryCard";
import { TrackView } from "@/components/tracking/TrackView";

type Props = { params: Promise<{ id: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { id } = await params;
  const p = await getProduct(decodeURIComponent(id));
  if (!p) return { title: "Product not found" };
  const source = STORES[p.market].source;
  const title = p.title.length > 70 ? p.title.slice(0, 67).trimEnd() + "..." : p.title;
  const description = `${p.brand ? p.brand + " · " : ""}${formatBdt(priceBdt(p))} delivered to Bangladesh. Bought for you from ${source}, India and delivered by Skydrop.`;
  const path = `/p/${encodeURIComponent(p.id)}`;
  // og:image comes from ./opengraph-image.tsx (product card with BDT price); X/Twitter falls back to it.
  return {
    metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL ?? "https://retail.skydrop.online"),
    title,
    description,
    alternates: { canonical: path },
    openGraph: { type: "website", siteName: "Skydrop Retail", url: path, title: p.title, description, locale: "en_BD" },
    twitter: { card: "summary_large_image", title, description },
  };
}

export default async function ProductPage({ params }: Props) {
  const { id } = await params;
  const p = await getProduct(decodeURIComponent(id));
  if (!p) notFound();

  // Which variant ids we can open as their own page, and which are known out of stock.
  const knownIds: string[] = [];
  const unavailableIds: string[] = [];
  const ids = Array.from(new Set((p.variants ?? []).map((v) => v.id).filter((v): v is string => !!v && v !== p.id)));
  const others = await Promise.all(ids.slice(0, 60).map((vid) => getProduct(vid)));
  for (const other of [p, ...others]) {
    if (!other || !(p.variants ?? []).some((v) => v.id === other.id)) continue;
    knownIds.push(other.id);
    if (isUnavailableStock(other.stock)) unavailableIds.push(other.id);
  }

  const more = await related(p);
  const store = STORES[p.market];

  return (
    <ProductDetail p={p} knownIds={knownIds} unavailableIds={unavailableIds}>
      <TrackView product={{ id: p.id, title: p.title, brand: p.brand, category: p.category, priceBdt: priceBdt(p) }} />
      <div className="mt-4">
        <Rail
          title="More like this"
          note={`Popular on ${store.source}`}
          href={`/search?q=&store=${p.market}&category=${p.category}`}
          items={more}
        />
      </div>
    </ProductDetail>
  );
}
