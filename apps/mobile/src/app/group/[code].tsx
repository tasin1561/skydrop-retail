// Live group buy: members, deadline, shipping split, share link, join / leave / close / cancel.
// Mirrors apps/web/src/components/shopping/GroupView.tsx. Deep link: skydropretail://group/CODE.
import { useCallback, useState } from "react";
import { Alert, AppState, RefreshControl, ScrollView, Share, StyleSheet, View } from "react-native";
import { router, Stack, useFocusEffect, useLocalSearchParams } from "expo-router";
import { LinearGradient } from "expo-linear-gradient";
import Animated, { FadeInDown } from "react-native-reanimated";
import { api, ApiError } from "@/api/client";
import { useStore } from "@/state/store";
import { useFeature } from "@/lib/features";
import { STORES } from "@/lib/markets";
import { parseGroupCode, shoppingMore, type Group } from "@/lib/shopping";
import { haptic } from "@/lib/haptics";
import { radius, useAccent, useTheme } from "@/theme";
import { Button, Card, Empty, IconButton, Skeleton, T } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { Countdown } from "@/components/offers/offers";
import { useToast } from "@/components/Toast";
import { hasKey, useT, type MsgKey } from "@/i18n";

type Act = "join" | "leave" | "close" | "cancel";

export default function GroupScreen() {
  const { code: raw } = useLocalSearchParams<{ code: string }>();
  const code = parseGroupCode(String(raw ?? ""));
  const on = useFeature("group_orders");
  const { t, bdt, digits } = useT();
  const { c } = useTheme();
  const toast = useToast();
  const { cart, user, ready } = useStore();
  const [g, setG] = useState<Group | null | undefined>(undefined);
  const [busy, setBusy] = useState<Act | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(
    () =>
      shoppingMore.groups
        .get(code)
        .then(setG)
        .catch((e) => setG((cur) => (e instanceof ApiError && e.status === 404 ? null : (cur ?? null)))),
    [code],
  );

  // Live member list: refresh every 20 s while the screen is focused and the app is in the foreground.
  useFocusEffect(
    useCallback(() => {
      if (!ready || !on) return;
      load();
      const id = setInterval(() => AppState.currentState === "active" && load(), 20_000);
      return () => clearInterval(id);
    }, [ready, on, load, user]), // eslint-disable-line react-hooks/exhaustive-deps
  );

  const a = useAccent(g?.market ?? "all");
  if (!on) return <Empty icon="users" title={t("na.title", { title: t("grp.groupBuy") })} text={t("na.later")} action={t("common.backHome")} onAction={() => router.navigate("/")} />;
  if (g === undefined)
    return (
      <View style={{ padding: 16, gap: 12, backgroundColor: c.bg, flex: 1 }}>
        <Skeleton h={180} r={22} />
        <Skeleton h={120} />
        <Skeleton h={160} />
      </View>
    );
  if (g === null) return <Empty icon="users" title={t("grp.notFound")} text={t("grp.notFoundBody")} action={t("grp.startOwn")} onAction={() => router.replace("/group")} />;

  const store = STORES[g.market];
  const mine = cart.filter((i) => i.market === g.market);
  const open = g.status === "open";
  const joined = !!g.you && g.you.status !== "left";
  const need = Math.max(0, g.minMembers - g.members.length);

  async function run(kind: Act) {
    if (!user) {
      router.push({ pathname: "/login", params: { next: `/group/${code}` } });
      return;
    }
    setBusy(kind);
    try {
      let next: Group;
      if (kind === "join") {
        const list = await api.addresses().catch(() => []);
        const addr = list.find((x) => x.isDefault) ?? list[0];
        next = await shoppingMore.groups.join(code, {
          items: mine.map((i) => ({ productId: i.productId, variant: i.variant ?? null, qty: i.qty })),
          ...(addr && /^\d+$/.test(addr.id) ? { addressId: Number(addr.id) } : {}),
          payment: g!.you?.payment ?? "bkash",
        });
      } else next = await shoppingMore.groups[kind](code);
      setG(next);
      haptic.success();
      toast({ kind: "success", title: { join: joined ? t("grp.updated") : t("grp.joined"), leave: t("grp.left"), close: t("grp.closed"), cancel: t("grp.cancelled") }[kind] });
    } catch (e) {
      haptic.error();
      toast({ kind: "error", title: t("common.didntWork"), body: e instanceof ApiError ? e.message : t("common.pleaseRetry") });
    } finally {
      setBusy(null);
    }
  }

  const confirm = (kind: Exclude<Act, "join">) => {
    const [title, body, cta] =
      kind === "close" ? [t("mx.grpCloseQ"), t("mx.grpCloseB"), t("grp.closeNow")] : kind === "cancel" ? [t("mx.grpCancelQ"), t("mx.grpCancelB"), t("grp.cancel")] : [t("mx.grpLeaveQ"), "", t("grp.leave")];
    Alert.alert(title, body || undefined, [
      { text: t("common.cancel"), style: "cancel" },
      { text: cta, style: kind === "close" ? "default" : "destructive", onPress: () => run(kind) },
    ]);
  };

  async function share() {
    haptic.light();
    const text = t("grp.shareText", { store: store.name });
    try {
      await Share.share({ title: t("grp.groupBuy"), message: `${text}\n${g!.shareUrl}`, url: g!.shareUrl });
    } catch {
      /* dismissed */
    }
  }

  return (
    <>
      <Stack.Screen options={{ title: t("grp.groupBuy"), headerRight: open ? () => <IconButton icon="share-2" label={t("mx.grpShareA11y")} onPress={share} bg="transparent" /> : undefined }} />
      <ScrollView
        style={{ flex: 1, backgroundColor: c.bg }}
        contentContainerStyle={{ padding: 16, paddingBottom: 40, gap: 14 }}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={async () => {
              setRefreshing(true);
              await load();
              setRefreshing(false);
            }}
            tintColor={c.brand}
          />
        }
      >
        <Card pad={0} style={{ overflow: "hidden" }}>
          <LinearGradient colors={[a.heroA ?? "#0b0b0f", a.accent]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={{ padding: 18, gap: 8 }}>
            <View style={styles.badge}>
              <Icon name={store.icon} size={13} color="#fff" />
              <T v="tiny" tone="white" style={{ fontWeight: "800" }}>{t("grp.groupBuyStore", { store: store.name })}</T>
            </View>
            <T v="h1" tone="white" accessibilityRole="header">{t("grp.ownerOrder", { owner: g.owner })}</T>
            {open ? (
              <View style={{ flexDirection: "row", alignItems: "center", gap: 6, flexWrap: "wrap" }}>
                <Icon name="clock" size={14} color="#fff" />
                <Countdown endsAt={g.closesAt} compact color="#fff" size="small" />
                <T v="small" style={{ color: "rgba(255,255,255,0.9)" }}>· {store.source}</T>
              </View>
            ) : (
              <T v="small" style={{ color: "rgba(255,255,255,0.9)" }}>
                {hasKey("grp.st." + g.status) ? t(("grp.st." + g.status) as MsgKey) : g.status}
                {g.closeNote ? ` · ${g.closeNote}` : ""}
              </T>
            )}
          </LinearGradient>
          <View style={[styles.stats, { borderTopColor: c.line }]}>
            <Stat label={t("grp.eachSaves")} value={bdt(g.totals.eachSavesBdt)} color={c.good} />
            <View style={[styles.vr, { backgroundColor: c.line }]} />
            <Stat
              label={t("grp.shipping")}
              value={bdt(g.totals.groupShippingBdt)}
              strike={g.totals.grouped && g.totals.soloShippingBdt !== g.totals.groupShippingBdt ? bdt(g.totals.soloShippingBdt) : undefined}
            />
            <View style={[styles.vr, { backgroundColor: c.line }]} />
            <Stat label={t("grp.membersTitle")} value={digits(g.members.length)} />
          </View>
        </Card>

        {open && need > 0 && (
          <View style={[styles.need, { backgroundColor: a.soft }]}>
            <Icon name="user-plus" size={16} color={a.ink} />
            <T v="small" style={{ flex: 1, color: a.ink, fontWeight: "700" }}>{t("grp.need", { count: need })}</T>
          </View>
        )}

        {open && (
          <Card style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
            <View style={[styles.link, { backgroundColor: c.surface2 }]}>
              <T v="tiny" numberOfLines={1} selectable style={{ color: c.ink2 }}>{g.shareUrl}</T>
            </View>
            <Button label={t("common.share")} icon="share-2" kind="soft" onPress={share} style={{ paddingHorizontal: 14 }} />
          </Card>
        )}

        <Card style={{ gap: 8 }}>
          <T v="h3" accessibilityRole="header">{t("grp.membersTitle")}</T>
          {g.members.map((m, i) => (
            <Animated.View key={i} entering={FadeInDown.delay(i * 40).duration(220)} style={[styles.member, { backgroundColor: c.surface2 }]}>
              <View style={[styles.avatar, { backgroundColor: a.accent }]}>
                <T v="small" tone="white" style={{ fontWeight: "800" }}>{m.name.slice(0, 1).toUpperCase()}</T>
              </View>
              <View style={{ flex: 1 }}>
                <T v="small" style={{ fontWeight: "800" }}>
                  {m.name}
                  {m.isYou ? t("grp.youSuffix") : ""}
                  {m.isOwner ? ` · ${t("grp.organiser")}` : ""}
                </T>
                <T v="tiny" tone="ink2">{t("grp.memberLine", { count: m.items, kg: digits(m.weightKg.toFixed(1)) })}</T>
              </View>
              {m.savingBdt > 0 && <T v="tiny" tone="good" style={{ fontWeight: "800" }}>{t("grp.saves", { amount: bdt(m.savingBdt) })}</T>}
            </Animated.View>
          ))}
        </Card>

        {g.you?.orderId && <Button label={t("grp.payOrder", { id: g.you.orderId })} icon="credit-card" onPress={() => router.push(`/order/${g.you!.orderId}`)} />}
        {g.you?.error && <T v="small" tone="bad">{t("grp.orderFailed", { error: g.you.error })}</T>}

        {open && (
          <Card style={{ gap: 10 }}>
            <T v="h3" accessibilityRole="header">{joined ? t("grp.yourItems") : t("grp.joinWithCart")}</T>
            <T v="small" tone="ink2">
              {mine.length
                ? t("grp.mineLine", { count: mine.length, store: store.name, amount: bdt(mine.reduce((s, i) => s + i.unitBdt * i.qty, 0)) })
                : t("grp.addItems", { store: store.name })}
              {joined && g.you && g.you.savingBdt > 0 ? t("grp.youSave", { amount: bdt(g.you.savingBdt) }) : ""}
            </T>
            {user ? (
              <Button
                label={busy === "join" ? t("common.saving") : joined ? t("grp.updateCart") : t("grp.join")}
                icon="users"
                mode={g.market}
                busy={busy === "join"}
                disabled={!!busy || !mine.length}
                onPress={() => run("join")}
              />
            ) : (
              <Button label={t("mx.grpSignInJoin")} icon="user" onPress={() => router.push({ pathname: "/login", params: { next: `/group/${code}` } })} />
            )}
            {!mine.length && <Button label={t("hero.shop", { store: store.name })} kind="ghost" onPress={() => router.push(`/store/${g.market}`)} />}
            {joined && !g.isOwner && <Button label={t("grp.leave")} kind="ghost" disabled={!!busy} busy={busy === "leave"} onPress={() => confirm("leave")} />}
            {g.isOwner && (
              <View style={{ gap: 8, paddingTop: 8, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: c.line }}>
                <Button label={t("grp.closeNow")} kind="ghost" icon="check-circle" disabled={!!busy} busy={busy === "close"} onPress={() => confirm("close")} />
                <Button label={t("grp.cancel")} kind="danger" icon="x" disabled={!!busy} busy={busy === "cancel"} onPress={() => confirm("cancel")} />
              </View>
            )}
            <T v="tiny" tone="ink2">{t("grp.howWorks")}</T>
          </Card>
        )}
      </ScrollView>
    </>
  );
}

