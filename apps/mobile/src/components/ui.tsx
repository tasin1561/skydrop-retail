// Small design-system primitives used across screens.
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
import Animated, {
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withSpring,
  withTiming,
} from "react-native-reanimated";
import { Image } from "expo-image";
import { radius, shadow, useAccent, useTheme, withAlpha } from "@/theme";
import { Icon } from "./Icon";
import type { StoreMode } from "@/lib/types";

const AnimatedPressable = Animated.createAnimatedComponent(Pressable);

// ---------- text ----------
type Variant = "h1" | "h2" | "h3" | "body" | "small" | "tiny" | "label";
const SIZES: Record<Variant, TextStyle> = {
  h1: { fontSize: 26, lineHeight: 32, fontWeight: "800", letterSpacing: -0.4 },
  h2: { fontSize: 20, lineHeight: 26, fontWeight: "800", letterSpacing: -0.2 },
  h3: { fontSize: 16, lineHeight: 22, fontWeight: "700" },
  body: { fontSize: 15, lineHeight: 21 },
  small: { fontSize: 13, lineHeight: 18 },
  tiny: { fontSize: 11, lineHeight: 14 },
  label: { fontSize: 12, lineHeight: 16, fontWeight: "700", letterSpacing: 0.4, textTransform: "uppercase" },
};

export function T({
  v = "body",
  tone = "ink",
  style,
  ...rest
}: TextProps & { v?: Variant; tone?: "ink" | "ink2" | "ink3" | "good" | "bad" | "warn" | "white" | "brand" | string }) {
  const { c } = useTheme();
  const map: Record<string, string> = { ink: c.ink, ink2: c.ink2, ink3: c.ink3, good: c.good, bad: c.bad, warn: c.warn, white: "#fff", brand: c.brand };
  return <RNText {...rest} style={[SIZES[v], { color: map[tone] ?? tone }, style]} />;
}

// ---------- pressable with spring scale ----------
export function Tap({
  children,
  style,
  scaleTo = 0.97,
  ...rest
}: PressableProps & { style?: StyleProp<ViewStyle>; scaleTo?: number; children?: ReactNode }) {
  const s = useSharedValue(1);
  const a = useAnimatedStyle(() => ({ transform: [{ scale: s.value }] }));
  return (
    <AnimatedPressable
      accessibilityRole="button"
      {...rest}
      onPressIn={(e) => {
        s.set(withSpring(scaleTo, { damping: 20, stiffness: 400 }));
        rest.onPressIn?.(e);
      }}
      onPressOut={(e) => {
        s.set(withSpring(1, { damping: 14, stiffness: 300 }));
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
  const bg =
    kind === "primary" ? a.accent : kind === "coral" ? c.coral : kind === "soft" ? a.soft : kind === "danger" ? withAlpha(c.bad, 0.12) : "transparent";
  const fg = kind === "primary" || kind === "coral" ? "#fff" : kind === "danger" ? c.bad : kind === "soft" ? a.ink : c.ink;
  return (
    <Tap
      onPress={onPress}
      disabled={disabled || busy}
      accessibilityLabel={accessibilityLabel ?? label}
      accessibilityState={{ disabled: !!disabled, busy: !!busy }}
      style={[
        styles.btn,
        { backgroundColor: bg, opacity: disabled ? 0.5 : 1 },
        kind === "ghost" && { borderWidth: 1, borderColor: c.line },
        style,
      ]}
    >
      {busy ? <ActivityIndicator color={fg} size="small" /> : icon ? <Icon name={icon} size={18} color={fg} /> : null}
      <T v="h3" style={{ color: fg, fontSize: 15 }} numberOfLines={1}>
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
        ...(bg ? {} : shadow(c)),
      }}
    >
      <Icon name={icon} size={size * 0.45} color={tint ?? c.ink} />
      {!!badge && (
        <View style={[styles.badge, { backgroundColor: c.coral, borderColor: c.surface }]}>
          <T v="tiny" tone="white" style={{ fontWeight: "800" }}>
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
  return <View style={[{ backgroundColor: c.surface, borderRadius: radius.lg, padding: pad }, shadow(c), style]}>{children}</View>;
}

export function IconChip({ icon, mode = "all", size = 36, color }: { icon: string; mode?: StoreMode; size?: number; color?: string }) {
  const a = useAccent(mode);
  return (
    <View style={{ width: size, height: size, borderRadius: size * 0.32, backgroundColor: color ? withAlpha(color, 0.12) : a.soft, alignItems: "center", justifyContent: "center" }}>
      <Icon name={icon} size={size * 0.48} color={color ?? a.ink} />
    </View>
  );
}

export function Pill({ text, color, bg, icon }: { text: string; color?: string; bg?: string; icon?: string }) {
  const { c } = useTheme();
  return (
    <View style={[styles.pill, { backgroundColor: bg ?? c.surface2 }]}>
      {icon && <Icon name={icon} size={12} color={color ?? c.ink2} />}
      <T v="tiny" style={{ color: color ?? c.ink2, fontWeight: "700" }}>
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
          <T v="small" style={{ color: c.brand, fontWeight: "700" }}>
            {action}
          </T>
        </Pressable>
      )}
    </View>
  );
}

// ---------- skeleton ----------
export function Skeleton({ w, h, r = 12, style }: { w?: number | `${number}%`; h: number; r?: number; style?: StyleProp<ViewStyle> }) {
  const { c } = useTheme();
  const o = useSharedValue(0.55);
  useEffect(() => {
    o.value = withRepeat(withTiming(1, { duration: 750, easing: Easing.inOut(Easing.quad) }), -1, true);
  }, [o]);
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
    const hue = placeholder?.hue ?? 210;
    return (
      <View
        accessibilityLabel={label}
        style={[
          { alignItems: "center", justifyContent: "center", backgroundColor: c.dark ? `hsl(${hue}, 35%, 22%)` : `hsl(${hue}, 70%, 94%)` },
          size ? { width: size, height: size } : null,
          style,
        ]}
      >
        <Icon name={placeholder?.icon ?? "package"} size={size ? size * 0.38 : 40} color={c.dark ? `hsl(${hue}, 70%, 75%)` : `hsl(${hue}, 55%, 42%)`} />
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
  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: 4 }} accessibilityLabel={`Rated ${rating} out of 5${count ? `, ${count} reviews` : ""}`}>
      <Icon name="star" size={size} color="#f59e0b" />
      <T v="tiny" style={{ fontWeight: "700", color: c.ink }}>
        {rating.toFixed(1)}
      </T>
      {count !== undefined && (
        <T v="tiny" tone="ink3">
          ({count >= 1000 ? (count / 1000).toFixed(1) + "k" : count})
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
