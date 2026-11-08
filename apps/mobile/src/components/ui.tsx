// Small design-system primitives used across screens.
import { brandFont } from "@/theme/fonts";
import { useEffect, type ReactNode } from "react";
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text as RNText,
  View,
  type PressableProps,
  type StyleProp,
  type TextProps,
  type TextStyle,
  type ViewStyle,
} from "react-native";
import Animated, { Easing, useAnimatedStyle, useReducedMotion, useSharedValue, withRepeat, withTiming } from "react-native-reanimated";
import { Image } from "expo-image";
import { radius, useAccent, useTheme, withAlpha } from "@/theme";
import { Icon } from "./Icon";
import type { StoreMode } from "@/lib/types";
import { useLang, useT } from "@/i18n";
import { DUR, EASE, timing } from "@/lib/motion";

const AnimatedPressable = Animated.createAnimatedComponent(Pressable);

// ---------- text ----------
type Variant = "h1" | "h2" | "h3" | "body" | "small" | "tiny" | "label";
const SIZES: Record<Variant, TextStyle> = {
  h1: { fontSize: 24, lineHeight: 30, fontWeight: "600", letterSpacing: -0.4 },
  h2: { fontSize: 19, lineHeight: 25, fontWeight: "600", letterSpacing: -0.2 },
  h3: { fontSize: 15, lineHeight: 21, fontWeight: "600" },
  body: { fontSize: 15, lineHeight: 21 },
  small: { fontSize: 13, lineHeight: 18 },
  tiny: { fontSize: 11, lineHeight: 14 },
  label: { fontSize: 12, lineHeight: 16, fontWeight: "500", letterSpacing: 0.4, textTransform: "uppercase" },
};

export function T({
  v = "body",
  tone = "ink",
  style,
  ...rest
}: TextProps & { v?: Variant; tone?: "ink" | "ink2" | "ink3" | "good" | "bad" | "warn" | "white" | "brand" | string }) {
  const { c } = useTheme();
  const { lang } = useLang(); // re-render on language change (font family follows the language)
  const map: Record<string, string> = { ink: c.ink, ink2: c.ink2, ink3: c.ink3, good: c.good, bad: c.bad, warn: c.warn, white: "#fff", brand: c.brand };
  const flat = StyleSheet.flatten([SIZES[v], style]) ?? {};
  // Bangla: a little more line height for matras and conjuncts, and never tightened letter spacing.
  const bn = lang === "bn" ? { letterSpacing: 0, ...(flat.lineHeight ? { lineHeight: Math.round(flat.lineHeight * 1.18) } : {}) } : null;
  return <RNText {...rest} style={[SIZES[v], { color: map[tone] ?? tone }, style, brandFont(flat.fontWeight), bn]} />;
}

// ---------- pressable with spring scale ----------
export function Tap({
  children,
  style,
  scaleTo = 0.98,
  ...rest
}: PressableProps & { style?: StyleProp<ViewStyle>; scaleTo?: number; children?: ReactNode }) {
  const s = useSharedValue(1);
  const a = useAnimatedStyle(() => ({ transform: [{ scale: s.value }] }));
  return (
    <AnimatedPressable
      accessibilityRole="button"
      {...rest}
      onPressIn={(e) => {
        s.set(withTiming(scaleTo, timing(DUR.fast)));
        rest.onPressIn?.(e);
      }}
      onPressOut={(e) => {
        s.set(withTiming(1, timing(DUR.base)));
        rest.onPressOut?.(e);
      }}
      style={[style, a]}
    >
      {children}
    </AnimatedPressable>
  );
}

// ---------- buttons ----------
export function Button({
  label,
  onPress,
  icon,
  kind = "primary",
  busy,
  disabled,
  mode = "all",
  style,
  accessibilityLabel,
  right,
}: {
  label: string;
  onPress?: () => void;
  icon?: string;
  kind?: "primary" | "soft" | "ghost" | "coral" | "danger";
  busy?: boolean;
  disabled?: boolean;
  mode?: StoreMode;
  style?: StyleProp<ViewStyle>;
  accessibilityLabel?: string;
  right?: ReactNode;
}) {
  const { c } = useTheme();
  const a = useAccent(mode);
  void a; // store mode no longer recolours buttons
  // primary (and the retired coral) = solid ink; soft = quiet grey; ghost = white with a 1px grey border
  const solid = kind === "primary" || kind === "coral";
  const bg = solid ? c.primary : kind === "soft" ? c.surface2 : kind === "danger" ? withAlpha(c.bad, 0.08) : c.surface;
  const fg = solid ? c.onPrimary : kind === "danger" ? c.bad : c.ink;
  return (
    <Tap
      onPress={onPress}
      disabled={disabled || busy}
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityState={{ disabled: !!disabled, busy: !!busy }}
      style={[
        styles.btn,
        { backgroundColor: bg, opacity: disabled ? 0.5 : 1 },
        kind === "ghost" && { borderWidth: 1, borderColor: c.line2 },
        style,
      ]}
    >
      {busy ? <ActivityIndicator color={fg} size="small" /> : icon ? <Icon name={icon} size={18} color={fg} /> : null}
      <T v="h3" style={{ color: fg, fontSize: 15, fontWeight: "500" }} numberOfLines={1}>
        {label}
      </T>
      {right}
    </Tap>
  );
}

