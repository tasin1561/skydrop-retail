import { Alert, ScrollView, StyleSheet, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { router } from "expo-router";
import { LinearGradient } from "expo-linear-gradient";
import { useStore } from "@/state/store";
import { useTheme, BRAND, type ThemePref } from "@/theme";
import { prettyPhone } from "@/lib/phone";
import { haptic } from "@/lib/haptics";
import { Button, Card, Divider, IconChip, Pill, T, Tap } from "@/components/ui";
import { Icon } from "@/components/Icon";

export default function Account() {
  const { c, pref, setPref } = useTheme();
  const insets = useSafeAreaInsets();
  const { user, signOut, wish, demo } = useStore();

  const row = (icon: string, title: string, sub: string, onPress: () => void) => (
    <Tap onPress={onPress} accessibilityLabel={title} style={styles.row} scaleTo={0.98}>
      <IconChip icon={icon} />
      <View style={{ flex: 1 }}>
        <T v="h3" style={{ fontSize: 15 }}>
          {title}
        </T>
        <T v="tiny" tone="ink2">
          {sub}
        </T>
      </View>
      <Icon name="chevron-right" size={18} color={c.ink3} />
    </Tap>
  );

  return (
    <ScrollView style={{ flex: 1, backgroundColor: c.bg }} contentContainerStyle={{ paddingTop: insets.top + 12, paddingHorizontal: 16, paddingBottom: 32, gap: 14 }}>
      <T v="h1" accessibilityRole="header">
        Account
      </T>

      <LinearGradient colors={[BRAND, "#38bdf8"]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.hero}>
        <View style={styles.avatar}>
          <Icon name="user" size={26} color={BRAND} />
        </View>
        <View style={{ flex: 1 }}>
          {user ? (
            <>
              <T v="h2" tone="white" numberOfLines={1}>
                {user.name || "Skydrop shopper"}
              </T>
              <T v="small" style={{ color: "rgba(255,255,255,0.9)" }}>
                {prettyPhone(user.phone)}
              </T>
            </>
          ) : (
            <>
              <T v="h2" tone="white">
                Welcome
              </T>
              <T v="small" style={{ color: "rgba(255,255,255,0.9)" }}>
                Sign in with your Bangladesh mobile number
              </T>
            </>
          )}
        </View>
      </LinearGradient>

      {!user && <Button label="Sign in with phone" icon="smartphone" onPress={() => router.push("/login")} />}

      <Card pad={6}>
        {user && row("user", "Profile", "Name and email", () => router.push("/profile"))}
        {user && <Divider style={{ marginLeft: 60 }} />}
        {row("map-pin", "Addresses", "Where Skydrop delivers your parcels", () => (user ? router.push("/addresses") : router.push("/login")))}
        <Divider style={{ marginLeft: 60 }} />
        {row("star", "Skydrop Points", "Earn on every order, save at checkout", () => (user ? router.push("/points") : router.push({ pathname: "/login", params: { next: "/points" } })))}
        <Divider style={{ marginLeft: 60 }} />
        {row("heart", "Wishlist", `${wish.length} saved`, () => router.push("/wishlist"))}
        <Divider style={{ marginLeft: 60 }} />
        {row("package", "Orders", "Track from India to your door", () => router.navigate("/orders"))}
      </Card>

      <Card style={{ gap: 10 }}>
        <T v="h3">Appearance</T>
        <View style={[styles.seg, { backgroundColor: c.surface2 }]} accessibilityRole="radiogroup">
          {(["system", "light", "dark"] as ThemePref[]).map((p) => {
            const on = pref === p;
            return (
              <Tap
                key={p}
                onPress={() => {
                  haptic.tap();
                  setPref(p);
                }}
                accessibilityRole="radio"
                accessibilityState={{ checked: on }}
                accessibilityLabel={`${p} theme`}
                style={[styles.segItem, on && { backgroundColor: c.surface }]}
              >
                <Icon name={p === "system" ? "smartphone" : p === "light" ? "sun" : "moon"} size={16} color={on ? c.ink : c.ink3} />
                <T v="small" style={{ fontWeight: "700", color: on ? c.ink : c.ink3, textTransform: "capitalize" }}>
                  {p}
                </T>
              </Tap>
            );
          })}
        </View>
      </Card>

      <Card style={{ gap: 6 }}>
        <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
          <T v="h3" style={{ flex: 1 }}>
            About
          </T>
          {demo && <Pill text="Demo mode" icon="info" />}
        </View>
        <T v="small" tone="ink2">
          Skydrop Retail buys from Indian stores (amazon.in, flipkart.com, bigbasket.com) on your behalf and Skydrop delivers to your door in Bangladesh. We are not affiliated with these stores.
        </T>
        {demo && (
          <T v="tiny" tone="ink3">
            Demo mode: the Skydrop Retail API isn't connected, so products, orders and sign-in run on this device. Payments are not taken.
          </T>
        )}
      </Card>

      {user && (
        <Button
          label="Sign out"
          kind="danger"
          icon="log-out"
          onPress={() =>
            Alert.alert("Sign out?", "Your cart stays on this phone.", [
              { text: "Cancel", style: "cancel" },
              { text: "Sign out", style: "destructive", onPress: () => signOut() },
            ])
          }
        />
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  hero: { flexDirection: "row", alignItems: "center", gap: 14, padding: 18, borderRadius: 22 },
  avatar: { width: 54, height: 54, borderRadius: 27, backgroundColor: "#fff", alignItems: "center", justifyContent: "center" },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 10, minHeight: 60 },
  seg: { flexDirection: "row", borderRadius: 14, padding: 4 },
  segItem: { flex: 1, flexDirection: "row", gap: 6, alignItems: "center", justifyContent: "center", minHeight: 44, borderRadius: 11 },
});
