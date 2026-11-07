// Paid unboxing review (feature "unboxing"): hub photos / video per item, then approve or report a problem.
// Mirrors apps/web/src/components/fulfilment/UnboxingReview.tsx. Deep link from push: /orders/{id}/unboxing.
import { useCallback, useState } from "react";
import { KeyboardAvoidingView, Modal, Platform, Pressable, ScrollView, StyleSheet, TextInput, View, useWindowDimensions } from "react-native";
import { router, useFocusEffect, useLocalSearchParams } from "expo-router";
import { Image } from "expo-image";
import * as WebBrowser from "expo-web-browser";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { ApiError, isDemo } from "@/api/client";
import { REASON_LABELS, fulfilment, type ProblemReason, type Unboxing } from "@/api/fulfilment";
import { useFeature } from "@/lib/features";
import { haptic } from "@/lib/haptics";
import { radius, useTheme } from "@/theme";
import { Button, Card, Empty, IconButton, Skeleton, T, Tap } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { useT } from "@/i18n";

export default function UnboxingScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const on = useFeature("unboxing");
  const { t, dateTime } = useT();
  const { c } = useTheme();
  const toast = useToast();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const [data, setData] = useState<Unboxing | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [mode, setMode] = useState<"view" | "problem">("view");
  const [reason, setReason] = useState<ProblemReason | null>(null);
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);
  const [photo, setPhoto] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      if (!on || isDemo()) return;
      let live = true;
      fulfilment
        .unboxing(String(id))
        .then((u) => live && setData(u))
        .catch((e: Error) => live && setError(e.message));
      return () => {
        live = false;
      };
    }, [on, id]),
  );

  const back = () => router.back();
  if (!on) return <Empty icon="package" title={t("ur.paused")} text={t("ur.pausedB")} action={t("rw.backOrder")} onAction={back} />;
  if (isDemo()) return <Empty icon="package" title={t("ur.demoT")} text={t("ur.demoB")} action={t("rw.backOrder")} onAction={back} />;
  if (error) return <Empty icon="alert-circle" title={t("ur.loadFail")} text={error} action={t("rw.backOrder")} onAction={back} />;
  if (!data)
    return (
      <View style={{ padding: 16, gap: 12 }}>
        <Skeleton h={120} r={20} />
        <Skeleton h={200} r={20} />
      </View>
    );

  const awaiting = data.status === "awaiting_customer_approval";
  const thumb = Math.floor((width - 32 - 32 - 16) / 3);

  async function approve() {
    setBusy(true);
    try {
      setData(await fulfilment.approveUnboxing(String(id)));
      haptic.success();
      toast({ kind: "success", title: t("ur.approved"), body: t("ur.approvedB") });
    } catch (e) {
      haptic.error();
      toast({ kind: "error", title: t("ur.approveFail"), body: e instanceof ApiError ? e.message : t("common.pleaseRetry") });
    } finally {
      setBusy(false);
    }
  }
  async function report() {
    if (!reason) return;
    setBusy(true);
    try {
      setData(await fulfilment.reportUnboxing(String(id), reason, note.trim()));
      setMode("view");
      haptic.success();
      toast({ kind: "info", title: t("ur.reported"), body: t("ur.reportedB") });
    } catch (e) {
      haptic.error();
      toast({ kind: "error", title: t("ur.sendFail"), body: e instanceof ApiError ? e.message : t("common.pleaseRetry") });
    } finally {
      setBusy(false);
    }
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.bg }} behavior={Platform.OS === "ios" ? "padding" : undefined} keyboardVerticalOffset={Platform.OS === "ios" ? 100 : 0}>
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 32, gap: 14 }} keyboardShouldPersistTaps="handled">
        <T v="small" tone="ink2">{t("ur.sub", { id: String(id) })}</T>
        <Card style={{ gap: 12 }}>
          <T v="h2" accessibilityRole="header">
            {awaiting ? t("ur.opened") : data.status === "problem" ? t("ur.reported") : data.status ? t("ur.approved") : t("ur.notYet")}
          </T>
          <T v="small" tone="ink2">
            {awaiting
              ? t("ur.check") + (data.autoApproveAt ? t("ur.auto", { when: dateTime(data.autoApproveAt) }) : "")
              : data.status === "problem"
                ? t("ur.onIt")
                : data.status
                  ? t("ur.onWay")
                  : t("ur.whenArrive")}
          </T>
          {data.items.map((it) => (
            <View key={it.id} style={{ gap: 8 }}>
              <T v="small" style={{ fontWeight: "700" }}>
                {it.title}
                {it.variant ? <T v="small" tone="ink2">{` · ${it.variant}`}</T> : null}
                {it.condition && it.condition !== "ok" ? <T v="small" tone="bad">{` · ${t(it.condition === "damaged" ? "rr.damaged" : "rr.wrong_item")}`}</T> : null}
              </T>
              {!!it.note && <T v="tiny" tone="ink2">{t("ur.hubNote", { note: it.note })}</T>}
              <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
                {it.photos.map((src, k) => (
                  <Tap
                    key={src}
                    onPress={() => setPhoto(src)}
                    accessibilityRole="imagebutton"
                    accessibilityLabel={t("ur.openMedia", { kind: t("ur.photo"), n: k + 1, title: it.title })}
                    style={{ width: thumb, height: thumb, borderRadius: radius.sm, overflow: "hidden", backgroundColor: c.surface2 }}
                  >
                    <Image source={{ uri: src }} style={{ width: "100%", height: "100%" }} contentFit="cover" transition={150} />
                  </Tap>
                ))}
                {it.video && (
                  <Tap
                    onPress={() => WebBrowser.openBrowserAsync(it.video!).catch(() => {})}
                    accessibilityLabel={t("ur.openMedia", { kind: t("ur.video"), n: 1, title: it.title })}
                    style={[styles.video, { width: thumb, height: thumb, backgroundColor: c.ink }]}
                  >
                    <Icon name="play" size={26} color={c.bg} />
                  </Tap>
                )}
              </View>
            </View>
          ))}
        </Card>

        {awaiting && mode === "view" && (
          <View style={{ gap: 10 }}>
            <Button label={t("ur.good")} icon="check" busy={busy} onPress={approve} />
            <Button label={t("ur.report")} icon="info" kind="ghost" disabled={busy} onPress={() => setMode("problem")} />
          </View>
        )}

        {awaiting && mode === "problem" && (
          <Card style={{ gap: 12 }}>
            <T v="h3" accessibilityRole="header">{t("ur.wrong")}</T>
            <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }} accessibilityRole="radiogroup" accessibilityLabel={t("ur.problem")}>
              {data.reasons.map((r) => {
                const sel = reason === r;
                return (
                  <Tap
                    key={r}
                    onPress={() => {
                      haptic.tap();
                      setReason(r);
                    }}
                    accessibilityRole="radio"
                    accessibilityState={{ checked: sel }}
                    style={[styles.chip, { backgroundColor: sel ? c.brand : c.surface2 }]}
                  >
                    <T v="small" style={{ fontWeight: "700", color: sel ? "#fff" : c.ink }}>{t(REASON_LABELS[r])}</T>
                  </Tap>
                );
              })}
            </View>
            <T v="small" style={{ fontWeight: "700" }}>
              {t("ur.more")} <T v="small" tone="ink3">{t("rw.optional")}</T>
            </T>
            <TextInput
              value={note}
              onChangeText={setNote}
              placeholder={t("ur.notePh")}
              placeholderTextColor={c.ink3}
              multiline
              maxLength={1000}
              accessibilityLabel={t("ur.more")}
              style={[styles.note, { color: c.ink, backgroundColor: c.surface2 }]}
            />
            <View style={{ flexDirection: "row", gap: 10 }}>
              <Button label={t("common.cancel")} kind="ghost" style={{ flex: 1 }} onPress={() => setMode("view")} />
              <Button label={t("ur.send")} icon="send" style={{ flex: 1.4 }} busy={busy} disabled={!reason} onPress={report} />
            </View>
          </Card>
        )}
      </ScrollView>

      <Modal visible={!!photo} transparent animationType="fade" onRequestClose={() => setPhoto(null)} statusBarTranslucent>
        <Pressable style={styles.lightbox} onPress={() => setPhoto(null)} accessibilityRole="button" accessibilityLabel={t("common.close")}>
          {photo && <Image source={{ uri: photo }} style={{ width: "100%", height: "80%" }} contentFit="contain" />}
          <View style={{ position: "absolute", top: insets.top + 12, right: 16 }}>
            <IconButton icon="x" label={t("common.close")} onPress={() => setPhoto(null)} />
          </View>
        </Pressable>
      </Modal>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  video: { borderRadius: radius.sm, alignItems: "center", justifyContent: "center" },
  chip: { minHeight: 44, paddingHorizontal: 14, borderRadius: radius.md, alignItems: "center", justifyContent: "center" },
  note: { minHeight: 90, borderRadius: radius.md, padding: 12, fontSize: 15, textAlignVertical: "top" },
  lightbox: { flex: 1, backgroundColor: "rgba(0,0,0,0.92)", alignItems: "center", justifyContent: "center" },
});
