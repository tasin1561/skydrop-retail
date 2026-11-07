import { useState } from "react";
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, TextInput, View } from "react-native";
import { router } from "expo-router";
import { api } from "@/api/client";
import { useStore } from "@/state/store";
import { prettyPhone } from "@/lib/phone";
import { radius, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Button, Card, T } from "@/components/ui";
import { useToast } from "@/components/Toast";

export default function Profile() {
  const { c } = useTheme();
  const { user, setUser } = useStore();
  const toast = useToast();
  const [name, setName] = useState(user?.name ?? "");
  const [email, setEmail] = useState(user?.email ?? "");
  const [busy, setBusy] = useState(false);
  const emailOk = !email || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

  async function save() {
    setBusy(true);
    try {
      const u = await api.updateMe({ name: name.trim(), email: email.trim() || undefined });
      setUser({ ...user!, ...u });
      haptic.success();
      toast({ kind: "success", title: "Profile saved" });
      router.back();
    } catch (e) {
      toast({ kind: "error", title: "Couldn't save", body: (e as Error).message });
    } finally {
      setBusy(false);
    }
  }

  const input = { color: c.ink, backgroundColor: c.surface, borderColor: c.line };
  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === "ios" ? "padding" : undefined}>
      <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }} keyboardShouldPersistTaps="handled">
        <Card style={{ gap: 14 }}>
          <View style={{ gap: 6 }}>
            <T v="small" style={{ fontWeight: "700" }}>
              Mobile number
            </T>
            <T v="body" tone="ink2">
              {user ? prettyPhone(user.phone) : "-"}
            </T>
          </View>
          <View style={{ gap: 6 }}>
            <T v="small" style={{ fontWeight: "700" }}>
              Name
            </T>
            <TextInput value={name} onChangeText={setName} accessibilityLabel="Name" autoComplete="name" style={[styles.input, input]} />
          </View>
          <View style={{ gap: 6 }}>
            <T v="small" style={{ fontWeight: "700" }}>
              Email (optional)
            </T>
            <TextInput
              value={email}
              onChangeText={setEmail}
              accessibilityLabel="Email"
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
              style={[styles.input, input, !emailOk && { borderColor: c.bad }]}
            />
          </View>
          <Button label="Save" onPress={save} busy={busy} disabled={!name.trim() || !emailOk} />
        </Card>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  input: { minHeight: 52, borderWidth: 1.5, borderRadius: radius.md, paddingHorizontal: 14, fontSize: 16 },
});
