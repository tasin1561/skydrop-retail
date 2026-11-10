import Svg, { Path } from "react-native-svg";
import { CATEGORY_ICONS } from "@/lib/categoryIcons";
import { Icon } from "./Icon";

/**
 * A category's own icon (saree, heels, lipstick...), drawn from the same paths as the website (lib/categoryIcons.ts),
 * so both apps show the same picture. Unknown keys fall back to the Feather-based Icon.
 */
export function CategoryIcon({ name, size = 22, color, stroke = 1.8 }: { name: string; size?: number; color: string; stroke?: number }) {
  const d = CATEGORY_ICONS[name];
  if (!d) return <Icon name={name} size={size} color={color} />;
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round">
      <Path d={d} />
    </Svg>
  );
}
