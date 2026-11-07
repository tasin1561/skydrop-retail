import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { Platform, useColorScheme } from "react-native";
import AsyncStorage from "@react-native-async-storage/async-storage";
import * as SystemUI from "expo-system-ui";
import type { StoreMode } from "@/lib/types";

// Brand tokens mirror apps/web/src/app/globals.css.
export const BRAND = "#0b6bcb";
export const CORAL = "#ff6a3d";

export const ACCENTS: Record<StoreMode, { accent: string; accent2: string; heroA: string; heroB: string }> = {
  all: { accent: BRAND, accent2: "#38bdf8", heroA: "#0b6bcb", heroB: "#38bdf8" },
  amazon: { accent: "#0f766e", accent2: "#2dd4bf", heroA: "#0f766e", heroB: "#14b8a6" },
  flipkart: { accent: "#7c3aed", accent2: "#f472b6", heroA: "#6d28d9", heroB: "#db2777" },
  bigbasket: { accent: "#c2410c", accent2: "#fbbf24", heroA: "#b45309", heroB: "#f59e0b" },
};

const light = {
  dark: false,
  bg: "#f6f7fb",
  surface: "#ffffff",
  surface2: "#f0f2f8",
  ink: "#0f172a",
  ink2: "#475569",
  ink3: "#94a3b8",
  line: "#e5e8f0",
  good: "#16a34a",
  warn: "#d97706",
  bad: "#dc2626",
  brand: BRAND,
  coral: CORAL,
  shadow: "#0f172a",
  skeleton: "#e7eaf2",
  overlay: "rgba(15,23,42,0.45)",
};

export type Palette = typeof light;

const dark: Palette = {
  dark: true,
  bg: "#0b1020",
  surface: "#121a2e",
  surface2: "#19223a",
  ink: "#e8edf7",
  ink2: "#a5b0c6",
  ink3: "#66728c",
  line: "#24304b",
  good: "#22c55e",
  warn: "#f59e0b",
  bad: "#f87171",
  brand: "#3b8fe6",
  coral: CORAL,
  shadow: "#000000",
  skeleton: "#1c2640",
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
  return { ...a, soft: withAlpha(c.dark ? a.accent2 : a.accent, c.dark ? 0.16 : 0.1), ink: c.dark ? a.accent2 : a.accent };
}

export function withAlpha(hex: string, alpha: number) {
  const h = hex.replace("#", "");
  const n = parseInt(h.length === 3 ? h.split("").map((x) => x + x).join("") : h, 16);
  return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${alpha})`;
}

export const radius = { sm: 12, md: 16, lg: 20, pill: 999 };

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
    default: { boxShadow: level === 1 ? "0 2px 8px rgba(15,23,42,.06)" : "0 8px 24px rgba(15,23,42,.1)" },
  }) as object;
}
