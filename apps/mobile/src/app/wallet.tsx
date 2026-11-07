import { useCallback, useState } from "react";
import { RefreshControl, ScrollView, StyleSheet, TextInput, View } from "react-native";
import { router, useFocusEffect } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { LinearGradient } from "expo-linear-gradient";
import Animated, { FadeInDown } from "react-native-reanimated";
import { ApiError, isDemo } from "@/api/client";
import { formatGiftCode, moneyApi, type Wallet } from "@/api/money";
import { useStore } from "@/state/store";
import { useFeature } from "@/lib/features";
import { BRAND, useTheme } from "@/theme";
import { Button, Card, Divider, Empty, IconChip, Skeleton, T } from "@/components/ui";
import { useT } from "@/i18n";
import { hasKey, type MsgKey } from "@/i18n";

// Skydrop Wallet: balance, redeem a gift card, history.
export default function WalletScreen() {
  const { t, bdt, date } = useT();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { user } = useStore();
  const on = useFeature("wallet");
  const giftOn = useFeature("gift_cards");
  const [w, setW] = useState<Wallet | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  const [code, setCode] = useState("");
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);

  const load = useCallback(async () => {
    try {
      setW(await moneyApi.wallet());
      setError(null);
    } catch (e) {
      setError((e as Error).message);
    }
  }, []);
  useFocusEffect(
    useCallback(() => {
      if (user && on) load();
    }, [user, on, load]),
  );

  if (!on) return <Empty icon="credit-card" title={t("m.walletUnavail")} text={t("wv.off")} />;
  if (!user)
    return <Empty icon="credit-card" title={t("wv.signIn")} text={t("m.walletSignInB")} action={t("nav.signIn")} onAction={() => router.push({ pathname: "/login", params: { next: "/wallet" } })} />;
  if (error && !w) return <Empty icon="wifi-off" title={t("m.walletLoadFail")} text={error} action={t("maint.retry")} onAction={load} />;

  const redeem = async () => {
    if (code.replace(/-/g, "").length !== 16 || busy) return;
    if (isDemo()) {
      setMsg({ ok: false, text: t("m.giftNotConnected") });
      return;
    }
    setBusy(true);
    try {
      const r = await moneyApi.redeem(code);
      setMsg({ ok: true, text: `${t("rg.added", { amount: bdt(r.amountBdt) })}. ${t("rg.newBalance", { amount: bdt(r.balanceBdt) })}.` });
      setCode("");
      load();
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : t("rg.failed") });
    } finally {
      setBusy(false);
    }
  };

  return (
    <ScrollView
      keyboardShouldPersistTaps="handled"
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
      <LinearGradient colors={["#0b0b0f", BRAND]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.hero}>
        <T v="small" style={{ color: "rgba(255,255,255,0.85)", fontWeight: "700" }}>{t("wallet.name")}</T>
        {w ? (
          <T style={{ color: "#fff", fontSize: 42, lineHeight: 48, fontWeight: "900", fontVariant: ["tabular-nums"] }}>{bdt(w.balanceBdt)}</T>
        ) : (
          <Skeleton w={160} h={44} style={{ opacity: 0.4 }} />
        )}
        <T v="small" style={{ color: "rgba(255,255,255,0.85)" }}>{t("m.useAtCheckout")}</T>
      </LinearGradient>

      {giftOn && (
        <Card style={{ gap: 10 }}>
          <T v="h3">{t("rg.label")}</T>
          <TextInput
            value={code}
            onChangeText={(v) => {
              setCode(formatGiftCode(v));
              setMsg(null);
            }}
            placeholder="XXXX-XXXX-XXXX-XXXX"
            placeholderTextColor={c.ink3}
            autoCapitalize="characters"
            autoCorrect={false}
            accessibilityLabel={t("m.giftCode")}
            style={[styles.input, { color: c.ink, borderColor: c.line, backgroundColor: c.surface }]}
          />
          {msg && (
            <T v="small" tone={msg.ok ? "good" : "bad"} accessibilityLiveRegion="polite">
              {msg.text}
            </T>
          )}
          <Button label={t("rg.redeem")} icon="check" busy={busy} disabled={code.replace(/-/g, "").length !== 16} onPress={redeem} />
        </Card>
      )}

      <Card pad={6}>
        <T v="h3" style={{ padding: 10 }}>{t("wv.history")}</T>
        {!w ? (
          <Skeleton h={56} style={{ margin: 10 }} />
        ) : w.history.length === 0 ? (
          <T v="small" tone="ink2" style={{ padding: 10 }}>{t("m.noWallet")}</T>
        ) : (
          w.history.map((h, i) => (
            <Animated.View key={h.id} entering={FadeInDown.delay(Math.min(i, 8) * 30)}>
              {i > 0 && <Divider style={{ marginLeft: 60 }} />}
              <View style={styles.row}>
                <IconChip icon={h.type === "credit" ? "plus" : "tag"} color={h.type === "credit" ? c.good : c.brand} />
                <View style={{ flex: 1 }}>
                  <T style={{ fontWeight: "600" }} numberOfLines={1}>
                    {h.note || (hasKey(`wv.${h.reason}`) ? t(`wv.${h.reason}` as MsgKey) : h.reason)}
                  </T>
                  <T v="tiny" tone="ink3">
                    {date(h.at, { day: "numeric", month: "short", year: "numeric" })}
                    {h.orderId ? ` · ${h.orderId}` : ""}
                  </T>
                </View>
                <T style={{ fontWeight: "800", fontVariant: ["tabular-nums"] }} tone={h.type === "credit" ? "good" : "ink"}>
                  {h.type === "credit" ? "+" : "−"}
                  {bdt(h.amountBdt)}
                </T>
              </View>
            </Animated.View>
          ))
        )}
      </Card>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  hero: { borderRadius: 20, padding: 20, gap: 4, overflow: "hidden" },
  input: { minHeight: 48, borderWidth: 1, borderRadius: 14, paddingHorizontal: 14, fontSize: 16, letterSpacing: 1.5, fontVariant: ["tabular-nums"] },
  row: { flexDirection: "row", alignItems: "center", gap: 12, padding: 10, minHeight: 56 },
});
