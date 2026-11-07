import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import "@/components/shopping/shopping.css";
import { Icon } from "@/components/Icon";
import { OriginTag, ProductImage } from "@/components/ProductBits";
import { AddBundleButton } from "@/components/shopping/AddBundleButton";
import { getFeatures, isOn } from "@/lib/features";
import { formatBdt, priceBdt } from "@/lib/pricing";
import { getBundle } from "@/lib/shopping/server";

type Props = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const b = await getBundle((await params).slug).catch(() => null);
  return b ? { title: `${b.title} bundle`, description: `${b.count} items for ${formatBdt(b.totalBdt)}, save ${formatBdt(b.discountBdt)}.` } : { title: "Bundle not found" };
}

export default async function BundlePage({ params }: Props) {
  const { slug } = await params;
  if (!isOn(await getFeatures(), "bundles")) notFound();
  const b = await getBundle(slug).catch(() => null);
  if (!b) notFound();
  const items = b.items ?? [];

  return (
    <div className="mx-auto max-w-4xl px-4 pb-24 pt-4 sm:px-6 md:pb-10">
      <nav className="mb-3 text-xs text-ink-2">
        <Link href="/bundles" className="inline-flex min-h-8 items-center gap-1 hover:text-accent">
          <Icon name="left" size={14} /> All bundles
        </Link>
      </nav>
      <header className="card overflow-hidden">
        <div className="bg-[linear-gradient(135deg,var(--hero-a),var(--hero-b))] px-5 py-6 text-white sm:px-8 sm:py-8">
          <p className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-bold">
            <Icon name="package" size={14} /> Bundle · {b.count} items
          </p>
          <h1 className="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">{b.title}</h1>
          {b.description && <p className="mt-1 max-w-xl text-sm text-white/85">{b.description}</p>}
        </div>
        <div className="flex flex-wrap items-center justify-between gap-4 p-5 sm:px-8">
          <div>
            <p className="text-3xl font-extrabold tracking-tight tabular-nums">{formatBdt(b.totalBdt)}</p>
            <p className="text-sm text-ink-2">
              <s className="tabular-nums">{formatBdt(b.regularBdt)}</s> ·{" "}
              <span className="font-bold text-good">
                save {formatBdt(b.discountBdt)} ({b.discountPct}% extra)
              </span>
            </p>
          </div>
          <AddBundleButton b={b} />
        </div>
      </header>

      <ol className="mt-5 flex flex-col gap-3">
        {items.map(({ product: p, qty }, i) => (
          <li key={p.id} className="rise" style={{ ["--i" as string]: i }}>
            <Link href={`/p/${encodeURIComponent(p.id)}`} className="card flex items-center gap-4 p-3 transition-transform duration-300 hover:-translate-y-0.5 sm:p-4">
              <span className="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-white p-1.5">
                <ProductImage p={p} className="h-full w-full" />
              </span>
              <span className="min-w-0 flex-1">
                <OriginTag market={p.market} compact />
                <span className="mt-1 line-clamp-2 block text-sm font-medium text-ink">{p.title}</span>
                {qty > 1 && <span className="text-xs text-ink-2">Qty {qty}</span>}
              </span>
              <span className="shrink-0 text-right font-bold tabular-nums">{formatBdt(priceBdt(p) * qty)}</span>
            </Link>
          </li>
        ))}
      </ol>
      <p className="mt-4 flex items-start gap-2 text-xs text-ink-2">
        <Icon name="info" size={15} className="mt-px shrink-0" /> The bundle saving applies automatically in your cart while every item of the bundle is in it.
      </p>
    </div>
  );
}
