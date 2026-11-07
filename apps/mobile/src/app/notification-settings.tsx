// Notification settings: marketing channels (GET/PATCH /me/notifications) and push on this phone.
// Transactional messages (orders, payments, delivery) are always sent and cannot be turned off.
import { useCallback, useState } from "react";
import { AppState, ScrollView, StyleSheet, Switch, View } from "react-native";
import { router, useFocusEffect } from "expo-router";
import { messagingApi, type NotifyPrefs } from "@/api/messaging";
import { useStore } from "@/state/store";
import { useFeature } from "@/lib/features";
import { enablePush, openPhoneSettings, pushState, type PushState } from "@/lib/push";
import { haptic } from "@/lib/haptics";
import { useTheme } from "@/theme";
import { Button, Card, Divider, Empty, IconChip, Skeleton, T } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { useT, type MsgKey } from "@/i18n";

type Channel = keyof NotifyPrefs["marketing"];
const CHANNELS: { key: Channel; feature: string; icon: string; title: MsgKey; sub: MsgKey }[] = [
  { key: "push", feature: "push", icon: "bell", title: "mx.np.push", sub: "mx.np.pushSub" },
  { key: "sms", feature: "sms", icon: "message-square", title: "mx.np.sms", sub: "mx.np.smsSub" },
  { key: "email", feature: "email", icon: "mail", title: "mx.np.email", sub: "mx.np.emailSub" },
  { key: "inapp", feature: "in_app_notifications", icon: "inbox", title: "mx.np.inapp", sub: "mx.np.inappSub" },
];

export default function NotificationSettings() {
  const { t } = useT();
  const { c } = useTheme();
  const toast = useToast();
  const { user, ready } = useStore();
  const pushOn = useFeature("push");
  const marketingOn = useFeature("marketing_messages");
  const on: Record<string, boolean> = {
    push: useFeature("push"),
    sms: useFeature("sms"),
    email: useFeature("email"),
    in_app_notifications: useFeature("in_app_notifications"),
  };
  const [prefs, setPrefs] = useState<NotifyPrefs | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [device, setDevice] = useState<PushState>("unsupported");
  const [asking, setAsking] = useState(false);

  const refreshDevice = useCallback(() => {
    pushState()
      .then(setDevice)
      .catch(() => setDevice("unsupported"));
  }, []);

  useFocusEffect(
    useCallback(() => {
      if (!ready || !user) return;
      let live = true;
      messagingApi
        .prefs()
        .then((p) => {
          if (!live) return;
          setPrefs(p);
          setError(null);
        })
        .catch((e: Error) => live && setError(e.message));
      refreshDevice();
      // Coming back from the phone's settings app: re-read the permission.
      const sub = AppState.addEventListener("change", (s) => s === "active" && refreshDevice());
      return () => {
        live = false;
        sub.remove();
      };
    }, [ready, user, refreshDevice]),
  );

  if (!user) return <Empty icon="bell" title={t("mx.signInFor")} text={t("mx.np.intro")} action={t("nav.signIn")} onAction={() => router.push({ pathname: "/login", params: { next: "/notification-settings" } })} />;
  if (error && !prefs) return <Empty icon="alert-circle" title={t("mx.np.loadFail")} text={error} action={t("common.tryAgain")} onAction={() => setError(null)} />;

  async function toggle(key: Channel, value: boolean) {
    if (!prefs) return;
    haptic.tap();
    const before = prefs;
    setPrefs({ ...prefs, marketing: { ...prefs.marketing, [key]: value } }); // optimistic
    try {
      setPrefs(await messagingApi.savePrefs({ marketing: { [key]: value } }));
    } catch (e) {
      setPrefs(before);
      haptic.error();
      toast({ kind: "error", title: t("mx.np.saveFail"), body: (e as Error).message });
    }
  }

  async function turnOnPush() {
    setAsking(true);
    const s = await enablePush();
    setAsking(false);
    setDevice(s === "unsupported" ? device : s);
    if (s === "granted") {
      haptic.success();
      toast({ kind: "success", title: t("mx.pushOn") });
    }
  }

  return (
    <ScrollView style={{ flex: 1, backgroundColor: c.bg }} contentContainerStyle={{ padding: 16, paddingBottom: 40, gap: 14 }}>
      <T v="small" tone="ink2">{t("mx.np.intro")}</T>

      {pushOn && (
        <Card style={{ gap: 12 }}>
          <View style={styles.row}>
            <IconChip icon="smartphone" />
            <View style={{ flex: 1 }}>
              <T v="h3" style={{ fontSize: 15 }}>{t("mx.np.device")}</T>
              <T v="tiny" tone="ink2">
                {device === "granted" ? t("mx.np.deviceOn") : device === "denied" ? t("mx.np.deviceBlocked") : device === "undetermined" ? t("mx.np.deviceOff") : t("mx.np.deviceNone")}
              </T>
            </View>
            {device === "granted" && <Icon name="check-circle" size={20} color={c.good} />}
          </View>
          {device === "undetermined" && <Button label={t("mx.np.enable")} icon="bell" busy={asking} onPress={turnOnPush} />}
          {device === "denied" && <Button label={t("mx.np.openSettings")} kind="ghost" icon="settings" onPress={openPhoneSettings} />}
        </Card>
      )}

      <Card pad={6}>
        <View style={[styles.row, { padding: 10 }]}>
          <IconChip icon="package" color={c.good} />
          <View style={{ flex: 1 }}>
            <T v="h3" style={{ fontSize: 15 }}>{t("mx.np.orders")}</T>
            <T v="tiny" tone="ink2">{prefs?.transactional ?? t("acc.notifSub")}</T>
          </View>
          <T v="tiny" tone="good" style={{ fontWeight: "800" }}>{t("mx.np.always")}</T>
        </View>
      </Card>

      {marketingOn && (
        <>
          <T v="label" tone="ink2" style={{ marginTop: 4 }}>{t("mx.np.offers")}</T>
          <Card pad={6}>
            {!prefs
              ? [0, 1, 2].map((i) => <Skeleton key={i} h={52} style={{ margin: 8 }} />)
              : CHANNELS.filter((ch) => on[ch.feature]).map((ch, i) => {
                  const noEmail = ch.key === "email" && !prefs.email;
                  const value = !!prefs.marketing[ch.key] && !noEmail;
                  return (
                    <View key={ch.key}>
                      {i > 0 && <Divider style={{ marginLeft: 60 }} />}
                      <View style={[styles.row, { padding: 10, minHeight: 64 }]}>
                        <IconChip icon={ch.icon} />
                        <View style={{ flex: 1 }}>
                          <T v="h3" style={{ fontSize: 15 }}>{t(ch.title)}</T>
                          <T v="tiny" tone="ink2">{noEmail ? t("mx.np.emailNone") : t(ch.sub)}</T>
                        </View>
                        <Switch
                          value={value}
                          disabled={noEmail}
                          onValueChange={(v) => toggle(ch.key, v)}
                          trackColor={{ true: c.brand, false: c.surface2 }}
                          thumbColor="#fff"
                          {...({ activeThumbColor: "#fff" } as object)} // react-native-web only
                          ios_backgroundColor={c.surface2}
                          accessibilityLabel={t(ch.title)}
                          accessibilityHint={t(ch.sub)}
                        />
                      </View>
                    </View>
                  );
                })}
          </Card>
          {prefs && !prefs.email && on.email && <Button label={t("m.profile")} kind="ghost" icon="user" onPress={() => router.push("/profile")} />}
        </>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", alignItems: "center", gap: 12 },
});
