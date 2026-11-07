import { View } from "react-native";
import type { Product } from "@/lib/types";
import { CompareStoresCard } from "./CompareStoresCard";
import { PriceHistoryCard } from "./PriceHistoryCard";
import { WaitlistCard } from "./WaitlistCard";
import { GroupBuyCard, ProductBundlesSection } from "./Bundles";

/** Shopping sections on the product screen (each one hides itself when its feature is off or has no data). */
export function ShoppingSections({ p, inStock, addLine }: { p: Product; inStock: boolean; addLine: () => void }) {
  return (
    <View style={{ gap: 12 }}>
      <WaitlistCard p={p} inStock={inStock} />
      <CompareStoresCard id={p.id} />
      <GroupBuyCard p={p} canBuy={inStock} line={addLine} />
      <PriceHistoryCard p={p} />
      <ProductBundlesSection p={p} canBuy={inStock} />
    </View>
  );
}
