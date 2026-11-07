import { useEffect, useState } from "react";
import { ScrollView, TextInput, View } from "react-native";
import { Stack, router, useLocalSearchParams } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { isDemo } from "@/api/client";
import { REASON_LABELS, RESOLUTION_LABELS, fulfilment, type Eligibility, type Resolution, type ReturnCase, type ReturnReason } from "@/api/fulfilment";
import { useFeature } from "@/lib/features";
import { haptic } from "@/lib/haptics";
import { useTheme, withAlpha } from "@/theme";
import { Button, Card, Empty, Skeleton, T, Tap } from "@/components/ui";
import { useToast } from "@/components/Toast";
import { useT } from "@/i18n";

const WEB = (process.env.EXPO_PUBLIC_WEB_URL ?? "").replace(/\/+$/, "");
const PHOTO_REASONS: ReturnReason[] = ["damaged", "wrong_item"];

/** Simple return / replacement form (the web has the full wizard with photo upload). */
export default function ReturnScreen() {
  const { t, bdt } = useT();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const toast = useToast();
  const on = useFeature("returns");
  const [elig, setElig] = useState<Eligibility | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [picked, setPicked] = useState<Record<number, boolean>>({});
  const [reason, setReason] = useState<ReturnReason | null>(null);
  const [resolution, setResolution] = useState<Resolution | null>(null);
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState<ReturnCase | null>(null);

  useEffect(() => {
    if (!on || isDemo()) return;
    fulfilment
      .returns(id)
      .then((r) => setElig(r.eligibility))
      .catch((e: Error) => setError(e.message));
  }, [id, on]);

  if (!on) return <Empty icon="rotate-ccw" title={t("rw.paused")} text={t("rw.pausedB")} action={t("common.back")} onAction={() => router.back()} />;
  if (isDemo()) return <Empty icon="rotate-ccw" title={t("m.retDemo")} text={t("m.retDemoBody")} action={t("common.back")} onAction={() => router.back()} />;
  if (error) return <Empty icon="alert-circle" title={t("rw.loadFail")} text={error} action={t("common.back")} onAction={() => router.back()} />;
  if (!elig)
    return (
      <View style={{ padding: 16, gap: 12 }}>
        <Skeleton h={160} r={20} />
        <Skeleton h={220} r={20} />
      </View>
    );
  if (done)
    return (
      <Empty
        icon="check-circle"
        title={t("rw.requested")}
        text={t("rw.gotB")}
        action={t("rw.backOrder")}
        onAction={() => router.back()}
      />
    );
  if (!elig.eligible)
    return (
      <Empty
        icon="rotate-ccw"
        title={t("rw.cant")}
        text={elig.reason === "window_closed" ? t("rw.closed", { count: elig.windowDays }) : elig.reason === "already_requested" ? t("rw.already") : t("rw.notDelivered")}
        action={t("common.back")}
        onAction={() => router.back()}
      />
    );

  const chosen = elig.items.filter((i) => picked[i.id]);
  const needsPhoto = !!reason && PHOTO_REASONS.includes(reason);
  const ready = chosen.length > 0 && !!reason && !!resolution && !needsPhoto;

  const submit = async () => {
    if (!reason || !resolution) return;
    setBusy(true);
    try {
      const r = await fulfilment.createReturn(id, {
        items: chosen.map((i) => ({ orderItemId: i.id, qty: i.returnableQty })),
        reason,
        note: note.trim() || undefined,
        photos: [],
        resolution,
      });
      haptic.success();
      setDone(r);
    } catch (e) {
      toast({ kind: "error", title: t("m.cantSend"), body: (e as Error).message });
    } finally {
      setBusy(false);
    }
  };

  const chip = (active: boolean) => ({
    minHeight: 44, paddingHorizontal: 16, borderRadius: 999, justifyContent: "center" as const,
    backgroundColor: active ? c.brand : c.surface2,
  });

  return (
    <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 32, gap: 14 }} keyboardShouldPersistTaps="handled">
      <Stack.Screen options={{ title: t("rw.title") }} />
      <Card style={{ gap: 10 }}>
        <T v="h3" accessibilityRole="header">{t("rw.which")}</T>
        {elig.items.map((it) => (
          <Tap
            key={it.id}
            disabled={it.returnableQty === 0}
            onPress={() => setPicked((p) => ({ ...p, [it.id]: !p[it.id] }))}
            accessibilityRole="checkbox"
            accessibilityState={{ checked: !!picked[it.id], disabled: it.returnableQty === 0 }}
            style={{
              minHeight: 56, flexDirection: "row", alignItems: "center", gap: 12, padding: 12, borderRadius: 14,
              borderWidth: 2, borderColor: picked[it.id] ? c.brand : c.line, opacity: it.returnableQty === 0 ? 0.5 : 1,
            }}
          >
            <View style={{ flex: 1 }}>
              <T v="small" numberOfLines={2} style={{ fontWeight: "600" }}>
                {it.title}
              </T>
              <T v="tiny" tone="ink2">
                {it.returnableQty === 0 ? t("rw.inReturn") : t("m.retQty", { qty: it.returnableQty, price: bdt(it.unitBdt) })}
              </T>
            </View>
          </Tap>
        ))}
      </Card>

      <Card style={{ gap: 10 }}>
        <T v="h3" accessibilityRole="header">
          {t("rw.problem")}
        </T>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }} accessibilityRole="radiogroup">
          {elig.reasons.map((r) => (
            <Tap key={r} onPress={() => setReason(r)} accessibilityRole="radio" accessibilityState={{ checked: reason === r }} style={chip(reason === r)}>
              <T v="small" style={{ fontWeight: "700", color: reason === r ? "#fff" : c.ink }}>
                {t(REASON_LABELS[r])}
              </T>
            </Tap>
          ))}
        </View>
        {needsPhoto && (
          <View style={{ padding: 12, borderRadius: 14, backgroundColor: withAlpha(c.warn, 0.12), gap: 8 }}>
            <T v="small">{t("m.retPhotos")}</T>
            {!!WEB && <Button label={t("m.openWeb")} kind="ghost" icon="external-link" onPress={() => WebBrowser.openBrowserAsync(`${WEB}/orders/${id}/return`)} />}
          </View>
        )}
        <TextInput
          value={note}
          onChangeText={setNote}
          placeholder={t("m.detailsOpt")}
          placeholderTextColor={c.ink3}
          multiline
          maxLength={1000}
          accessibilityLabel={t("rw.details")}
          style={{ minHeight: 88, borderRadius: 14, padding: 12, backgroundColor: c.surface2, color: c.ink, textAlignVertical: "top" }}
        />
      </Card>

      <Card style={{ gap: 10 }}>
        <T v="h3" accessibilityRole="header">{t("rw.what")}</T>
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }} accessibilityRole="radiogroup">
          {elig.resolutions.map((r) => (
            <Tap key={r} onPress={() => setResolution(r)} accessibilityRole="radio" accessibilityState={{ checked: resolution === r }} style={chip(resolution === r)}>
              <T v="small" style={{ fontWeight: "700", color: resolution === r ? "#fff" : c.ink }}>
                {t(RESOLUTION_LABELS[r])}
              </T>
            </Tap>
          ))}
        </View>
      </Card>

      <Button label={t("rw.request")} icon="rotate-ccw" busy={busy} disabled={!ready} onPress={submit} />
    </ScrollView>
  );
}
