// Group buy: start a group for one store from your cart, open one by code, and see your groups.
// Mirrors apps/web/src/components/shopping/GroupStart.tsx (feature "group_orders").
import { useCallback, useMemo, useState } from "react";
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, TextInput, View } from "react-native";
import { router, useFocusEffect, useLocalSearchParams } from "expo-router";
import { api, ApiError } from "@/api/client";
import { useStore } from "@/state/store";
import { useFeature } from "@/lib/features";
import { MARKET_IDS, STORES, isMarket } from "@/lib/markets";
import { groupSavingPreview, parseGroupCode, shoppingMore, type Group } from "@/lib/shopping";
import { sized } from "@/lib/variants";
import type { MarketId } from "@/lib/types";
import { haptic } from "@/lib/haptics";
import { radius, useAccent, useTheme, withAlpha } from "@/theme";
import { Button, Card, Empty, ProductImage, T, Tap } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { hasKey, useT, type MsgKey } from "@/i18n";
import { StoreName } from "@/components/StoreName";

const HOURS = [24, 48, 72] as const;

export default function GroupStartScreen() {
  const { market: qm } = useLocalSearchParams<{ market?: string }>();
  const on = useFeature("group_orders");
  const { t, bdt, digits } = useT();
  const { c } = useTheme();
  const toast = useToast();
  const { cart, user, ready, demo } = useStore();
  const counts = useMemo(() => Object.fromEntries(MARKET_IDS.map((m) => [m, cart.filter((i) => i.market === m).length])) as Record<MarketId, number>, [cart]);
  const best = [...MARKET_IDS].sort((a, b) => counts[b] - counts[a])[0];
  const [market, setMarket] = useState<MarketId | null>(qm && isMarket(qm) ? qm : null);
  const m = market ?? best;
  const a = useAccent(m);
  const items = cart.filter((i) => i.market === m);
  const [hours, setHours] = useState<(typeof HOURS)[number]>(48);
  const [busy, setBusy] = useState(false);
  const [mine, setMine] = useState<Group[] | null>(null);
  const [code, setCode] = useState("");

  useFocusEffect(
    useCallback(() => {
      if (!ready || !user || !on) return;
      let live = true;
      shoppingMore.groups
        .mine()
        .then((g) => live && setMine(g))
        .catch(() => live && setMine([]));
      return () => {
        live = false;
      };
    }, [ready, user, on]),
  );

  if (!on) return <Empty icon="users" title={t("na.title", { title: t("grp.groupBuy") })} text={t("na.later")} action={t("common.backHome")} onAction={() => router.navigate("/")} />;
  if (demo) return <Empty icon="users" title={t("grp.groupBuy")} text={t("grp.demo")} action={t("common.backHome")} onAction={() => router.navigate("/")} />;
  if (!user)
    return (
      <Empty
        icon="users"
        title={t("mx.grpSignIn")}
        text={t("grp.everyonePays")}
        action={t("nav.signIn")}
        onAction={() => router.push({ pathname: "/login", params: { next: `/group?market=${m}` } })}
      />
    );

  async function start() {
    setBusy(true);
    try {
      const list = await api.addresses().catch(() => []);
      const addr = list.find((x) => x.isDefault) ?? list[0];
      const g = await shoppingMore.groups.create(m, hours, {
        items: items.map((i) => ({ productId: i.productId, variant: i.variant ?? null, qty: i.qty })),
        ...(addr && /^\d+$/.test(addr.id) ? { addressId: Number(addr.id) } : {}),
        payment: "bkash",
      });
      haptic.success();
      router.replace(`/group/${g.code}`);
    } catch (e) {
      haptic.error();
      toast({ kind: "error", title: t("grp.startFailed"), body: e instanceof ApiError ? e.message : t("common.pleaseRetry") });
      setBusy(false);
    }
  }

  const preview = groupSavingPreview(items);
  const openCode = () => {
    const c2 = parseGroupCode(code);
    if (c2.length < 4) return;
    haptic.tap();
    router.push(`/group/${c2}`);
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.bg }} behavior={Platform.OS === "ios" ? "padding" : undefined} keyboardVerticalOffset={Platform.OS === "ios" ? 100 : 0}>
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40, gap: 14 }} keyboardShouldPersistTaps="handled">
        <T v="small" tone="ink2">{t("grp.share")}</T>
        <Card style={{ gap: 12 }}>
          <T v="h3" accessibilityRole="header">{t("grp.step1")}</T>
          <View style={{ flexDirection: "row", gap: 8 }} accessibilityRole="radiogroup">
            {MARKET_IDS.map((id) => {
              const sel = m === id;
              return (
                <Tap
                  key={id}
                  onPress={() => {
                    haptic.tap();
                    setMarket(id);
                  }}
                  accessibilityRole="radio"
                  accessibilityState={{ checked: sel }}
                  accessibilityLabel={`${STORES[id].name}, ${t("grp.inCart", { count: counts[id] })}`}
                  style={[styles.store, { borderColor: sel ? a.accent : c.line, backgroundColor: sel ? a.soft : c.surface }]}
                >
                  <StoreName mode={id} size={14} style={{ alignSelf: "center" }} />
                  <T v="tiny" tone="ink3">{t("grp.inCart", { count: counts[id] })}</T>
                </Tap>
              );
            })}
          </View>

          <T v="h3" accessibilityRole="header" style={{ marginTop: 6 }}>{t("grp.step2")}</T>
          <View style={[styles.seg, { backgroundColor: c.surface2 }]} accessibilityRole="radiogroup" accessibilityLabel={t("grp.deadline")}>
            {HOURS.map((h) => {
              const sel = h === hours;
              return (
                <Tap
                  key={h}
                  onPress={() => {
                    haptic.tap();
                    setHours(h);
                  }}
                  accessibilityRole="radio"
                  accessibilityState={{ checked: sel }}
                  style={[styles.segItem, sel && { backgroundColor: c.surface }]}
                >
                  <T v="small" style={{ fontWeight: "700", color: sel ? c.ink : c.ink3 }}>{t("grp.hours", { count: h })}</T>
                </Tap>
              );
            })}
          </View>

          <T v="h3" accessibilityRole="header" style={{ marginTop: 6 }}>{t("grp.step3")}</T>
          {items.length ? (
            items.map((i) => (
              <View key={i.productId + (i.variant ?? "")} style={[styles.item, { backgroundColor: c.surface2 }]}>
                <ProductImage uri={sized(i.image, "SL160")} placeholder={i.placeholder} size={44} style={{ borderRadius: 10 }} />
                <T v="small" numberOfLines={2} style={{ flex: 1 }}>{i.title}</T>
                <T v="small" tone="ink2" style={{ fontWeight: "700" }}>×{digits(i.qty)}</T>
              </View>
            ))
          ) : (
            <View style={{ gap: 8 }}>
              <T v="small" tone="ink2">{t("grp.addItems", { store: STORES[m].name })}</T>
              <Button label={t("grp.shopStore", { store: STORES[m].name })} kind="ghost" mode={m} onPress={() => router.push(`/store/${m}`)} />
            </View>
          )}

          {preview > 0 && (
            <View style={[styles.note, { backgroundColor: withAlpha(c.good, 0.12) }]}>
              <T v="small" tone="good" style={{ fontWeight: "700" }}>{t("grp.preview", { amount: bdt(preview) })}</T>
            </View>
          )}
          <Button label={busy ? t("grp.starting") : t("grp.start")} icon="users" mode={m} busy={busy} onPress={start} />
          <T v="tiny" tone="ink2" style={{ textAlign: "center" }}>{t("grp.everyonePays")}</T>
        </Card>

        <Card style={{ gap: 10 }}>
          <T v="h3">{t("mx.grpHaveCode")}</T>
          <View style={{ flexDirection: "row", gap: 8 }}>
            <TextInput
              value={code}
              onChangeText={(v) => setCode(v.toUpperCase())}
              placeholder={t("mx.grpCodePh")}
              placeholderTextColor={c.ink3}
              autoCapitalize="characters"
              autoCorrect={false}
              returnKeyType="go"
              onSubmitEditing={openCode}
              accessibilityLabel={t("mx.grpCodePh")}
              style={[styles.input, { color: c.ink, borderColor: c.line, backgroundColor: c.surface2 }]}
            />
            <Button label={t("mx.grpOpen")} kind="soft" onPress={openCode} disabled={parseGroupCode(code).length < 4} style={{ paddingHorizontal: 18 }} />
          </View>
        </Card>

        {mine && mine.length > 0 && (
          <View style={{ gap: 8 }}>
            <T v="h2" accessibilityRole="header">{t("grp.yours")}</T>
            {mine.map((g) => (
              <Tap key={g.code} onPress={() => router.push(`/group/${g.code}`)} accessibilityLabel={`${STORES[g.market].name}, ${t("grp.members", { count: g.members.length })}`} scaleTo={0.98}>
                <Card style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
                  <View style={{ flex: 1 }}>
                    <StoreName mode={g.market} size={16} />
                    <T v="tiny" tone="ink2">{t("grp.members", { count: g.members.length })} · {g.code}</T>
                  </View>
                  <View style={[styles.status, { backgroundColor: c.surface2 }]}>
                    <T v="tiny" style={{ fontWeight: "800", color: c.ink2 }}>{hasKey("grp.st." + g.status) ? t(("grp.st." + g.status) as MsgKey) : g.status}</T>
                  </View>
                  <Icon name="chevron-right" size={18} color={c.ink3} />
                </Card>
              </Tap>
            ))}
          </View>
        )}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  store: { flex: 1, minHeight: 72, borderRadius: radius.md, borderWidth: 1.5, alignItems: "center", justifyContent: "center", gap: 3, paddingHorizontal: 4 },
  seg: { flexDirection: "row", borderRadius: 14, padding: 4 },
  segItem: { flex: 1, minHeight: 44, borderRadius: 11, alignItems: "center", justifyContent: "center" },
  item: { flexDirection: "row", alignItems: "center", gap: 10, padding: 8, borderRadius: radius.md },
  note: { padding: 12, borderRadius: radius.md },
  input: { flex: 1, minWidth: 0, minHeight: 48, borderRadius: radius.md, borderWidth: 1, paddingHorizontal: 14, fontSize: 16, fontWeight: "700", letterSpacing: 1 },
  status: { paddingHorizontal: 8, paddingVertical: 4, borderRadius: 999 },
});
