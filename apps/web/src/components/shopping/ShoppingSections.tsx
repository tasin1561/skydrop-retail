import { SafeSection } from "@/components/resilience/SafeSection";
import { getFeatures, isOn } from "@/lib/features";
import { bundlesForProduct, getBoughtTogether, getCompare } from "@/lib/shopping/server";
import type { Product } from "@/lib/types";
import { isUnavailableStock } from "../product/DeliveryCard";
import { BoughtTogether } from "./BoughtTogether";
import { CompareStores } from "./CompareStores";
import { PreorderBox } from "./PreorderBox";
import { PriceHistoryChart } from "./PriceHistoryChart";
import { ProductBundles } from "./ProductBundles";
import { getT } from "@/i18n/server";

const safe = <T,>(p: Promise<T>, fallback: T) => p.catch(() => fallback);

/** Info column, under the buy box: compare stores and (out of stock) restock waitlist / pre-order. */
export async function ShoppingBuyExtras({ p }: { p: Product }) {
  const [f, { t }] = await Promise.all([getFeatures(), getT()]);
  const compare = isOn(f, "compare_stores") ? await safe(getCompare(p), null) : null;
  const inStock = !isUnavailableStock(p.stock);
  const hp = { id: p.id, priceInr: p.priceInr, category: p.category, effectivePriceInr: p.effectivePriceInr };
  return (
    <>
      {compare && (
        <SafeSection label={t("sec.compare")} quiet>
          <CompareStores data={compare} />
        </SafeSection>
      )}
      {!inStock && !p.demo && (isOn(f, "preorders") || isOn(f, "waitlist")) && (
        <SafeSection label={t("sec.preorder")} quiet>
          <PreorderBox p={hp} inStock={inStock} />
        </SafeSection>
      )}
    </>
  );
}

/** Details area: price history, frequently bought together and curated bundles. */
export async function ShoppingSections({ p }: { p: Product }) {
  const { t } = await getT();
  const f = await getFeatures();
  const [fbt, bundles] = await Promise.all([
    isOn(f, "bundles") ? safe(getBoughtTogether(p, 3), null) : null,
    isOn(f, "bundles") ? safe(bundlesForProduct(p.id), []) : [],
  ]);
  const hp = { id: p.id, priceInr: p.priceInr, category: p.category, effectivePriceInr: p.effectivePriceInr };
  const canBuy = !isUnavailableStock(p.stock);
  return (
    <div className="mt-6 grid gap-4 px-4 sm:px-0 lg:grid-cols-2 lg:gap-6">
      {isOn(f, "price_history") && (
        <SafeSection label={t("sec.history")}>
          <PriceHistoryChart p={hp} />
        </SafeSection>
      )}
      {((fbt?.items.length ?? 0) > 0 || bundles.length > 0) && (
        <div className="flex flex-col gap-4">
          {fbt && fbt.items.length > 0 && (
            <SafeSection label={t("sec.fbt")} quiet>
              <BoughtTogether data={fbt} canBuyAnchor={canBuy && !p.demo} />
            </SafeSection>
          )}
          {bundles.length > 0 && (
            <SafeSection label={t("sec.bundles")} quiet>
              <ProductBundles bundles={bundles} />
            </SafeSection>
          )}
        </div>
      )}
    </div>
  );
}
