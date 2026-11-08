import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { Platform, useColorScheme } from "react-native";
import AsyncStorage from "@react-native-async-storage/async-storage";
import * as SystemUI from "expo-system-ui";
import type { StoreMode } from "@/lib/types";

// Brand tokens mirror apps/web/src/app/globals.css (corporate neutral). Near-black ink, white surfaces on a light
// grey page, ONE deep navy accent (BRAND) for focus, links and selected states. Primary actions are solid ink
// (c.primary / c.onPrimary, inverted in dark mode). CORAL is retired: it resolves to ink so old call sites stay calm.
export const BRAND = "#233056";
export const BRAND_2 = "#8a94ad";
export const BRAND_INK = "#111111";
export const CORAL = "#111111";
/** Flat neutral for the few dark panels (wallet, points, account header): no gradients. */
export const PANEL = "#18181b";

type Accent = { accent: string; accent2: string; accentDark: string; heroA: string; heroB: string; heroADark: string };

// Store modes no longer recolour the app: every store uses the same neutral system and navy accent.
const NEUTRAL: Accent = { accent: BRAND, accent2: BRAND_2, accentDark: "#34477a", heroA: PANEL, heroB: PANEL, heroADark: "#1d1d22" };
export const ACCENTS: Record<StoreMode, Accent> = { all: NEUTRAL, amazon: NEUTRAL, flipkart: NEUTRAL, bigbasket: NEUTRAL };

/**
 * Store-name text tokens (StoreName): the providers' brand colours (owner's decision), the ONLY colour accents in
 * the chrome. "All stores" is plain ink. `fill` is the neutral selected fill (white text). Mirrors the .sn tokens in
 * apps/web/src/components/store/storename.css. Names as text only, never logos.
 */
export type StoreTextTokens = { a: string; b: string; ring: string };
const FILL: [string, string] = ["#111111", "#111111"];
export const STORE_TEXT: Record<StoreMode, { light: StoreTextTokens; dark: StoreTextTokens; fill: [string, string] }> = {
  all: { light: { a: "#111111", b: "#111111", ring: "#cfcfca" }, dark: { a: "#f2f2f3", b: "#f2f2f3", ring: "#3a3a42" }, fill: FILL },
  // Store names in each provider's brand colours (owner's decision): Amazon ink + orange, Flipkart blue + yellow, BigBasket green.
  amazon: { light: { a: "#232f3e", b: "#c45500", ring: "#ff9900" }, dark: { a: "#ff9900", b: "#ffb84d", ring: "#ff9900" }, fill: FILL },
  flipkart: { light: { a: "#2874f0", b: "#1a56c4", ring: "#2874f0" }, dark: { a: "#5b9bff", b: "#ffe11b", ring: "#2874f0" }, fill: FILL },
  bigbasket: { light: { a: "#4e8a16", b: "#5f9e1c", ring: "#84c225" }, dark: { a: "#9bd43a", b: "#c6e86e", ring: "#84c225" }, fill: FILL },
};

const light = {
  dark: false,
  bg: "#f6f6f4",
  surface: "#ffffff",
  surface2: "#f4f4f2", // image wells, quiet fills
  ink: "#111111",
  ink2: "#4b4b52",
  ink3: "#6b6b73", // 4.9:1 on bg: secondary text stays AA
  line: "#e3e3df",
  line2: "#cfcfca", // outline buttons, inputs
  good: "#1a7f4b",
  warn: "#8a5a00",
  bad: "#b42318",
  star: "#b7791f",
  brand: BRAND, // navy accent: links, focus, selected
  brandInk: BRAND_INK,
  coral: CORAL,
  primary: "#111111",
  onPrimary: "#ffffff",
  shadow: "#000000",
  skeleton: "#ececea",
  overlay: "rgba(17,17,17,0.45)",
};

export type Palette = typeof light;

const dark: Palette = {
  dark: true,
  bg: "#0e0e10",
  surface: "#16161a",
  surface2: "#1d1d22",
  ink: "#f2f2f3",
  ink2: "#b4b4bc",
  ink3: "#8c8c96",
  line: "#2a2a31",
  line2: "#3a3a42",
  good: "#4cc38a",
  warn: "#e0a84a",
  bad: "#f07a70",
  star: "#d9a441",
  brand: "#a9b6d9",
  brandInk: "#f2f2f3",
  coral: "#2c2c33", // retired coral: a dark neutral that keeps white badge text readable
  primary: "#f2f2f3",
  onPrimary: "#0e0e10",
  shadow: "#000000",
  skeleton: "#1f1f25",
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
    // "soft" fills are neutral grey (no tinted chips); accent text is the navy (lifted in dark mode)
    soft: withAlpha(c.ink, c.dark ? 0.08 : 0.05),
    ink: c.brand,
  };
}

export function withAlpha(hex: string, alpha: number) {
  const h = hex.replace("#", "");
  const n = parseInt(h.length === 3 ? h.split("").map((x) => x + x).join("") : h, 16);
  return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${alpha})`;
}

export const radius = { sm: 8, md: 10, lg: 12, pill: 999 }; // 8px controls, 10-12px cards

/** Minimal elevation: hairlines carry structure, shadows are barely there (sheets and popovers use level 2). */
export function shadow(c: Palette, level: 1 | 2 = 1) {
  return Platform.select({
    ios: {
      shadowColor: c.shadow,
      shadowOpacity: c.dark ? 0.35 : level === 1 ? 0.03 : 0.08,
      shadowRadius: level === 1 ? 2 : 16,
      shadowOffset: { width: 0, height: level === 1 ? 1 : 6 },
    },
    android: { elevation: level === 1 ? 0 : 4, shadowColor: c.shadow },
    default: {
      boxShadow: level === 1 ? `0 0 0 1px ${c.line}` : c.dark ? `0 0 0 1px ${c.line}, 0 10px 28px rgba(0,0,0,.6)` : `0 0 0 1px ${c.line}, 0 8px 24px rgba(17,17,17,.12)`,
    },
  }) as object;
}
