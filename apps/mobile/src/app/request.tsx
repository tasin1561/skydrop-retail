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

/** Request a product: paste a link or describe it (phone needed when signed out). */
export default function RequestScreen() {
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

  if (!on) return <Empty icon="search" title="Not available" text="Product requests are paused right now." />;
  if (sent) {
    return (
      <View style={{ flex: 1, padding: 16, justifyContent: "center" }}>
        <Empty icon="check-circle" title="Request sent" text="We'll look for it and send you a delivered price, usually within a day." action="My requests" onAction={() => router.replace("/requests")} />
      </View>
    );
  }

  async function submit() {
    if (mode === "link" && !/^https?:\/\/\S+\.\S+/.test(url.trim())) return toast({ kind: "error", title: "Paste the full link, starting with https://" });
    if (mode === "describe" && desc.trim().length < 3) return toast({ kind: "error", title: "Tell us what you are looking for" });
    if (!user && !/^(?:\+?88)?01[3-9]\d{8}$/.test(phone.replace(/[\s-]/g, ""))) return toast({ kind: "error", title: "Enter your mobile number" });
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
      toast({ kind: "error", title: "Couldn't send your request", body: e instanceof ApiError ? e.message : undefined });
    } finally {
      setBusy(false);
    }
  }

  const input = [styles.input, { color: c.ink, backgroundColor: c.surface, borderColor: c.line }];
  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === "ios" ? "padding" : undefined}>
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }} keyboardShouldPersistTaps="handled">
        <T v="body" tone="ink2">
          Can&apos;t find it? Paste a link from any Indian store or describe it, and we&apos;ll source it for you.
        </T>
        <View style={[styles.seg, { backgroundColor: c.surface2 }]}>
          {(["link", "describe"] as const).map((m) => (
            <Tap key={m} onPress={() => setMode(m)} accessibilityRole="tab" accessibilityState={{ selected: mode === m }} style={[styles.segBtn, mode === m && { backgroundColor: c.surface }]}>
              <T v="small" style={{ fontWeight: "800", color: mode === m ? c.ink : c.ink2 }}>
                {m === "link" ? "Paste a link" : "Describe it"}
              </T>
            </Tap>
          ))}
        </View>
        <Card style={{ gap: 12 }}>
          {mode === "link" && (
            <TextInput value={url} onChangeText={setUrl} placeholder="https://www.amazon.in/…" placeholderTextColor={c.ink3} autoCapitalize="none" keyboardType="url" accessibilityLabel="Product link" style={input} />
          )}
          <TextInput
            value={desc}
            onChangeText={setDesc}
            placeholder={mode === "link" ? "Anything else? Size, colour… (optional)" : "Brand, model, size, colour…"}
            placeholderTextColor={c.ink3}
            multiline
            accessibilityLabel="Description"
            style={[...input, { minHeight: 88, textAlignVertical: "top", paddingTop: 12 }]}
          />
          <View style={{ flexDirection: "row", gap: 10, alignItems: "center" }}>
            <T v="small" style={{ fontWeight: "700", flex: 1 }}>
              Quantity
            </T>
            <Tap onPress={() => setQty((x) => Math.max(1, x - 1))} accessibilityLabel="Fewer" style={[styles.step, { backgroundColor: c.surface2 }]}>
              <T v="h3">−</T>
            </Tap>
            <T v="h3">{qty}</T>
            <Tap onPress={() => setQty((x) => Math.min(100, x + 1))} accessibilityLabel="More" style={[styles.step, { backgroundColor: c.surface2 }]}>
              <T v="h3">+</T>
            </Tap>
          </View>
          <TextInput value={budget} onChangeText={(t) => setBudget(t.replace(/\D/g, ""))} placeholder="Budget in ৳ (optional)" placeholderTextColor={c.ink3} keyboardType="number-pad" accessibilityLabel="Budget" style={input} />
          {!user && (
            <TextInput value={phone} onChangeText={setPhone} placeholder="Mobile number 01XXXXXXXXX" placeholderTextColor={c.ink3} keyboardType="phone-pad" accessibilityLabel="Mobile number" style={input} />
          )}
        </Card>
        <Button label="Send request" icon="send" onPress={submit} busy={busy} />
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
