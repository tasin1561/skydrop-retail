import { View, type StyleProp, type ViewStyle } from "react-native";
import { Image } from "expo-image";
import type { StoreMode } from "@/lib/types";
import { useAccent, useTheme, withAlpha } from "@/theme";
import { CategoryIcon } from "./CategoryIcon";

type Thumb = { icon: string; image?: string | null; images?: string[] | null };

/** Amazon photos are product shots on white: shown whole. Myntra/Flipkart photos are shot on models: fill the tile. */
const isAmazon = (src: string) => /(^|\.)media-amazon\.com\/|images-amazon\.com\//.test(src);

/**
 * A category's picture (same rules as the website's CatThumb): a real product photo (the admin's image or the
 * category's best product, picked by the API), else the category's own icon (saree, heels, lipstick...) on a soft
 * tint of the store colour.
 */
export function CatThumb({ node, size = 56, radius, mode = "all", style }: { node: Thumb; size?: number; radius?: number; mode?: StoreMode; style?: StyleProp<ViewStyle> }) {
  const { c } = useTheme();
  const a = useAccent(mode);
  const src = node.images?.[0] ?? node.image ?? null;
  const r = radius ?? Math.round(size * 0.28);
  if (src) {
    const contain = isAmazon(src);
    return (
      <View style={[{ width: size, height: size, borderRadius: r, overflow: "hidden", backgroundColor: "#fff", borderWidth: 1, borderColor: c.line }, style]}>
        <Image
          source={{ uri: src }}
          contentFit={contain ? "contain" : "cover"}
          contentPosition="top"
          transition={180}
          recyclingKey={src}
          style={{ flex: 1, margin: contain ? size * 0.09 : 0 }}
          accessibilityIgnoresInvertColors
        />
      </View>
    );
  }
  return (
    <View
      style={[
        { width: size, height: size, borderRadius: r, alignItems: "center", justifyContent: "center", backgroundColor: a.soft, borderWidth: 1, borderColor: withAlpha(a.ink, 0.12) },
        style,
      ]}
    >
      <CategoryIcon name={node.icon} size={Math.round(size * 0.46)} color={a.ink} stroke={1.7} />
    </View>
  );
}
