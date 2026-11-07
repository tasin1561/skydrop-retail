import { useEffect, useState } from "react";
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, TextInput, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Animated, { FadeInRight, FadeOutLeft } from "react-native-reanimated";
import { api } from "@/api/client";
import { useStore } from "@/state/store";
import { checkPhone, formatPhone, phoneDigits, toE164 } from "@/lib/phone";
import { radius, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Button, IconChip, T } from "@/components/ui";
import { OtpInput } from "@/components/OtpInput";
import { useToast } from "@/components/Toast";

export default function Login() {
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { next } = useLocalSearchParams<{ next?: string }>();
  const { signIn, demo } = useStore();
  const toast = useToast();
  const [step, setStep] = useState<"phone" | "code">("phone");
  const [digits, setDigits] = useState("");
  const [name, setName] = useState("");
  const [code, setCode] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [resendIn, setResendIn] = useState(0);
  const check = checkPhone(digits);

  useEffect(() => {
    if (resendIn <= 0) return;
    const t = setTimeout(() => setResendIn((s) => s - 1), 1000);
    return () => clearTimeout(t);
  }, [resendIn]);

  async function sendCode() {
    if (!check.valid) return;
    setBusy(true);
    setErr(null);
    try {
      const r = await api.otpRequest(toE164(digits));
      haptic.success();
      setStep("code");
      setResendIn(30);
      if (r.demoCode) toast({ kind: "info", title: "Demo sign-in", body: `Any 6 digits work (e.g. ${r.demoCode}).` });
    } catch (e) {
      haptic.error();
      setErr((e as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function verify(v = code) {
    if (v.length !== 6 || busy) return;
    setBusy(true);
    setErr(null);
    try {
      const r = await api.otpVerify(toE164(digits), v, name.trim() || undefined);
      haptic.success();
      signIn(r.user);
      toast({ kind: "success", title: r.isNew ? "Welcome to Skydrop Retail" : "Signed in" });
      if (next) router.replace(next as never);
      else router.back();
    } catch (e) {
      haptic.error();
      setErr((e as Error).message || "That code didn't work");
      setCode("");
    } finally {
      setBusy(false);
    }
  }

  const tone = check.tone === "bad" ? c.bad : check.tone === "good" ? c.good : check.tone === "warn" ? c.warn : c.ink3;

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.bg }} behavior={Platform.OS === "ios" ? "padding" : undefined} keyboardVerticalOffset={Platform.OS === "ios" ? 64 : 0}>
      <ScrollView contentContainerStyle={{ padding: 20, paddingBottom: insets.bottom + 24, gap: 18 }} keyboardShouldPersistTaps="handled">
        <IconChip icon={step === "phone" ? "smartphone" : "shield"} size={56} />
        {step === "phone" ? (
          <Animated.View key="phone" entering={FadeInRight} exiting={FadeOutLeft} style={{ gap: 16 }}>
            <View>
              <T v="h1" accessibilityRole="header">
                Sign in with your phone
              </T>
              <T v="body" tone="ink2">
                We'll text a 6-digit code to your Bangladesh mobile number.
              </T>
            </View>
            <View>
              <View style={[styles.phone, { backgroundColor: c.surface, borderColor: check.tone === "bad" ? c.bad : c.line }]}>
                <View style={[styles.cc, { backgroundColor: c.surface2 }]}>
                  <T v="h3">+880</T>
                </View>
                <TextInput
                  value={formatPhone(digits)}
                  onChangeText={(t) => setDigits(phoneDigits(t))}
                  placeholder="1712-345678"
                  placeholderTextColor={c.ink3}
                  keyboardType="phone-pad"
                  textContentType="telephoneNumber"
                  autoComplete="tel"
                  autoFocus
                  maxLength={11}
                  returnKeyType="next"
                  onSubmitEditing={sendCode}
                  accessibilityLabel="Mobile number after +880"
                  style={[styles.phoneInput, { color: c.ink }]}
                />
              </View>
              <T v="small" style={{ color: tone, marginTop: 6 }} accessibilityLiveRegion="polite">
                {check.message}
              </T>
            </View>
            <TextInput
              value={name}
              onChangeText={setName}
              placeholder="Your name (for new accounts)"
              placeholderTextColor={c.ink3}
              textContentType="name"
              autoComplete="name"
              accessibilityLabel="Your name"
              style={[styles.field, { color: c.ink, backgroundColor: c.surface, borderColor: c.line }]}
            />
            {err && <T v="small" tone="bad">{err}</T>}
            <Button label="Send code" icon="send" onPress={sendCode} busy={busy} disabled={!check.valid} />
            {demo && (
              <T v="tiny" tone="ink3" style={{ textAlign: "center" }}>
                Demo mode: no SMS is sent. Any 6-digit code signs you in on this device.
              </T>
            )}
          </Animated.View>
        ) : (
          <Animated.View key="code" entering={FadeInRight} exiting={FadeOutLeft} style={{ gap: 18 }}>
            <View>
              <T v="h1" accessibilityRole="header">
                Enter the code
              </T>
              <T v="body" tone="ink2">
                Sent to +880 {formatPhone(digits)}
              </T>
            </View>
            <OtpInput
              value={code}
              error={!!err}
              onChange={(v) => {
                setCode(v);
                setErr(null);
                if (v.length === 6) verify(v);
              }}
            />
            {err && (
              <T v="small" tone="bad" style={{ textAlign: "center" }}>
                {err}
              </T>
            )}
            <Button label="Verify and sign in" onPress={() => verify()} busy={busy} disabled={code.length !== 6} />
            <View style={{ flexDirection: "row", justifyContent: "space-between" }}>
              <Button label="Change number" kind="ghost" onPress={() => setStep("phone")} style={{ minHeight: 44 }} />
              <Button label={resendIn > 0 ? `Resend in ${resendIn}s` : "Resend code"} kind="ghost" disabled={resendIn > 0} onPress={sendCode} style={{ minHeight: 44 }} />
            </View>
          </Animated.View>
        )}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  phone: { flexDirection: "row", alignItems: "center", borderWidth: 1.5, borderRadius: radius.md, padding: 4, gap: 8 },
  cc: { paddingHorizontal: 12, minHeight: 48, justifyContent: "center", borderRadius: 12 },
  phoneInput: { flex: 1, fontSize: 20, fontWeight: "700", letterSpacing: 1, minHeight: 48 },
  field: { minHeight: 52, borderWidth: 1.5, borderRadius: radius.md, paddingHorizontal: 14, fontSize: 16 },
});
