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
import { useFeature } from "@/lib/features";
import { useT } from "@/i18n";
import { LangSwitch } from "@/components/LangSwitch";

export default function Account() {
  const { t } = useT();
  const { c, pref, setPref } = useTheme();
  const insets = useSafeAreaInsets();
  const { user, signOut, wish, demo } = useStore();
  const walletOn = useFeature("wallet");
  const plusOn = useFeature("membership");
  const requestsOn = useFeature("product_requests");
  const banglaOn = useFeature("bangla");

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
      <T v="h1" accessibilityRole="header">{t("acc.account")}</T>

      <LinearGradient colors={["#0b0b0f", BRAND]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.hero}>
        <View style={styles.avatar}>
          <Icon name="user" size={26} color={BRAND} />
        </View>
        <View style={{ flex: 1 }}>
          {user ? (
            <>
              <T v="h2" tone="white" numberOfLines={1}>
                {user.name || t("m.shopper")}
              </T>
              <T v="small" style={{ color: "rgba(255,255,255,0.9)" }}>
                {prettyPhone(user.phone)}
              </T>
            </>
          ) : (
            <>
              <T v="h2" tone="white">{t("m.welcome")}</T>
              <T v="small" style={{ color: "rgba(255,255,255,0.9)" }}>{t("m.signInBd")}</T>
            </>
          )}
        </View>
      </LinearGradient>

      {!user && <Button label={t("qs.withPhone")} icon="smartphone" onPress={() => router.push("/login")} />}

      <Card pad={6}>
        {user && row("user", t("m.profile"), t("m.profileSub"), () => router.push("/profile"))}
        {user && <Divider style={{ marginLeft: 60 }} />}
        {row("map-pin", t("acc.addresses"), t("m.addressesSub"), () => (user ? router.push("/addresses") : router.push("/login")))}
        <Divider style={{ marginLeft: 60 }} />
        {row("star", t("acc.points"), t("acc.pointsSub"), () => (user ? router.push("/points") : router.push({ pathname: "/login", params: { next: "/points" } })))}
        {/* money module: wallet + Skydrop Plus (feature switches "wallet" / "membership") */}
        {walletOn && <Divider style={{ marginLeft: 60 }} />}
        {walletOn && row("credit-card", t("acc.wallet"), t("m.walletSub"), () => (user ? router.push("/wallet") : router.push({ pathname: "/login", params: { next: "/wallet" } })))}
        {plusOn && <Divider style={{ marginLeft: 60 }} />}
        {plusOn && row("award", t("acc.plus"), t("acc.plusSub"), () => router.push("/plus"))}
        <Divider style={{ marginLeft: 60 }} />
        {row("heart", t("acc.wishlist"), t("m.saved", { count: wish.length }), () => router.push("/wishlist"))}
        {requestsOn && row("search", t("common.requestProduct"), t("m.requestSub"), () => router.push("/requests"))}
        <Divider style={{ marginLeft: 60 }} />
        {row("package", t("acc.orders"), t("m.ordersSub"), () => router.navigate("/orders"))}
      </Card>

      <Card style={{ gap: 10 }}>
        <T v="h3">{t("acc.appearance")}</T>
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
                accessibilityLabel={t("m.themeA11y", { name: t(`acc.${p}`) })}
                style={[styles.segItem, on && { backgroundColor: c.surface }]}
              >
                <Icon name={p === "system" ? "smartphone" : p === "light" ? "sun" : "moon"} size={16} color={on ? c.ink : c.ink3} />
                <T v="small" style={{ fontWeight: "700", color: on ? c.ink : c.ink3 }}>
                  {t(`acc.${p}`)}
                </T>
              </Tap>
            );
          })}
        </View>
      </Card>

      {banglaOn && (
      <Card style={{ gap: 10 }}>
        <View style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
          <View style={{ flex: 1, gap: 2 }}>
            <T v="h3">{t("acc.language")}</T>
            <T v="tiny" tone="ink2">
              {t(user ? "acc.languageSub" : "m.languageSubLocal")}
            </T>
          </View>
          <LangSwitch />
        </View>
      </Card>
      )}

      <Card style={{ gap: 6 }}>
        <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
          <T v="h3" style={{ flex: 1 }}>{t("m.about")}</T>
          {demo && <Pill text={t("m.demoMode")} icon="info" />}
        </View>
        <T v="small" tone="ink2">{t("m.aboutBody")}</T>
        {demo && (
          <T v="tiny" tone="ink3">{t("m.demoBody")}</T>
        )}
      </Card>

      {user && (
        <Button
          label={t("acc.signOut")}
          kind="danger"
          icon="log-out"
          onPress={() =>
            Alert.alert(t("acc.signOutQ"), t("m.cartStays"), [
              { text: t("common.cancel"), style: "cancel" },
              { text: t("acc.signOut"), style: "destructive", onPress: () => signOut() },
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
