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
import { useT } from "@/i18n";

export default function Login() {
  const { t } = useT();
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
  const check = checkPhone(digits, t);

  useEffect(() => {
    if (resendIn <= 0) return;
    const timer = setTimeout(() => setResendIn((s) => s - 1), 1000);
    return () => clearTimeout(timer);
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
      if (r.demoCode) toast({ kind: "info", title: t("m.demoSignIn"), body: t("m.anyDigits", { code: r.demoCode }) });
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
      toast({ kind: "success", title: r.isNew ? t("nd.welcome") : t("m.signedIn") });
      if (next) router.replace(next as never);
      else router.back();
    } catch (e) {
      haptic.error();
      setErr((e as Error).message || t("m.codeBad"));
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
              <T v="h1" accessibilityRole="header">{t("so.signInPhone")}</T>
              <T v="body" tone="ink2">{t("m.textCode")}</T>
            </View>
            <View>
              <View style={[styles.phone, { backgroundColor: c.surface, borderColor: check.tone === "bad" ? c.bad : c.line }]}>
                <View style={[styles.cc, { backgroundColor: c.surface2 }]}>
                  <T v="h3">+880</T>
                </View>
                <TextInput
                  value={formatPhone(digits)}
                  onChangeText={(v) => setDigits(phoneDigits(v))}
                  placeholder="1712-345678"
                  placeholderTextColor={c.ink3}
                  keyboardType="phone-pad"
                  textContentType="telephoneNumber"
                  autoComplete="tel"
                  autoFocus
                  maxLength={11}
                  returnKeyType="next"
                  onSubmitEditing={sendCode}
                  accessibilityLabel={t("m.phoneAfter")}
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
              placeholder={t("m.namePh")}
              placeholderTextColor={c.ink3}
              textContentType="name"
              autoComplete="name"
              accessibilityLabel={t("qs.name")}
              style={[styles.field, { color: c.ink, backgroundColor: c.surface, borderColor: c.line }]}
            />
            {err && <T v="small" tone="bad">{err}</T>}
            <Button label={t("lg.send")} icon="send" onPress={sendCode} busy={busy} disabled={!check.valid} />
            {demo && (
              <T v="tiny" tone="ink3" style={{ textAlign: "center" }}>{t("m.demoOtp")}</T>
            )}
          </Animated.View>
        ) : (
          <Animated.View key="code" entering={FadeInRight} exiting={FadeOutLeft} style={{ gap: 18 }}>
            <View>
              <T v="h1" accessibilityRole="header">{t("m.enterCode")}</T>
              <T v="body" tone="ink2">
                {t("m.sentTo", { phone: digits ? formatPhone(digits) : "" })}
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
            <Button label={t("m.verifySignIn")} onPress={() => verify()} busy={busy} disabled={code.length !== 6} />
            <View style={{ flexDirection: "row", justifyContent: "space-between" }}>
              <Button label={t("m.changeNumber")} kind="ghost" onPress={() => setStep("phone")} style={{ minHeight: 44 }} />
              <Button label={resendIn > 0 ? t("m.resendIn", { n: resendIn }) : t("lg.resend")} kind="ghost" disabled={resendIn > 0} onPress={sendCode} style={{ minHeight: 44 }} />
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
