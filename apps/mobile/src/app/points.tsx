import { useCallback, useState } from "react";
import { RefreshControl, ScrollView, StyleSheet, View } from "react-native";
import { router, useFocusEffect } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { LinearGradient } from "expo-linear-gradient";
import Animated from "react-native-reanimated";
import { api, isDemo } from "@/api/client";
import type { PointsResponse } from "@/api/types";
import { useStore } from "@/state/store";
import { POINTS, pointsValueBdt } from "@/lib/points";
import { PANEL, useTheme } from "@/theme";
import { Card, Divider, Empty, IconChip, Progress, Skeleton, T } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { useT } from "@/i18n";
import { listEnter } from "@/lib/motion";

export default function Points() {
  const { t, bdt, num, digits, date: fmt } = useT();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { user } = useStore();
  const [data, setData] = useState<PointsResponse | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const TONE: Record<string, { icon: string; color: string; label: string }> = {
    earn: { icon: "plus", color: c.good, label: t("pv.earned") },
    bonus: { icon: "star", color: c.good, label: t("pv.bonus") },
    refund: { icon: "refresh-cw", color: c.good, label: t("pv.returned") },
    pending: { icon: "clock", color: c.warn, label: t("pv.pending") },
    redeem: { icon: "tag", color: c.brand, label: t("pv.used") },
    expire: { icon: "x", color: c.bad, label: t("pv.expired") },
    adjust: { icon: "settings", color: c.ink2, label: t("pv.adjusted") },
  };
  // Ledger notes arrive in English (API and demo ledger); the known patterns are shown in the shopper's language.
  const noteText = (n: string) => {
    let m: RegExpExecArray | null;
    if (n === "Welcome bonus") return t("pt.welcome");
    if ((m = /^Used on order (.+)$/.exec(n))) return t("pt.used", { id: m[1] });
    if ((m = /^Returned from cancelled order (.+)$/.exec(n))) return t("pt.returned", { id: m[1] });
    if ((m = /^Earned on order (.+)$/.exec(n))) return t("pt.earned", { id: m[1] });
    if ((m = /^Pending until order (.+) is delivered$/.exec(n))) return t("pt.pending", { id: m[1] });
    return n;
  };

  const load = useCallback(async () => {
    try {
      setData(await api.points());
      setError(null);
    } catch (e) {
      setError((e as Error).message);
    }
  }, []);
  useFocusEffect(
    useCallback(() => {
      if (user || isDemo()) load();
    }, [user, load]),
  );

  if (!user)
    return <Empty icon="star" title={t("pv.signIn")} text={t("pv.signInB")} action={t("nav.signIn")} onAction={() => router.push({ pathname: "/login", params: { next: "/points" } })} />;
  if (error && !data) return <Empty icon="wifi-off" title={t("m.pointsLoadFail")} text={error} action={t("maint.retry")} onAction={load} />;

  const balance = data?.balance ?? 0;
  const toNext = POINTS.blockPoints - (balance % POINTS.blockPoints);

  return (
    <ScrollView
      contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 32, gap: 14 }}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          tintColor={c.brand}
          colors={[c.brand]}
          onRefresh={async () => {
            setRefreshing(true);
            await load();
            setRefreshing(false);
          }}
        />
      }
    >
      <LinearGradient colors={[PANEL, PANEL]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.hero}>
        <View style={styles.blob} />
        <T v="small" style={{ color: "rgba(255,255,255,0.85)", fontWeight: "700" }}>{t("acc.points")}</T>
        {data ? (
          <T style={{ color: "#fff", fontSize: 46, lineHeight: 52, fontWeight: "900", fontVariant: ["tabular-nums"] }} accessibilityLabel={t("m.pointsN", { count: balance })}>
            {num(balance)}
          </T>
        ) : (
          <Skeleton w={120} h={48} style={{ opacity: 0.4 }} />
        )}
        <T v="small" tone="white">
          {t("pv.worth", { amount: bdt(pointsValueBdt(balance)) })}
        </T>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8, marginTop: 10 }}>
          <View style={styles.tag}>
            <T v="tiny" tone="white" style={{ fontWeight: "700" }}>
              {t("m.pendingN", { count: data?.pending ?? 0 })}
            </T>
          </View>
          <View style={styles.tag}>
            <T v="tiny" tone="white" style={{ fontWeight: "700" }}>
              {t("pv.next", { count: toNext, amount: bdt(POINTS.blockValueBdt) })}
            </T>
          </View>
        </View>
        <View style={{ marginTop: 12 }}>
          <Progress value={(balance % POINTS.blockPoints) / POINTS.blockPoints} color="#fff" track="rgba(255,255,255,0.25)" />
        </View>
      </LinearGradient>

      {[
        ["file-text", t("pv.earn"), t("pv.earnT", { amount: bdt(POINTS.earnPerBdt) })],
        ["tag", t("pv.save"), t("pv.saveT", { count: POINTS.blockPoints, amount: bdt(POINTS.blockValueBdt), pct: POINTS.maxRedeemPct * 100 })],
        ["clock", t("pv.keep"), t("pv.keepT", { count: POINTS.expiryMonths })],
      ].map(([icon, title, text], i) => (
        <Animated.View key={title} entering={listEnter(i)}>
          <Card style={{ flexDirection: "row", gap: 12 }}>
            <IconChip icon={icon} />
            <View style={{ flex: 1 }}>
              <T v="h3" style={{ fontSize: 15 }}>
                {title}
              </T>
              <T v="small" tone="ink2">
                {text}
              </T>
            </View>
          </Card>
        </Animated.View>
      ))}

      <Card pad={6}>
        <T v="h2" accessibilityRole="header" style={{ padding: 10 }}>{t("pv.history")}</T>
        {!data ? (
          <View style={{ padding: 10, gap: 10 }}>
            <Skeleton h={40} />
            <Skeleton h={40} />
          </View>
        ) : data.history.length === 0 ? (
          <T v="small" tone="ink2" style={{ padding: 10 }}>{t("pv.none")}</T>
        ) : (
          data.history.map((h, i) => {
            const tone = TONE[h.type] ?? TONE.adjust;
            const date = h.at && !h.at.startsWith("1970") ? ` · ${fmt(h.at, { day: "numeric", month: "short" })}` : "";
            return (
              <View key={i}>
                {i > 0 && <Divider style={{ marginLeft: 56 }} />}
                <View style={styles.row} accessibilityLabel={`${noteText(h.note)}, ${h.points > 0 ? "+" : ""}${t("m.pointsN", { count: h.points })}`}>
                  <View style={[styles.icon, { backgroundColor: tone.color }]}>
                    <Icon name={tone.icon} size={16} color="#fff" />
                  </View>
                  <View style={{ flex: 1 }}>
                    <T v="small" numberOfLines={1} style={{ fontWeight: "600" }}>
                      {noteText(h.note)}
                    </T>
                    <T v="tiny" tone="ink3">
                      {tone.label}
                      {date}
                    </T>
                  </View>
                  <T v="h3" style={{ color: tone.color, fontWeight: "800", fontVariant: ["tabular-nums"] }}>
                    {h.points > 0 ? "+" : ""}
                    {digits(h.points)}
                  </T>
                </View>
              </View>
            );
          })
        )}
      </Card>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  hero: { borderRadius: 24, padding: 20, overflow: "hidden" },
  blob: { position: "absolute", right: -24, top: -24, width: 128, height: 128, borderRadius: 64, backgroundColor: "rgba(255,255,255,0.15)" },
  tag: { backgroundColor: "rgba(255,255,255,0.2)", paddingHorizontal: 10, paddingVertical: 4, borderRadius: 999 },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 10, minHeight: 56 },
  icon: { width: 36, height: 36, borderRadius: 12, alignItems: "center", justifyContent: "center" },
});
