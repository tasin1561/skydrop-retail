import { View } from "react-native";
import type { Product } from "@/lib/types";
import { CompareStoresCard } from "./CompareStoresCard";
import { PriceHistoryCard } from "./PriceHistoryCard";
import { WaitlistCard } from "./WaitlistCard";

/** Shopping sections on the product screen (each one hides itself when its feature is off or has no data). */
export function ShoppingSections({ p, inStock }: { p: Product; inStock: boolean }) {
  return (
    <View style={{ gap: 12 }}>
      <WaitlistCard p={p} inStock={inStock} />
      <CompareStoresCard id={p.id} />
      <PriceHistoryCard p={p} />
    </View>
  );
}
