// Plus Jakarta Sans (Midnight Luxe brand font). React Native picks a font file per weight via fontFamily,
// so map the requested fontWeight to the matching loaded family. Falls back to the system font until loaded.
import {
  PlusJakartaSans_400Regular,
  PlusJakartaSans_500Medium,
  PlusJakartaSans_600SemiBold,
  PlusJakartaSans_700Bold,
  PlusJakartaSans_800ExtraBold,
} from "@expo-google-fonts/plus-jakarta-sans";
import type { TextStyle } from "react-native";

export const FONT_FILES = {
  PlusJakartaSans_400Regular,
  PlusJakartaSans_500Medium,
  PlusJakartaSans_600SemiBold,
  PlusJakartaSans_700Bold,
  PlusJakartaSans_800ExtraBold,
};

let loaded = false;
export function setFontsLoaded(v: boolean) {
  loaded = v;
}

const BY_WEIGHT: Record<string, string> = {
  "100": "PlusJakartaSans_400Regular",
  "200": "PlusJakartaSans_400Regular",
  "300": "PlusJakartaSans_400Regular",
  "400": "PlusJakartaSans_400Regular",
  normal: "PlusJakartaSans_400Regular",
  "500": "PlusJakartaSans_500Medium",
  "600": "PlusJakartaSans_600SemiBold",
  "700": "PlusJakartaSans_700Bold",
  bold: "PlusJakartaSans_700Bold",
  "800": "PlusJakartaSans_800ExtraBold",
  "900": "PlusJakartaSans_800ExtraBold",
};

/** Style patch that applies the brand font for the given weight (and clears fontWeight so Android doesn't fake-bold). */
export function brandFont(weight: TextStyle["fontWeight"] | undefined): TextStyle {
  if (!loaded) return {};
  return { fontFamily: BY_WEIGHT[String(weight ?? "400")] ?? BY_WEIGHT["400"], fontWeight: "normal" };
}
