import Link from "next/link";
import { Icon } from "@/components/Icon";
import { ProductImage } from "@/components/ProductBits";
import { formatBdt } from "@/lib/pricing";
import type { Bundle } from "@/lib/shopping/types";

/** Admin-curated bundles that include this product. */
export function ProductBundles({ bundles }: { bundles: Bundle[] }) {
  if (!bundles.length) return null;
  return (
    <section className="card p-4 sm:p-5" aria-labelledby="sh-bundles-h">
      <h2 id="sh-bundles-h" className="flex items-center gap-3 text-lg font-extrabold tracking-tight">
        <span className="chip-icon">
          <Icon name="package" size={18} />
        </span>
        Save with a bundle
      </h2>
      <ul className="mt-3 grid gap-3 sm:grid-cols-2">
        {bundles.map((b, i) => (
          <li key={b.id} className="rise" style={{ ["--i" as string]: i }}>
            <BundleCard b={b} />
          </li>
        ))}
      </ul>
    </section>
  );
}

export function BundleCard({ b }: { b: Bundle }) {
  return (
    <Link href={`/bundles/${b.slug}`} className="group flex h-full flex-col gap-3 rounded-2xl bg-surface-2/70 p-3 transition-transform duration-300 hover:-translate-y-0.5">
      {b.items && b.items.length > 0 && (
        <div className="flex -space-x-3">
          {b.items.slice(0, 4).map(({ product }) => (
            <span key={product.id} className="h-14 w-14 overflow-hidden rounded-xl bg-white p-1 shadow-[var(--shadow-1)] ring-2 ring-[var(--surface)]">
              <ProductImage p={product} className="h-full w-full" />
            </span>
          ))}
        </div>
      )}
      <div className="min-w-0">
        <p className="font-bold text-ink">{b.title}</p>
        <p className="text-xs text-ink-2">{b.count} items · extra {b.discountPct}% off together</p>
      </div>
      <div className="mt-auto flex items-end justify-between gap-2">
        <p>
          <span className="text-lg font-extrabold tabular-nums">{formatBdt(b.totalBdt)}</span>{" "}
          <s className="text-xs text-ink-3 tabular-nums">{formatBdt(b.regularBdt)}</s>
        </p>
        <span className="inline-flex items-center gap-1 rounded-full bg-good px-2 py-1 text-[11px] font-extrabold text-white">
          Save {formatBdt(b.discountBdt)}
        </span>
      </div>
    </Link>
  );
}