function Stat({ label, value, color, strike }: { label: string; value: string; color?: string; strike?: string }) {
  const { c } = useTheme();
  return (
    <View style={{ flex: 1, alignItems: "center", paddingVertical: 12, gap: 2 }} accessible accessibilityLabel={`${label}: ${value}`}>
      <T v="label" tone="ink2" style={{ fontSize: 10 }}>{label}</T>
      <View style={{ flexDirection: "row", alignItems: "baseline", gap: 4 }}>
        {strike && <T v="tiny" tone="ink3" style={{ textDecorationLine: "line-through" }}>{strike}</T>}
        <T v="h3" style={{ color: color ?? c.ink, fontVariant: ["tabular-nums"] }}>{value}</T>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: { flexDirection: "row", alignItems: "center", gap: 6, alignSelf: "flex-start", backgroundColor: "rgba(255,255,255,0.2)", paddingHorizontal: 10, paddingVertical: 5, borderRadius: 999 },
  stats: { flexDirection: "row", borderTopWidth: StyleSheet.hairlineWidth },
  vr: { width: StyleSheet.hairlineWidth },
  need: { flexDirection: "row", alignItems: "center", gap: 8, padding: 12, borderRadius: radius.md },
  link: { flex: 1, minHeight: 44, borderRadius: radius.md, paddingHorizontal: 12, justifyContent: "center" },
  member: { flexDirection: "row", alignItems: "center", gap: 10, padding: 10, borderRadius: radius.md, minHeight: 56 },
  avatar: { width: 36, height: 36, borderRadius: 18, alignItems: "center", justifyContent: "center" },
});
