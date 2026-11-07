import { useEffect } from "react";
import { StyleSheet, View, useWindowDimensions } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Animated, {
  Easing,
  FadeInDown,
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withRepeat,
  withSequence,
  withSpring,
  withTiming,
} from "react-native-reanimated";
import Svg, { Path, Rect } from "react-native-svg";
import { formatBdt, deliveryWindow } from "@/lib/pricing";
import { addDays } from "@/lib/cart";
import { BRAND, CORAL, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Button, Card, T } from "@/components/ui";

export default function Success() {
  const { id, total, paid } = useLocalSearchParams<{ id: string; total?: string; paid?: string }>();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { height } = useWindowDimensions();
  const w = deliveryWindow();
  const now = new Date().toISOString();

  // Parcel floats down under its parachute, sways, then lands with a little bounce.
  const y = useSharedValue(-height * 0.4);
  const sway = useSharedValue(0);
  const land = useSharedValue(1);
  useEffect(() => {
    y.value = withTiming(0, { duration: 1800, easing: Easing.out(Easing.cubic) });
    sway.value = withRepeat(withSequence(withTiming(-6, { duration: 700, easing: Easing.inOut(Easing.sin) }), withTiming(6, { duration: 700, easing: Easing.inOut(Easing.sin) })), -1, true);
    land.value = withDelay(1750, withSequence(withTiming(0.9, { duration: 120 }), withSpring(1, { damping: 6 })));
    const t = setTimeout(() => haptic.success(), 1800);
    return () => clearTimeout(t);
  }, [y, sway, land, height]);

  const chute = useAnimatedStyle(() => ({ transform: [{ translateY: y.value }, { rotate: `${sway.value}deg` }, { scaleY: land.value }] }));

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: insets.top + 24, paddingBottom: insets.bottom + 16, paddingHorizontal: 20 }}>
      <View style={{ alignItems: "center", height: 220, justifyContent: "flex-end" }}>
        <Animated.View style={[{ transformOrigin: "bottom" }, chute]} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
          <Svg width={150} height={190} viewBox="200 180 624 660">
            <Path d="M232 470 C232 300 368 196 512 196 C656 196 792 300 792 470 C756 446 716 446 680 470 C644 446 604 446 568 470 C532 446 492 446 456 470 C420 446 380 446 344 470 C308 446 268 446 232 470 Z" fill={CORAL} />
            <Path d="M456 470 C456 330 486 220 512 196 C538 220 568 330 568 470 C532 446 492 446 456 470 Z" fill="#fff" fillOpacity={0.28} />
            <Path d="M240 466 L430 640 M400 462 L462 640 M624 462 L562 640 M784 466 L594 640" stroke={c.ink3} strokeWidth={10} strokeLinecap="round" />
            <Rect x={404} y={628} width={216} height={196} rx={30} fill={BRAND} />
            <Rect x={490} y={628} width={44} height={196} fill="#5ea8f0" />
          </Svg>
        </Animated.View>
        <View style={[styles.ground, { backgroundColor: c.line }]} />
      </View>

      <Animated.View entering={FadeInDown.delay(1500).springify().damping(16)} style={{ alignItems: "center", gap: 6, marginTop: 20 }}>
        <T v="h1" accessibilityRole="header" style={{ textAlign: "center" }}>
          Order placed!
        </T>
        <T v="body" tone="ink2" style={{ textAlign: "center" }}>
          We'll buy your items in India and Skydrop will bring them to your door.
        </T>
      </Animated.View>

      <Animated.View entering={FadeInDown.delay(1700).springify().damping(16)} style={{ marginTop: 20 }}>
        <Card style={{ gap: 10 }}>
          {[
            ["Order", id],
            ["Total", total ? formatBdt(Number(total)) : "-"],
            ...(paid ? [["Payment", paid === "paid" ? "Paid" : paid]] : []),
            ["Estimated delivery", `${addDays(now, w.min)} - ${addDays(now, w.max)}`],
          ].map(([k, v]) => (
            <View key={k} style={{ flexDirection: "row", justifyContent: "space-between" }}>
              <T v="small" tone="ink2">
                {k}
              </T>
              <T v="small" style={{ fontWeight: "800" }} selectable>
                {v}
              </T>
            </View>
          ))}
        </Card>
      </Animated.View>

      <View style={{ flex: 1 }} />
      <Animated.View entering={FadeInDown.delay(1900)} style={{ gap: 10 }}>
        <Button label="Track order" icon="map-pin" onPress={() => router.replace(`/order/${id}`)} />
        <Button label="Continue shopping" kind="ghost" onPress={() => router.dismissTo("/")} />
      </Animated.View>
    </View>
  );
}

const styles = StyleSheet.create({
  ground: { width: 180, height: 6, borderRadius: 3, marginTop: 4, opacity: 0.7 },
});
