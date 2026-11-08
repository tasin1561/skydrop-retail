// Inter (corporate UI font, mirrors the web). React Native picks a font file per weight via fontFamily, so map
// the requested fontWeight to the matching loaded family. Calm scale: 400 body, 500 UI, 600 headings; any
// heavier weight (700/800/900, "bold") renders as 600. Falls back to the system font until loaded.
import { Inter_400Regular, Inter_500Medium, Inter_600SemiBold } from "@expo-google-fonts/inter";
// Bangla mode: Noto Sans Bengali per weight (Inter has no Bengali glyphs).
import { NotoSansBengali_400Regular, NotoSansBengali_500Medium, NotoSansBengali_600SemiBold } from "@expo-google-fonts/noto-sans-bengali";
import type { TextStyle } from "react-native";

export const FONT_FILES = {
  Inter_400Regular,
  Inter_500Medium,
  Inter_600SemiBold,
  NotoSansBengali_400Regular,
  NotoSansBengali_500Medium,
  NotoSansBengali_600SemiBold,
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
  "100": "Inter_400Regular",
  "200": "Inter_400Regular",
  "300": "Inter_400Regular",
  "400": "Inter_400Regular",
  normal: "Inter_400Regular",
  "500": "Inter_500Medium",
  "600": "Inter_600SemiBold",
  "700": "Inter_600SemiBold",
  bold: "Inter_600SemiBold",
  "800": "Inter_600SemiBold",
  "900": "Inter_600SemiBold",
};

const BN_BY_WEIGHT: Record<string, string> = Object.fromEntries(
  Object.entries(BY_WEIGHT).map(([w, f]) => [w, f.replace("Inter_", "NotoSansBengali_")]),
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
