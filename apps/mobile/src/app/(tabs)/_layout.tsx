import { useEffect } from "react";
import { Platform, Pressable, StyleSheet, View } from "react-native";
import { Tabs, type BottomTabBarProps } from "expo-router/js-tabs";
import Animated, { useAnimatedStyle, useSharedValue, withTiming } from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useStore } from "@/state/store";
import { ACCENTS, useTheme } from "@/theme";
import { Icon } from "@/components/Icon";
import { T } from "@/components/ui";
import { haptic } from "@/lib/haptics";
import { useT, type MsgKey } from "@/i18n";
import { DUR, timing } from "@/lib/motion";

const TABS: Record<string, { label: MsgKey; icon: string }> = {
  index: { label: "nav.home", icon: "home" },
  categories: { label: "nav.categories", icon: "grid" },
  cart: { label: "nav.cart", icon: "shopping-cart" },
  orders: { label: "nav.orders", icon: "package" },
  account: { label: "nav.account", icon: "user" },
};

export default function TabLayout() {
  const { t } = useT();
  return (
    <Tabs screenOptions={{ headerShown: false }} tabBar={(p) => <TabBar {...p} />}>
      <Tabs.Screen name="index" options={{ title: t("nav.home") }} />
      <Tabs.Screen name="categories" options={{ title: t("nav.categories") }} />
      <Tabs.Screen name="cart" options={{ title: t("nav.cart") }} />
      <Tabs.Screen name="orders" options={{ title: t("nav.orders") }} />
      <Tabs.Screen name="account" options={{ title: t("nav.account") }} />
    </Tabs>
  );
}

// Custom tab bar: a travelling dot follows the active tab; cart shows a count badge.
function TabBar({ state, navigateToTab }: BottomTabBarProps) {
  const { t } = useT();
  const { c } = useTheme();
  const { mode, totals } = useStore();
  const insets = useSafeAreaInsets();
  const accent = c.dark ? ACCENTS[mode].accent2 : ACCENTS[mode].accent;
  const width = useSharedValue(0);
  const x = useSharedValue(0);
  const n = state.routes.length;

  useEffect(() => {
    x.value = withTiming(state.index, timing(DUR.open));
  }, [state.index, x]);

  const dot = useAnimatedStyle(() => ({
    transform: [{ translateX: (x.value + 0.5) * (width.value / n) - 14 }],
  }));

  return (
    <View
      onLayout={(e) => (width.value = e.nativeEvent.layout.width)}
      style={[
        styles.bar,
        { backgroundColor: c.surface, borderTopColor: c.line, paddingBottom: Math.max(insets.bottom, 8) },
        Platform.OS === "ios" ? { shadowColor: "#000", shadowOpacity: c.dark ? 0.4 : 0.06, shadowRadius: 12, shadowOffset: { width: 0, height: -2 } } : { elevation: 12 },
      ]}
    >
      <Animated.View style={[styles.dot, { backgroundColor: accent }, dot]} />
      {state.routes.map((route, i) => {
        const m = TABS[route.name];
        const meta = { label: m ? t(m.label) : route.name, icon: m?.icon ?? "circle" };
        const focused = state.index === i;
        const badge = route.name === "cart" ? totals.count : 0;
        return (
          <Pressable
            key={route.key}
            accessibilityRole="tab"
            accessibilityState={{ selected: focused }}
            accessibilityLabel={badge ? t("nav.cartCount", { count: badge }) : meta.label}
            onPress={() => {
              if (!focused) {
                haptic.tap();
                navigateToTab(route.key);
              }
            }}
            style={styles.tab}
          >
            <View>
              <Icon name={meta.icon} size={22} color={focused ? accent : c.ink3} />
              {badge > 0 && (
                <View style={[styles.badge, { backgroundColor: c.coral, borderColor: c.surface }]}>
                  <T v="tiny" tone="white" style={{ fontWeight: "800", fontSize: 10 }}>
                    {badge > 99 ? "99+" : badge}
                  </T>
                </View>
              )}
            </View>
            <T v="tiny" style={{ color: focused ? accent : c.ink3, fontWeight: focused ? "800" : "600", marginTop: 3 }}>
              {meta.label}
            </T>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: { flexDirection: "row", borderTopWidth: StyleSheet.hairlineWidth, paddingTop: 8 },
  tab: { flex: 1, alignItems: "center", justifyContent: "center", minHeight: 52 },
  dot: { position: "absolute", top: 0, left: 0, width: 28, height: 3, borderBottomLeftRadius: 3, borderBottomRightRadius: 3 },
  badge: { position: "absolute", top: -6, right: -12, minWidth: 18, height: 18, borderRadius: 9, paddingHorizontal: 4, alignItems: "center", justifyContent: "center", borderWidth: 2 },
});
