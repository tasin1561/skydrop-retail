import Feather from "@expo/vector-icons/Feather";
import type { ComponentProps } from "react";

type FeatherName = ComponentProps<typeof Feather>["name"];

// The web app's icon names, mapped onto Feather glyphs (generic line icons, no brand marks).
const MAP: Record<string, FeatherName> = {
  globe: "globe",
  bolt: "zap",
  spark: "star",
  basket: "shopping-bag",
  phone: "smartphone",
  shirt: "scissors",
  shoe: "navigation",
  beauty: "droplet",
  grain: "sun",
  home: "home",
  juice: "coffee",
  watch: "watch",
  audio: "headphones",
  bag: "shopping-bag",
  oil: "droplet",
  tea: "coffee",
  nut: "circle",
  spice: "thermometer",
  noodle: "wind",
  choco: "gift",
  receipt: "file-text",
  shield: "shield",
  package: "package",
  plane: "send",
  pin: "map-pin",
  truck: "truck",
  check: "check",
  x: "x",
  search: "search",
  cart: "shopping-cart",
  tag: "tag",
  refresh: "refresh-cw",
  wallet: "credit-card",
  card: "credit-card",
  cash: "dollar-sign",
  user: "user",
  heart: "heart",
  info: "info",
  grid: "grid",
  list: "list",
};

export type IconName = FeatherName | keyof typeof MAP;

export function Icon({ name, size = 20, color }: { name: IconName | string; size?: number; color?: string }) {
  const glyph = (MAP[name as string] ?? (name as FeatherName)) as FeatherName;
  return <Feather name={glyph} size={size} color={color} />;
}
