// Refer a friend (GET /referrals/me, feature "referral"). Mirrors apps/web/src/components/growth/InviteView.tsx.
import { useCallback, useState } from "react";
import { ScrollView, Share, StyleSheet, View } from "react-native";
import { router, useFocusEffect } from "expo-router";
import { LinearGradient } from "expo-linear-gradient";
import Animated, { FadeInDown } from "react-native-reanimated";
import { http, isDemo } from "@/api/client";
import { useStore } from "@/state/store";
import { useFeature } from "@/lib/features";
import { demoInviteCode, WEB_URL } from "@/lib/share";
import { haptic } from "@/lib/haptics";
import { BRAND, useTheme } from "@/theme";
import { Button, Card, Empty, IconChip, Progress, Skeleton, T } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { useT } from "@/i18n";

type Referrals = {
  code: string;
  link: string;
  invited: number;
  rewarded: number;
  pointsEarned: number;
  rules: { inviterPoints: number; friendPoints: number };
  friends: { name: string; joinedAt: string; status: "joined" | "rewarded" }[];
};

const pad = (n: number) => String(n).padStart(2, "0");

export default function InviteScreen() {
  const on = useFeature("referral");
  const { t, digits, date } = useT();
  const { c } = useTheme();
  const { user, ready } = useStore();
  const [data, setData] = useState<Referrals | null>(null);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      if (!ready || !user || !on) return;
      let live = true;
      if (isDemo()) {
        const code = demoInviteCode(user.phone);
        setData({ code, link: `${WEB_URL}/?invite=${code}`, invited: 0, rewarded: 0, pointsEarned: 0, rules: { inviterPoints: 200, friendPoints: 100 }, friends: [] });
        return;
      }
      http<Referrals>("GET", "/referrals/me")
        .then((r) => live && setData(r))
        .catch((e: Error) => live && setError(e.message));
      return () => {
        live = false;
      };
    }, [ready, user, on]),
  );

  if (!on) return <Empty icon="gift" title={t("na.title", { title: t("iv.refer") })} text={t("na.later")} action={t("common.backHome")} onAction={() => router.navigate("/")} />;
  if (!user) return <Empty icon="gift" title={t("iv.signIn")} text={t("iv.signInB")} action={t("nav.signIn")} onAction={() => router.push({ pathname: "/login", params: { next: "/invite" } })} />;
  if (error) return <Empty icon="alert-circle" title={t("common.didntWork")} text={error} />;
  if (!data)
    return (
      <View style={{ padding: 16, gap: 12 }}>
        <Skeleton h={190} r={22} />
        <Skeleton h={90} />
        <Skeleton h={160} />
      </View>
    );

  const { inviterPoints, friendPoints } = data.rules;
  const share = async () => {
    haptic.light();
    try {
      await Share.share({ title: "Skydrop Retail", message: `${t("iv.text", { points: friendPoints })} ${data.link}`, url: data.link });
    } catch {
      /* dismissed */
    }
  };
  const steps = [
    { icon: "share-2", title: t("iv.s1"), sub: t("iv.s1b") },
    { icon: "user", title: t("iv.s2"), sub: t("iv.s2b") },
    { icon: "truck", title: t("iv.s3"), sub: t("iv.s3b", { mine: inviterPoints, theirs: friendPoints }) },
  ];

  return (
    <ScrollView style={{ flex: 1, backgroundColor: c.bg }} contentContainerStyle={{ padding: 16, paddingBottom: 40, gap: 14 }}>
      <T v="small" tone="ink2">{t("iv.sub")}</T>
      {isDemo() && <T v="tiny" tone="ink3">{t("iv.demo")}</T>}
      <Animated.View entering={FadeInDown.duration(260)}>
        <LinearGradient colors={["#0b0b0f", BRAND]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.hero}>
          <T v="small" style={{ color: "rgba(255,255,255,0.88)" }}>{t("iv.give", { theirs: friendPoints, mine: inviterPoints })}</T>
          <T v="label" style={{ color: "rgba(255,255,255,0.75)", marginTop: 6 }}>{t("iv.code")}</T>
          <T v="h1" tone="white" selectable style={{ letterSpacing: 4, fontSize: 30 }}>{data.code}</T>
          <Button label={t("iv.share")} icon="share-2" kind="soft" onPress={share} style={{ marginTop: 10, backgroundColor: "#fff" }} />
        </LinearGradient>
      </Animated.View>

      <View style={{ flexDirection: "row", gap: 10 }} accessibilityLabel={t("iv.yours")}>
        {[
          { label: t("iv.invited"), value: digits(pad(data.invited)), icon: "user" },
          { label: t("iv.first"), value: digits(pad(data.rewarded)), icon: "truck" },
          { label: t("iv.earned"), value: digits(data.pointsEarned), icon: "star" },
        ].map((s) => (
          <Card key={s.label} pad={12} style={{ flex: 1, gap: 6 }}>
            <IconChip icon={s.icon} size={32} />
            <T v="h2" style={{ fontVariant: ["tabular-nums"] }}>{s.value}</T>
            <T v="tiny" tone="ink2" style={{ fontWeight: "700" }}>{s.label}</T>
          </Card>
        ))}
      </View>

      <Card style={{ gap: 12 }}>
        <T v="h3" accessibilityRole="header">{t("iv.how")}</T>
        {steps.map((s, i) => (
          <View key={s.title} style={{ flexDirection: "row", gap: 12, alignItems: "center" }}>
            <IconChip icon={s.icon} />
            <View style={{ flex: 1 }}>
              <T v="small" style={{ fontWeight: "800" }}>{digits(pad(i + 1))} · {s.title}</T>
              <T v="tiny" tone="ink2">{s.sub}</T>
            </View>
          </View>
        ))}
      </Card>

      <Card style={{ gap: 10 }}>
        <T v="h3" accessibilityRole="header">{t("iv.friends")}</T>
        {data.invited > 0 && (
          <>
            <Progress value={data.rewarded / data.invited} color={c.good} track={c.surface2} />
            <T v="tiny" tone="ink2">{t("iv.ofGot", { n: data.rewarded, total: data.invited })}</T>
          </>
        )}
        {data.friends.length ? (
          data.friends.map((f, i) => (
            <View key={i} style={{ flexDirection: "row", gap: 10, alignItems: "center", minHeight: 48 }}>
              <IconChip icon="user" size={32} />
              <View style={{ flex: 1 }}>
                <T v="small" style={{ fontWeight: "700" }}>{f.name}</T>
                <T v="tiny" tone="ink2">{t("iv.joined", { date: date(f.joinedAt) })}</T>
              </View>
              <T v="tiny" style={{ fontWeight: "800", color: f.status === "rewarded" ? c.good : c.warn }}>
                {f.status === "rewarded" ? t("iv.plus", { count: inviterPoints }) : t("iv.waiting")}
              </T>
            </View>
          ))
        ) : (
          <T v="small" tone="ink2">{t("iv.none")}</T>
        )}
      </Card>
      <View style={{ flexDirection: "row", gap: 6, justifyContent: "center" }}>
        <Icon name="info" size={13} color={c.ink3} />
        <T v="tiny" tone="ink3" style={{ textAlign: "center" }}>{t("iv.once")}</T>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  hero: { borderRadius: 22, padding: 20, gap: 2 },
});
