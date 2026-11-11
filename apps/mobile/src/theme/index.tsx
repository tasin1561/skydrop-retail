import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { Platform, useColorScheme } from "react-native";
import AsyncStorage from "@react-native-async-storage/async-storage";
import * as SystemUI from "expo-system-ui";
import type { StoreMode } from "@/lib/types";

// Brand tokens mirror apps/web/src/app/globals.css ("Lagoon").
// BRAND is the primary interactive colour (deep teal); BRAND_INK is the near-black brand ink;
// CORAL is the signal colour (deals, parcel, highlights). One accent for every store.
export const BRAND = "#0f766e";
export const BRAND_2 = "#5eead4";
export const BRAND_DARK = "#0e8a7f";
export const BRAND_INK = "#111114";
export const CORAL = "#e8552f";

type Accent = { accent: string; accent2: string; accentDark: string; heroA: string; heroB: string; heroADark: string };

const LAGOON: Accent = { accent: BRAND, accent2: BRAND_2, accentDark: BRAND_DARK, heroA: "#0b2e2c", heroB: BRAND, heroADark: "#0a2321" };

/** Same accent in every store mode: stores are told apart by their name, not by a colour change. */
export const ACCENTS: Record<StoreMode, Accent> = { all: LAGOON, amazon: LAGOON, flipkart: LAGOON, myntra: LAGOON };

/**
 * Store-name text tokens (StoreName): text-safe shades of each store's own accent. Both gradient stops pass
 * WCAG AA (4.5:1) on surface, bg, surface2 and the pill tint; `fill` is the selected fill (white text >= 5.3:1).
 * Mirrors the .sn tokens in apps/web/src/components/store/storename.css. No third-party colours.
 */
export type StoreTextTokens = { a: string; b: string; ring: string };
export const STORE_TEXT: Record<StoreMode, { light: StoreTextTokens; dark: StoreTextTokens; fill: [string, string] }> = {
  all: { light: { a: BRAND, b: BRAND, ring: BRAND }, dark: { a: BRAND_2, b: BRAND_2, ring: BRAND_DARK }, fill: [BRAND, BRAND] },
  // Store names in each provider's brand colours (owner's decision): Amazon ink + orange, Flipkart blue + yellow, Myntra pink + orange.
  amazon: { light: { a: "#232f3e", b: "#c45500", ring: "#ff9900" }, dark: { a: "#ff9900", b: "#ffb84d", ring: "#ff9900" }, fill: [BRAND, BRAND] },
  flipkart: { light: { a: "#2874f0", b: "#1a56c4", ring: "#2874f0" }, dark: { a: "#5b9bff", b: "#ffe11b", ring: "#2874f0" }, fill: [BRAND, BRAND] },
  myntra: { light: { a: "#d6194f", b: "#c2410c", ring: "#ff3f6c" }, dark: { a: "#ff6b8e", b: "#ffa45c", ring: "#ff3f6c" }, fill: [BRAND, BRAND] },
};

const light = {
  dark: false,
  bg: "#f6f7f5",
  surface: "#ffffff",
  surface2: "#eef1ee",
  ink: "#0b0b0f",
  ink2: "#55555f",
  ink3: "#9a9aa3",
  line: "#e3e7e3",
  good: "#0e9f6e",
  warn: "#d97706",
  bad: "#e5363d",
  brand: BRAND,
  brandInk: BRAND_INK,
  coral: CORAL,
  shadow: "#0b2e2c",
  skeleton: "#e7ebe7",
  overlay: "rgba(11,11,15,0.45)",
};

export type Palette = typeof light;

const dark: Palette = {
  dark: true,
  bg: "#09090c",
  surface: "#131318",
  surface2: "#1b1b22",
  ink: "#f4f4f6",
  ink2: "#a8a8b3",
  ink3: "#6b6b78",
  line: "#26262f",
  good: "#22c08a",
  warn: "#f59e0b",
  bad: "#f87171",
  brand: BRAND_DARK,
  brandInk: "#f4f4f6",
  coral: CORAL,
  shadow: "#000000",
  skeleton: "#1f1f27",
  overlay: "rgba(0,0,0,0.6)",
};

export type ThemePref = "system" | "light" | "dark";

type Ctx = { c: Palette; pref: ThemePref; setPref: (p: ThemePref) => void };
const ThemeCtx = createContext<Ctx>({ c: light, pref: "system", setPref: () => {} });
const KEY = "sr:theme";

export function ThemeProvider({ children }: { children: ReactNode }) {
  const system = useColorScheme();
  const [pref, setPrefState] = useState<ThemePref>("system");

  useEffect(() => {
    AsyncStorage.getItem(KEY)
      .then((v) => {
        if (v === "light" || v === "dark" || v === "system") setPrefState(v);
      })
      .catch(() => {});
  }, []);

  const setPref = useCallback((p: ThemePref) => {
    setPrefState(p);
    AsyncStorage.setItem(KEY, p).catch(() => {});
  }, []);

  const isDark = pref === "system" ? system === "dark" : pref === "dark";
  const c = isDark ? dark : light;

  useEffect(() => {
    if (Platform.OS !== "web") SystemUI.setBackgroundColorAsync(c.bg).catch(() => {});
  }, [c.bg]);

  const value = useMemo(() => ({ c, pref, setPref }), [c, pref, setPref]);
  return <ThemeCtx.Provider value={value}>{children}</ThemeCtx.Provider>;
}

export function useTheme() {
  return useContext(ThemeCtx);
}

/** Store accent colours for a store mode. */
export function useAccent(mode: StoreMode = "all") {
  const { c } = useTheme();
  const a = ACCENTS[mode];
  const accent = c.dark ? a.accentDark : a.accent;
  return {
    ...a,
    accent,
    heroA: c.dark ? a.heroADark : a.heroA,
    soft: withAlpha(accent, c.dark ? 0.16 : 0.11),
    ink: c.dark ? a.accent2 : a.accent,
  };
}

export function withAlpha(hex: string, alpha: number) {
  const h = hex.replace("#", "");
  const n = parseInt(h.length === 3 ? h.split("").map((x) => x + x).join("") : h, 16);
  return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${alpha})`;
}

export const radius = { sm: 12, md: 16, lg: 20, pill: 999 }; // lg = 20px card radius

/** Soft card shadow that works on both platforms. */
export function shadow(c: Palette, level: 1 | 2 = 1) {
  return Platform.select({
    ios: {
      shadowColor: c.shadow,
      shadowOpacity: c.dark ? 0.4 : level === 1 ? 0.06 : 0.1,
      shadowRadius: level === 1 ? 8 : 18,
      shadowOffset: { width: 0, height: level === 1 ? 2 : 8 },
    },
    android: { elevation: level === 1 ? 2 : 6, shadowColor: c.shadow },
    default: {
      boxShadow: c.dark
        ? level === 1 ? "0 1px 2px rgba(0,0,0,.5)" : "0 10px 36px rgba(0,0,0,.55)"
        : level === 1 ? "0 1px 1px rgba(0,0,0,.04), 0 6px 20px rgba(20,16,60,.06)" : "0 2px 6px rgba(20,16,60,.06), 0 18px 44px rgba(20,16,60,.12)",
    },
  }) as object;
}
