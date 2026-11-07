import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Icon } from "@/components/Icon";
import { BundleCard } from "@/components/shopping/ProductBundles";
import { getFeatures, isOn } from "@/lib/features";
import { getBundles } from "@/lib/shopping/server";

export const metadata: Metadata = { title: "Bundles", description: "Curated sets from Indian stores with an extra discount when you buy them together." };

export default async function BundlesPage() {
  if (!isOn(await getFeatures(), "bundles")) notFound();
  const bundles = await getBundles().catch(() => []);
  return (
    <div className="mx-auto max-w-6xl px-4 pb-24 pt-4 sm:px-6 md:pb-10">
      <h1 className="flex items-center gap-3 text-2xl font-extrabold tracking-tight">
        <span className="chip-icon">
          <Icon name="package" size={18} />
        </span>
        Bundles
      </h1>
      <p className="mt-1 text-sm text-ink-2">Buy these together and get an extra discount, applied in your cart.</p>
      {bundles.length ? (
        <ul className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {bundles.map((b, i) => (
            <li key={b.id} className="rise card p-1" style={{ ["--i" as string]: i }}>
              <BundleCard b={b} />
            </li>
          ))}
        </ul>
      ) : (
        <p className="card mt-5 p-8 text-center text-sm text-ink-2">No bundles right now. Check back soon.</p>
      )}
    </div>
  );
}
