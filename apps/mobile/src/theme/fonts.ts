// Plus Jakarta Sans (brand font). React Native picks a font file per weight via fontFamily,
// so map the requested fontWeight to the matching loaded family. Falls back to the system font until loaded.
import {
  PlusJakartaSans_400Regular,
  PlusJakartaSans_500Medium,
  PlusJakartaSans_600SemiBold,
  PlusJakartaSans_700Bold,
  PlusJakartaSans_800ExtraBold,
} from "@expo-google-fonts/plus-jakarta-sans";
// Bangla mode: Noto Sans Bengali per weight (Plus Jakarta Sans has no Bengali glyphs).
import {
  NotoSansBengali_400Regular,
  NotoSansBengali_500Medium,
  NotoSansBengali_600SemiBold,
  NotoSansBengali_700Bold,
  NotoSansBengali_800ExtraBold,
} from "@expo-google-fonts/noto-sans-bengali";
import type { TextStyle } from "react-native";

export const FONT_FILES = {
  PlusJakartaSans_400Regular,
  PlusJakartaSans_500Medium,
  PlusJakartaSans_600SemiBold,
  PlusJakartaSans_700Bold,
  PlusJakartaSans_800ExtraBold,
  NotoSansBengali_400Regular,
  NotoSansBengali_500Medium,
  NotoSansBengali_600SemiBold,
  NotoSansBengali_700Bold,
  NotoSansBengali_800ExtraBold,
};

let fontLang: "en" | "bn" = "en";
/** Set by the language provider: Bangla mode uses the Noto Sans Bengali families. */
export function setFontLang(l: "en" | "bn") {
  fontLang = l;
}

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

const BN_BY_WEIGHT: Record<string, string> = Object.fromEntries(
  Object.entries(BY_WEIGHT).map(([w, f]) => [w, f.replace("PlusJakartaSans_", "NotoSansBengali_")]),
);

/** The font for a label shown in a fixed language (the "বাংলা" option of the switch, in either mode). */
export function fontFor(l: "en" | "bn", weight: TextStyle["fontWeight"] | undefined): TextStyle {
  if (!loaded) return {};
  const map = l === "bn" ? BN_BY_WEIGHT : BY_WEIGHT;
  return { fontFamily: map[String(weight ?? "400")] ?? map["400"], fontWeight: "normal" };
}

/** Style patch that applies the brand font for the given weight (and clears fontWeight so Android doesn't fake-bold). */
export function brandFont(weight: TextStyle["fontWeight"] | undefined): TextStyle {
  if (!loaded) return {};
  const map = fontLang === "bn" ? BN_BY_WEIGHT : BY_WEIGHT;
  return { fontFamily: map[String(weight ?? "400")] ?? map["400"], fontWeight: "normal" };
}
