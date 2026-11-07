// Store names as text only (no logos, no icons): each store's name in its own two-stop gradient.
// Real <Text> (one string for screen readers). The gradient is per-glyph colour interpolation, so it needs
// no native mask; Bangla is coloured per word so conjuncts are never split. Shine: a highlight band that
// sweeps across the text ONCE on mount and again when the name becomes selected (Reduce Motion: none).
import { useEffect, useState } from "react";
import { Text as RNText, View, type StyleProp, type TextStyle, type ViewStyle } from "react-native";
import Animated, { useAnimatedStyle, useReducedMotion, useSharedValue, withTiming } from "react-native-reanimated";
import { LinearGradient } from "expo-linear-gradient";
import { STORE_TEXT, useTheme, withAlpha } from "@/theme";
import { isMarket, storeText } from "@/lib/markets";
import type { StoreMode } from "@/lib/types";
import { useT } from "@/i18n";
import { EASE } from "@/lib/motion";
import { T } from "./ui";

function hex(h: string) {
  const n = parseInt(h.slice(1), 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}
function lerp(a: string, b: string, t: number) {
  const A = hex(a);
  const B = hex(b);
  return `rgb(${A.map((v, i) => Math.round(v + (B[i] - v) * t)).join(",")})`;
}

/** Tokens for a store name: theme-aware unless a tone is forced (names on a fixed deep tint or white). */
export function useStoreText(mode: StoreMode, tone?: "light" | "dark") {
  const { c } = useTheme();
  const k = STORE_TEXT[mode];
  return { ...(tone ? k[tone] : c.dark ? k.dark : k.light), fill: k.fill };
}

/** The gradient name as nested Text: usable inside another <T> line (no shine, no layout box). */
export function StoreNameText({ mode, name, tone, fill }: { mode: StoreMode; name?: string; tone?: "light" | "dark"; fill?: boolean }) {
  const { t } = useT();
  const k = useStoreText(mode, tone);
  const label = name ?? storeText(mode, t).name;
  if (fill) return <RNText style={{ color: "#fff" }}>{label}</RNText>;
  // Latin: per character. Anything else (Bangla): per word, so shaping/conjuncts stay intact.
  const parts = /^[\x20-\x7e]*$/.test(label) ? Array.from(label) : label.split(/(\s+)/);
  const n = Math.max(parts.length - 1, 1);
  return (
    <RNText>
      {parts.map((p, i) => (
        <RNText key={i} style={{ color: lerp(k.a, k.b, i / n) }}>
          {p}
        </RNText>
      ))}
    </RNText>
  );
}

type Props = {
  mode: StoreMode;
  /** plain = text, pill = text on a soft accent tint with a 1px ring, hero = large display name */
  variant?: "plain" | "pill" | "hero";
  name?: string;
  /** show the muted source after the name (markets only); pass a string to override */
  source?: boolean | string;
  /** selected: a pill fills with the store colour and the text turns white */
  fill?: boolean;
  tone?: "light" | "dark";
  size?: number;
  style?: StyleProp<ViewStyle>;
  textStyle?: StyleProp<TextStyle>;
};

const SIZE = { plain: 15, pill: 13, hero: 26 };

/** A store's name as its own highlighted, gradient text. The only way a store is identified in the app. */
export function StoreName({ mode, variant = "plain", name, source, fill, tone, size, style, textStyle }: Props) {
  const { t } = useT();
  const { c } = useTheme();
  const k = useStoreText(mode, tone);
  const reduce = useReducedMotion();
  const s = storeText(mode, t);
  const label = name ?? s.name;
  const src = typeof source === "string" ? source : source && isMarket(mode) ? s.source : null;
  const fs = size ?? SIZE[variant];
  const text: TextStyle = {
    fontSize: fs,
    lineHeight: Math.round(fs * 1.25),
    fontWeight: variant === "hero" ? "800" : "700",
    letterSpacing: variant === "hero" ? -0.4 : 0,
  };
  const muted = fill ? "rgba(255,255,255,0.85)" : tone === "dark" ? "rgba(255,255,255,0.75)" : c.ink2;

  // ---- the shine: a band clipped to the text box, its contents counter-translated so the glyphs line up
  const [w, setW] = useState(0);
  const band = Math.max(28, w * 0.38);
  const x = useSharedValue(-1e4);
  useEffect(() => {
    if (!w || reduce) return;
    x.set(-band);
    x.set(withTiming(w, { duration: 900, easing: EASE }));
  }, [w, band, reduce, fill, x]);
  const bandStyle = useAnimatedStyle(() => ({ transform: [{ translateX: x.value }] }));
  const innerStyle = useAnimatedStyle(() => ({ transform: [{ translateX: -x.value }] }));
  const shine = fill ? "#ffe3d6" : "rgba(255,255,255,0.85)";

  const nameNode = (
    <View
      style={{ alignSelf: "flex-start", maxWidth: "100%", overflow: "hidden" }}
      onLayout={(e) => setW(Math.round(e.nativeEvent.layout.width))}
    >
      <T numberOfLines={1} style={[text, textStyle]}>
        <StoreNameText mode={mode} name={label} tone={tone} fill={fill} />
      </T>
      {w > 0 && !reduce && (
        <Animated.View
          pointerEvents="none"
          accessibilityElementsHidden
          importantForAccessibility="no-hide-descendants"
          style={[{ position: "absolute", top: 0, bottom: 0, left: 0, width: band, overflow: "hidden" }, bandStyle]}
        >
          <Animated.View style={[{ width: w }, innerStyle]}>
            <T numberOfLines={1} style={[text, textStyle, { color: shine }]}>
              {label}
            </T>
          </Animated.View>
        </Animated.View>
      )}
    </View>
  );

  if (variant === "hero") {
    return (
      <View style={style}>
        {nameNode}
        {src && (
          <T v="small" style={{ color: muted, fontWeight: "600", marginTop: 2 }}>
            {t("home.sourcedFrom", { source: src })}
          </T>
        )}
      </View>
    );
  }

  const inner = (
    <>
      {nameNode}
      {src && (
        <T numberOfLines={1} style={{ color: muted, fontSize: Math.round(fs * 0.82), fontWeight: "500", marginLeft: 6, flexShrink: 1 }}>
          · {src}
        </T>
      )}
    </>
  );

  if (variant === "pill") {
    const box: ViewStyle = { flexDirection: "row", alignItems: "center", alignSelf: "flex-start", minHeight: 28, paddingHorizontal: 10, borderRadius: 999, overflow: "hidden" };
    if (fill)
      return (
        <LinearGradient colors={k.fill} start={{ x: 0, y: 0.5 }} end={{ x: 1, y: 0.5 }} style={[box, style]}>
          {inner}
        </LinearGradient>
      );
    return <View style={[box, { backgroundColor: withAlpha(k.ring, 0.11), borderWidth: 1, borderColor: withAlpha(k.ring, 0.55) }, style]}>{inner}</View>;
  }
  return <View style={[{ flexDirection: "row", alignItems: "center", flexShrink: 1 }, style]}>{inner}</View>;
}
