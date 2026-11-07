import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Icon } from "@/components/Icon";
import { BundleCard } from "@/components/shopping/ProductBundles";
import { getFeatures, isOn } from "@/lib/features";
import { getBundles } from "@/lib/shopping/server";
import { getT } from "@/i18n/server";

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return { title: t("bundles.title"), description: t("bundles.metaDesc") };
}

export default async function BundlesPage() {
  if (!isOn(await getFeatures(), "bundles")) notFound();
  const [bundles, { t }] = await Promise.all([getBundles().catch(() => []), getT()]);
  return (
    <div className="mx-auto max-w-6xl px-4 pb-24 pt-4 sm:px-6 md:pb-10">
      <h1 className="flex items-center gap-3 text-2xl font-extrabold tracking-tight">
        <span className="chip-icon">
          <Icon name="package" size={18} />
        </span>
{t("bundles.title")}
</h1>
      <p className="mt-1 text-sm text-ink-2">{t("bundles.sub")}</p>
      {bundles.length ? (
        <ul className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {bundles.map((b, i) => (
            <li key={b.id} className="rise card p-1" style={{ ["--i" as string]: i }}>
              <BundleCard b={b} />
            </li>
          ))}
        </ul>
      ) : (
        <p className="card mt-5 p-8 text-center text-sm text-ink-2">{t("bundles.none")}</p>
      )}
    </div>
  );
}
