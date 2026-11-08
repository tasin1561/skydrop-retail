import { useCallback, useState } from "react";
import { Alert, ScrollView, StyleSheet, View } from "react-native";
import { router, useFocusEffect } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { LinearGradient } from "expo-linear-gradient";
import Animated from "react-native-reanimated";
import { ApiError, isDemo } from "@/api/client";
import { moneyApi, RETURN_PLUS, type PlusMembership, type PlusPeriod, type PlusPlan, type PlusSavings } from "@/api/money";
import { useStore } from "@/state/store";
import { useFeature } from "@/lib/features";
import { useTheme } from "@/theme";
import { Button, Card, Empty, Pill, Skeleton, T, Tap } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { useT } from "@/i18n";
import type { MsgKey } from "@/i18n";
import { listEnter } from "@/lib/motion";

const PERIOD: Record<PlusPeriod, MsgKey> = { monthly: "period.monthly", quarterly: "period.quarterly", yearly: "period.yearly" };
const PAY = [
  { id: "bkash", label: "bKash" },
  { id: "nagad", label: "Nagad" },
  { id: "card", label: "pay.card" },
] as const;

// Skydrop Plus: plans with a period toggle, buy through the payment gateway (manual renewal).
export default function PlusScreen() {
  const { t, bdt, date } = useT();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { user } = useStore();
  const on = useFeature("membership");
  const [plans, setPlans] = useState<PlusPlan[] | null>(null);
  const [me, setMe] = useState<{ membership: PlusMembership | null; savings: PlusSavings } | null>(null);
  const [period, setPeriod] = useState<PlusPeriod | null>(null);
  const [pay, setPay] = useState<(typeof PAY)[number]["id"]>("bkash");
  const [busy, setBusy] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const r = await moneyApi.plans();
      setPlans(r.items);
      setPeriod((p) => p ?? r.items.find((x) => x.highlight)?.period ?? r.items[0]?.period ?? null);
    } catch {
      setPlans([]);
    }
    if (user && !isDemo()) moneyApi.membership().then(setMe).catch(() => {});
  }, [user]);
  useFocusEffect(
    useCallback(() => {
      if (on) load();
    }, [on, load]),
  );

  if (!on) return <Empty icon="star" title={t("acc.plus")} text={t("pl.off")} />;

  const periods = (["monthly", "quarterly", "yearly"] as PlusPeriod[]).filter((p) => plans?.some((x) => x.period === p));
  const current = me?.membership && me.membership.status !== "expired" ? me.membership : null;

  const buy = async (plan: PlusPlan) => {
    if (!user) return router.push({ pathname: "/login", params: { next: "/plus" } });
    if (isDemo()) return Alert.alert(t("m.preview"), t("m.plusNotConnected"));
    setBusy(plan.slug);
    try {
      const r = await moneyApi.buyPlus(plan.slug, pay);
      if (r.payment.redirectUrl) await WebBrowser.openAuthSessionAsync(r.payment.redirectUrl, RETURN_PLUS);
      await load();
    } catch (e) {
      Alert.alert(t("m.payNotStarted"), e instanceof ApiError ? e.message : t("common.pleaseRetry"));
    } finally {
      setBusy(null);
    }
  };

  return (
    <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 32, gap: 14 }}>
      <LinearGradient colors={["#0b0b0f", "#2a1d8f"]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.hero}>
        <Pill text="SKYDROP PLUS" icon="star" color="#f5c451" bg="rgba(255,255,255,0.08)" />
        <T style={{ color: "#fff8e1", fontSize: 28, lineHeight: 34, fontWeight: "900" }}>{t("m.plusHero")}</T>
        {current ? (
          <T v="small" style={{ color: "rgba(255,255,255,0.8)" }}>
            {t("pl.youreOn", { plan: current.plan?.name ?? "" })}
            {current.renewsAt ? t("pl.paidThrough", { date: date(current.renewsAt, { day: "numeric", month: "short", year: "numeric" }) }) : ""}
          </T>
        ) : null}
        {current && me && me.savings.totalBdt > 0 ? (
          <T style={{ color: "#f5c451", fontWeight: "800" }}>
            {t("pl.savedYou")} {bdt(me.savings.totalBdt)}
          </T>
        ) : null}
      </LinearGradient>

      {periods.length > 1 && (
        <View style={[styles.toggle, { backgroundColor: c.surface2 }]} accessibilityRole="radiogroup">
          {periods.map((p) => (
            <Tap
              key={p}
              onPress={() => setPeriod(p)}
              accessibilityRole="radio"
              accessibilityState={{ checked: p === period }}
              style={[styles.toggleBtn, p === period && { backgroundColor: c.surface }]}
            >
              <T style={{ fontWeight: "700" }}>{t(`pl.${p}`)}</T>
            </Tap>
          ))}
        </View>
      )}

      {!plans ? (
        <Skeleton h={260} r={20} />
      ) : (
        plans
          .filter((p) => p.period === period)
          .map((p, i) => (
            <Animated.View key={p.id} entering={listEnter(i)}>
              <Card style={[{ gap: 8 }, p.highlight && { borderWidth: 2, borderColor: "#f5c451" }]}>
                <View style={{ flexDirection: "row", justifyContent: "space-between", alignItems: "center" }}>
                  <T v="h3">{p.name}</T>
                  {p.highlight && <Pill text={t("m.popular")} color="#b45309" />}
                </View>
                <T style={{ fontSize: 28, fontWeight: "900", fontVariant: ["tabular-nums"] }}>
                  {bdt(p.priceBdt)}
                  <T tone="ink2"> / {t(PERIOD[p.period])}</T>
                </T>
                {p.benefitList.map((b) => (
                  <View key={b} style={{ flexDirection: "row", gap: 8, alignItems: "flex-start" }}>
                    <Icon name="check" size={16} color={c.good} />
                    <T style={{ flex: 1 }}>{b}</T>
                  </View>
                ))}
                <View style={{ flexDirection: "row", gap: 8, marginTop: 6 }}>
                  {PAY.map((m) => (
                    <Tap
                      key={m.id}
                      onPress={() => setPay(m.id)}
                      accessibilityRole="radio"
                      accessibilityState={{ checked: pay === m.id }}
                      style={[styles.chip, { backgroundColor: pay === m.id ? c.brand : c.surface2 }]}
                    >
                      <T style={{ fontWeight: "700", color: pay === m.id ? "#fff" : c.ink }}>{m.label === "pay.card" ? t(m.label) : m.label}</T>
                    </Tap>
                  ))}
                </View>
                <Button label={user ? t("m.payAmount", { amount: bdt(p.priceBdt) }) : t("pl.signIn")} icon="shield" busy={busy === p.slug} onPress={() => buy(p)} />
              </Card>
            </Animated.View>
          ))
      )}
      <T v="tiny" tone="ink3" style={{ textAlign: "center" }}>
        {t("m.noAutoShort")}
      </T>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  hero: { borderRadius: 24, padding: 20, gap: 8 },
  toggle: { flexDirection: "row", borderRadius: 999, padding: 4 },
  toggleBtn: { flex: 1, minHeight: 44, borderRadius: 999, alignItems: "center", justifyContent: "center" },
  chip: { flex: 1, minHeight: 44, borderRadius: 999, alignItems: "center", justifyContent: "center" },
});
