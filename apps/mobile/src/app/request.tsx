import { useState } from "react";
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, TextInput, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { ApiError } from "@/api/client";
import { Button, Card, Empty, T, Tap } from "@/components/ui";
import { useToast } from "@/components/Toast";
import { useFeature } from "@/lib/features";
import { haptic } from "@/lib/haptics";
import { shopping } from "@/lib/shopping";
import { useStore } from "@/state/store";
import { radius, useTheme } from "@/theme";
import { useT } from "@/i18n";

/** Request a product: paste a link or describe it (phone needed when signed out). */
export default function RequestScreen() {
  const { t } = useT();
  const on = useFeature("product_requests");
  const { q, url: initialUrl } = useLocalSearchParams<{ q?: string; url?: string }>();
  const { c } = useTheme();
  const { user } = useStore();
  const toast = useToast();
  const [mode, setMode] = useState<"link" | "describe">(initialUrl || !q ? "link" : "describe");
  const [url, setUrl] = useState(initialUrl ?? "");
  const [desc, setDesc] = useState(q ?? "");
  const [qty, setQty] = useState(1);
  const [budget, setBudget] = useState("");
  const [phone, setPhone] = useState("");
  const [busy, setBusy] = useState(false);
  const [sent, setSent] = useState(false);

  if (!on) return <Empty icon="search" title={t("rq.notAvail")} text={t("m.reqPaused")} />;
  if (sent) {
    return (
      <View style={{ flex: 1, padding: 16, justifyContent: "center" }}>
        <Empty icon="check-circle" title={t("rf.sent")} text={t("m.reqSentB")} action={t("m.myRequests")} onAction={() => router.replace("/requests")} />
      </View>
    );
  }

  async function submit() {
    if (mode === "link" && !/^https?:\/\/\S+\.\S+/.test(url.trim())) return toast({ kind: "error", title: t("rf.errUrl") });
    if (mode === "describe" && desc.trim().length < 3) return toast({ kind: "error", title: t("m.reqWhat") });
    if (!user && !/^(?:\+?88)?01[3-9]\d{8}$/.test(phone.replace(/[\s-]/g, ""))) return toast({ kind: "error", title: t("ui.enterPhone") });
    setBusy(true);
    try {
      await shopping.createRequest({
        url: mode === "link" ? url.trim() : undefined,
        description: desc.trim() || undefined,
        qty,
        budgetBdt: Number(budget) || undefined,
        phone: user ? undefined : phone.trim(),
        source: "app",
      });
      haptic.success();
      setSent(true);
    } catch (e) {
      toast({ kind: "error", title: t("rw.sendFailed"), body: e instanceof ApiError ? e.message : undefined });
    } finally {
      setBusy(false);
    }
  }

  const input = [styles.input, { color: c.ink, backgroundColor: c.surface, borderColor: c.line }];
  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === "ios" ? "padding" : undefined}>
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }} keyboardShouldPersistTaps="handled">
        <T v="body" tone="ink2">
          {t("m.reqIntro")}
        </T>
        <View style={[styles.seg, { backgroundColor: c.surface2 }]}>
          {(["link", "describe"] as const).map((m) => (
            <Tap key={m} onPress={() => setMode(m)} accessibilityRole="tab" accessibilityState={{ selected: mode === m }} style={[styles.segBtn, mode === m && { backgroundColor: c.surface }]}>
              <T v="small" style={{ fontWeight: "800", color: mode === m ? c.ink : c.ink2 }}>
                {m === "link" ? t("rf.paste") : t("rf.describe")}
              </T>
            </Tap>
          ))}
        </View>
        <Card style={{ gap: 12 }}>
          {mode === "link" && (
            <TextInput value={url} onChangeText={setUrl} placeholder="https://www.amazon.in/…" placeholderTextColor={c.ink3} autoCapitalize="none" keyboardType="url" accessibilityLabel={t("rf.link")} style={input} />
          )}
          <TextInput
            value={desc}
            onChangeText={setDesc}
            placeholder={mode === "link" ? t("m.reqExtraPh") : t("rf.descPh")}
            placeholderTextColor={c.ink3}
            multiline
            accessibilityLabel={t("m.description")}
            style={[...input, { minHeight: 88, textAlignVertical: "top", paddingTop: 12 }]}
          />
          <View style={{ flexDirection: "row", gap: 10, alignItems: "center" }}>
            <T v="small" style={{ fontWeight: "700", flex: 1 }}>{t("buy.qty")}</T>
            <Tap onPress={() => setQty((x) => Math.max(1, x - 1))} accessibilityLabel={t("common.fewer")} style={[styles.step, { backgroundColor: c.surface2 }]}>
              <T v="h3">−</T>
            </Tap>
            <T v="h3">{qty}</T>
            <Tap onPress={() => setQty((x) => Math.min(100, x + 1))} accessibilityLabel={t("common.more")} style={[styles.step, { backgroundColor: c.surface2 }]}>
              <T v="h3">+</T>
            </Tap>
          </View>
          <TextInput value={budget} onChangeText={(v) => setBudget(v.replace(/\D/g, ""))} placeholder={t("m.budgetPh")} placeholderTextColor={c.ink3} keyboardType="number-pad" accessibilityLabel={t("m.budget")} style={input} />
          {!user && (
            <TextInput value={phone} onChangeText={setPhone} placeholder={t("m.phonePh")} placeholderTextColor={c.ink3} keyboardType="phone-pad" accessibilityLabel={t("rf.mobile")} style={input} />
          )}
        </Card>
        <Button label={t("rf.send")} icon="send" onPress={submit} busy={busy} />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  input: { minHeight: 48, borderWidth: 1, borderRadius: radius.sm, paddingHorizontal: 14, fontSize: 15 },
  seg: { flexDirection: "row", padding: 4, borderRadius: 999 },
  segBtn: { flex: 1, minHeight: 44, alignItems: "center", justifyContent: "center", borderRadius: 999 },
  step: { width: 44, height: 44, borderRadius: 22, alignItems: "center", justifyContent: "center" },
});
