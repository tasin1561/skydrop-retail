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
import { formatBdt } from "@/lib/pricing";
import { BRAND, useTheme } from "@/theme";
import { Button, Card, Divider, Empty, IconChip, Skeleton, T } from "@/components/ui";

// Skydrop Wallet: balance, redeem a gift card, history.
export default function WalletScreen() {
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

  if (!on) return <Empty icon="credit-card" title="Wallet unavailable" text="The Skydrop Wallet is not available right now." />;
  if (!user)
    return <Empty icon="credit-card" title="Sign in to see your wallet" text="Refunds and gift cards land in your Skydrop Wallet." action="Sign in" onAction={() => router.push({ pathname: "/login", params: { next: "/wallet" } })} />;
  if (error && !w) return <Empty icon="wifi-off" title="Couldn't load your wallet" text={error} action="Try again" onAction={load} />;

  const redeem = async () => {
    if (code.replace(/-/g, "").length !== 16 || busy) return;
    if (isDemo()) {
      setMsg({ ok: false, text: "Gift cards can be redeemed once the app is connected to the store." });
      return;
    }
    setBusy(true);
    try {
      const r = await moneyApi.redeem(code);
      setMsg({ ok: true, text: `${formatBdt(r.amountBdt)} added. New balance ${formatBdt(r.balanceBdt)}.` });
      setCode("");
      load();
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : "Could not redeem the card." });
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
      <LinearGradient colors={[BRAND, "#38bdf8"]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.hero}>
        <T v="small" style={{ color: "rgba(255,255,255,0.85)", fontWeight: "700" }}>
          Skydrop Wallet
        </T>
        {w ? (
          <T style={{ color: "#fff", fontSize: 42, lineHeight: 48, fontWeight: "900", fontVariant: ["tabular-nums"] }}>{formatBdt(w.balanceBdt)}</T>
        ) : (
          <Skeleton w={160} h={44} style={{ opacity: 0.4 }} />
        )}
        <T v="small" style={{ color: "rgba(255,255,255,0.85)" }}>
          Use it at checkout for part or all of an order.
        </T>
      </LinearGradient>

      {giftOn && (
        <Card style={{ gap: 10 }}>
          <T v="h3">Redeem a gift card</T>
          <TextInput
            value={code}
            onChangeText={(t) => {
              setCode(formatGiftCode(t));
              setMsg(null);
            }}
            placeholder="XXXX-XXXX-XXXX-XXXX"
            placeholderTextColor={c.ink3}
            autoCapitalize="characters"
            autoCorrect={false}
            accessibilityLabel="Gift card code"
            style={[styles.input, { color: c.ink, borderColor: c.line, backgroundColor: c.surface }]}
          />
          {msg && (
            <T v="small" tone={msg.ok ? "good" : "bad"} accessibilityLiveRegion="polite">
              {msg.text}
            </T>
          )}
          <Button label="Redeem" icon="check" busy={busy} disabled={code.replace(/-/g, "").length !== 16} onPress={redeem} />
        </Card>
      )}

      <Card pad={6}>
        <T v="h3" style={{ padding: 10 }}>
          History
        </T>
        {!w ? (
          <Skeleton h={56} style={{ margin: 10 }} />
        ) : w.history.length === 0 ? (
          <T v="small" tone="ink2" style={{ padding: 10 }}>
            No wallet activity yet.
          </T>
        ) : (
          w.history.map((h, i) => (
            <Animated.View key={h.id} entering={FadeInDown.delay(Math.min(i, 8) * 30)}>
              {i > 0 && <Divider style={{ marginLeft: 60 }} />}
              <View style={styles.row}>
                <IconChip icon={h.type === "credit" ? "plus" : "tag"} color={h.type === "credit" ? c.good : c.brand} />
                <View style={{ flex: 1 }}>
                  <T style={{ fontWeight: "600" }} numberOfLines={1}>
                    {h.note || h.reason}
                  </T>
                  <T v="tiny" tone="ink3">
                    {new Date(h.at).toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" })}
                    {h.orderId ? ` · ${h.orderId}` : ""}
                  </T>
                </View>
                <T style={{ fontWeight: "800", fontVariant: ["tabular-nums"] }} tone={h.type === "credit" ? "good" : "ink"}>
                  {h.type === "credit" ? "+" : "−"}
                  {formatBdt(h.amountBdt)}
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