export function IconButton({
  icon,
  onPress,
  label,
  tint,
  bg,
  size = 44,
  badge,
}: {
  icon: string;
  onPress?: () => void;
  label: string;
  tint?: string;
  bg?: string;
  size?: number;
  badge?: number;
}) {
  const { c } = useTheme();
  return (
    <Tap
      onPress={onPress}
      accessibilityLabel={label}
      hitSlop={size < 48 ? (48 - size) / 2 : 0}
      style={{
        width: size,
        height: size,
        borderRadius: size / 2,
        alignItems: "center",
        justifyContent: "center",
        backgroundColor: bg ?? c.surface,
        ...(bg ? {} : { borderWidth: StyleSheet.hairlineWidth, borderColor: c.line2 }),
      }}
    >
      <Icon name={icon} size={size * 0.45} color={tint ?? c.ink} />
      {!!badge && (
        <View style={[styles.badge, { backgroundColor: c.primary, borderColor: c.surface }]}>
          <T v="tiny" style={{ fontWeight: "600", color: c.onPrimary }}>
            {badge > 9 ? "9+" : badge}
          </T>
        </View>
      )}
    </Tap>
  );
}

// ---------- surfaces ----------
export function Card({ children, style, pad = 16 }: { children: ReactNode; style?: StyleProp<ViewStyle>; pad?: number }) {
  const { c } = useTheme();
  return <View style={[{ backgroundColor: c.surface, borderRadius: radius.lg, padding: pad, borderWidth: StyleSheet.hairlineWidth, borderColor: c.line }, style]}>{children}</View>;
}

/** Monochrome line icon in a hairline square: no tinted fills. A semantic colour (good/bad/warn) tints the icon only. */
export function IconChip({ icon, mode = "all", size = 36, color }: { icon: string; mode?: StoreMode; size?: number; color?: string }) {
  const { c } = useTheme();
  void mode;
  const semantic = color === c.good || color === c.bad || color === c.warn;
  return (
    <View style={{ width: size, height: size, borderRadius: Math.min(10, size * 0.25), borderWidth: StyleSheet.hairlineWidth, borderColor: c.line2, alignItems: "center", justifyContent: "center" }}>
      <Icon name={icon} size={size * 0.46} color={semantic ? color : c.ink2} />
    </View>
  );
}

export function Pill({ text, color, bg, icon }: { text: string; color?: string; bg?: string; icon?: string }) {
  const { c } = useTheme();
  return (
    <View style={[styles.pill, { backgroundColor: bg ?? c.surface2 }]}>
      {icon && <Icon name={icon} size={12} color={color ?? c.ink2} />}
      <T v="tiny" style={{ color: color ?? c.ink2, fontWeight: "500" }}>
        {text}
      </T>
    </View>
  );
}

export function Divider({ style }: { style?: StyleProp<ViewStyle> }) {
  const { c } = useTheme();
  return <View style={[{ height: StyleSheet.hairlineWidth, backgroundColor: c.line }, style]} />;
}

export function SectionTitle({ title, action, onAction }: { title: string; action?: string; onAction?: () => void }) {
  const { c } = useTheme();
  return (
    <View style={styles.sectionTitle}>
      <T v="h2" accessibilityRole="header">
        {title}
      </T>
      {action && (
        <Pressable onPress={onAction} hitSlop={12} accessibilityRole="button" accessibilityLabel={action} style={{ minHeight: 44, justifyContent: "center" }}>
          <T v="small" style={{ color: c.ink2, fontWeight: "500" }}>
            {action} →
          </T>
        </Pressable>
      )}
    </View>
  );
}

