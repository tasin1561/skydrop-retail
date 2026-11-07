import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getProduct, related } from "@/lib/catalog";
import { STORES } from "@/lib/markets";
import { formatBdt, priceBdt } from "@/lib/pricing";
import { Rail } from "@/components/ProductBits";
import { ProductDetail } from "@/components/product/ProductDetail";
import { isUnavailableStock } from "@/components/product/DeliveryCard";

type Props = { params: Promise<{ id: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { id } = await params;
  const p = getProduct(decodeURIComponent(id));
  if (!p) return { title: "Product not found" };
  const source = STORES[p.market].source;
  return {
    title: p.title.length > 70 ? p.title.slice(0, 67).trimEnd() + "..." : p.title,
    description: `${p.brand ? p.brand + " · " : ""}${formatBdt(priceBdt(p))} delivered to Bangladesh. Bought for you from ${source}, India and delivered by Skydrop.`,
    openGraph: p.image ? { images: [{ url: p.image }] } : undefined,
  };
}

export default async function ProductPage({ params }: Props) {
  const { id } = await params;
  const p = getProduct(decodeURIComponent(id));
  if (!p) notFound();

  // Which variant ids we can open as their own page, and which are known out of stock.
  const knownIds: string[] = [];
  const unavailableIds: string[] = [];
  for (const v of p.variants ?? []) {
    if (!v.id) continue;
    const other = v.id === p.id ? p : getProduct(v.id);
    if (!other) continue;
    knownIds.push(v.id);
    if (isUnavailableStock(other.stock)) unavailableIds.push(v.id);
  }

  const more = related(p);
  const store = STORES[p.market];

  return (
    <ProductDetail p={p} knownIds={knownIds} unavailableIds={unavailableIds}>
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