// ---------- skeleton ----------
export function Skeleton({ w, h, r = 12, style }: { w?: number | `${number}%`; h: number; r?: number; style?: StyleProp<ViewStyle> }) {
  const { c } = useTheme();
  const o = useSharedValue(0.7);
  const reduce = useReducedMotion();
  // very slow, low-contrast breathing (one cycle per 1.6s); static with Reduce Motion
  useEffect(() => {
    if (!reduce) o.value = withRepeat(withTiming(1, { duration: 800, easing: EASE }), -1, true);
  }, [o, reduce]);
  const a = useAnimatedStyle(() => ({ opacity: o.value }));
  return <Animated.View style={[{ width: w ?? "100%", height: h, borderRadius: r, backgroundColor: c.skeleton }, a, style]} />;
}

// ---------- product image with placeholder ----------
export function ProductImage({
  uri,
  placeholder,
  size,
  style,
  contentFit = "contain",
  label,
}: {
  uri: string | null | undefined;
  placeholder?: { hue: number; icon: string };
  size?: number;
  style?: StyleProp<ViewStyle>;
  contentFit?: "contain" | "cover";
  label?: string;
}) {
  const { c } = useTheme();
  if (!uri) {
    // neutral image well with a grey line icon (no per-product pastel tint)
    return (
      <View
        accessibilityLabel={label}
        style={[{ alignItems: "center", justifyContent: "center", backgroundColor: c.surface2 }, size ? { width: size, height: size } : null, style]}
      >
        <Icon name={placeholder?.icon ?? "package"} size={size ? size * 0.38 : 40} color={c.ink3} />
      </View>
    );
  }
  return (
    <View style={[{ backgroundColor: "#fff", overflow: "hidden" }, size ? { width: size, height: size } : null, style]}>
      <Image
        source={{ uri }}
        style={{ width: "100%", height: "100%" }}
        contentFit={contentFit}
        transition={180}
        cachePolicy="memory-disk"
        recyclingKey={uri}
        accessibilityLabel={label}
      />
    </View>
  );
}

// ---------- stars ----------
export function Stars({ rating, count, size = 12 }: { rating: number; count?: number; size?: number }) {
  const { c } = useTheme();
  const { t, digits, num } = useT();
  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: 4 }} accessibilityLabel={t("m.rated", { rating: digits(rating) }) + (count ? ", " + t("m.reviewsN", { count }) : "")}>
      <Icon name="star" size={size} color={c.star} />
      <T v="tiny" style={{ fontWeight: "500", color: c.ink2 }}>
        {digits(rating.toFixed(1))}
      </T>
      {count !== undefined && (
        <T v="tiny" tone="ink3">
          ({count >= 1000 ? digits((count / 1000).toFixed(1)) + "k" : num(count)})
        </T>
      )}
    </View>
  );
}

// ---------- empty state ----------
export function Empty({ icon, title, text, action, onAction }: { icon: string; title: string; text: string; action?: string; onAction?: () => void }) {
  return (
    <View style={{ alignItems: "center", padding: 32, gap: 10 }}>
      <IconChip icon={icon} size={64} />
      <T v="h2" style={{ textAlign: "center", marginTop: 6 }}>
        {title}
      </T>
      <T v="body" tone="ink2" style={{ textAlign: "center" }}>
        {text}
      </T>
      {action && <Button label={action} onPress={onAction} style={{ marginTop: 8, paddingHorizontal: 24 }} />}
    </View>
  );
}

// ---------- progress bar (scaleX only) ----------
export function Progress({ value, color, track }: { value: number; color: string; track?: string }) {
  const { c } = useTheme();
  const v = useSharedValue(0);
  useEffect(() => {
    v.value = withTiming(Math.max(0, Math.min(1, value)), { duration: 600, easing: Easing.out(Easing.cubic) });
  }, [value, v]);
  const a = useAnimatedStyle(() => ({ transform: [{ scaleX: v.value }] }));
  return (
    <View style={{ height: 6, borderRadius: 3, backgroundColor: track ?? c.surface2, overflow: "hidden" }}>
      <Animated.View style={[{ height: 6, width: "100%", backgroundColor: color, transformOrigin: "left" }, a]} />
    </View>
  );
}

const styles = StyleSheet.create({
  btn: {
    minHeight: 50,
    borderRadius: radius.md,
    paddingHorizontal: 18,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: 8,
  },
  badge: {
    position: "absolute",
    top: -2,
    right: -2,
    minWidth: 18,
    height: 18,
    borderRadius: 9,
    paddingHorizontal: 4,
    alignItems: "center",
    justifyContent: "center",
    borderWidth: 2,
  },
  pill: { flexDirection: "row", alignItems: "center", gap: 4, paddingHorizontal: 8, paddingVertical: 4, borderRadius: radius.pill },
  sectionTitle: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", paddingHorizontal: 16, marginBottom: 10 },
});
