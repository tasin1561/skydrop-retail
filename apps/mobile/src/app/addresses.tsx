import { useCallback, useState } from "react";
import { Alert, KeyboardAvoidingView, Platform, ScrollView, View } from "react-native";
import { useFocusEffect } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Animated, { FadeOut, LinearTransition } from "react-native-reanimated";
import { api } from "@/api/client";
import { useStore } from "@/state/store";
import type { Address } from "@/lib/types";
import { prettyPhone } from "@/lib/phone";
import { useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { AddressForm, AddressSaveButton, emptyAddress, type AddressDraft } from "@/components/AddressForm";
import { Button, Card, Empty, IconButton, IconChip, Pill, Skeleton, T } from "@/components/ui";
import { useToast } from "@/components/Toast";
import { useT } from "@/i18n";
import { bdName } from "@/lib/bd";
import { listEnter } from "@/lib/motion";

export default function Addresses() {
  const { t, lang } = useT();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { user } = useStore();
  const toast = useToast();
  const [list, setList] = useState<Address[] | null>(null);
  const [draft, setDraft] = useState<AddressDraft | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    api
      .addresses()
      .then(setList)
      .catch(() => setList([]));
  }, []);
  useFocusEffect(load);

  async function save() {
    if (!draft) return;
    setBusy(true);
    try {
      await api.saveAddress(draft);
      haptic.success();
      setDraft(null);
      load();
      toast({ kind: "success", title: t("ab.saved") });
    } catch (e) {
      toast({ kind: "error", title: t("m.cantSave"), body: (e as Error).message });
    } finally {
      setBusy(false);
    }
  }

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === "ios" ? "padding" : undefined} keyboardVerticalOffset={Platform.OS === "ios" ? 100 : 0}>
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 32, gap: 12 }} keyboardShouldPersistTaps="handled">
        {draft ? (
          <Card style={{ gap: 16 }}>
            <T v="h2">{draft.id ? t("ab.editTitle") : t("ab.new")}</T>
            <AddressForm value={draft} onChange={setDraft} />
            <AddressSaveButton value={draft} onSave={save} busy={busy} />
            <Button label={t("common.cancel")} kind="ghost" onPress={() => setDraft(null)} />
          </Card>
        ) : (
          <>
            {list === null ? (
              <Skeleton h={110} r={20} />
            ) : list.length === 0 ? (
              <Empty icon="map-pin" title={t("m.noAddresses")} text={t("m.noAddressesB")} />
            ) : (
              list.map((a, i) => (
                <Animated.View key={a.id} entering={listEnter(i)} exiting={FadeOut} layout={LinearTransition}>
                  <Card style={{ flexDirection: "row", gap: 12 }}>
                    <IconChip icon="map-pin" />
                    <View style={{ flex: 1, gap: 2 }}>
                      <View style={{ flexDirection: "row", gap: 6, alignItems: "center" }}>
                        <T v="h3" style={{ fontSize: 15 }}>
                          {a.name}
                        </T>
                        {a.isDefault && <Pill text={t("addr.default")} color={c.brand} />}
                      </View>
                      <T v="small" tone="ink2">
                        {prettyPhone(a.phone)}
                      </T>
                      <T v="small" tone="ink2">
                        {a.line}, {a.area}, {bdName(a.district, lang)}, {bdName(a.division, lang)}
                      </T>
                    </View>
                    <View style={{ gap: 4 }}>
                      <IconButton icon="edit-2" label={t("m.editFor", { name: a.name })} size={40} bg={c.surface2} onPress={() => setDraft(a)} />
                      <IconButton
                        icon="trash-2"
                        label={t("m.deleteFor", { name: a.name })}
                        size={40}
                        bg={c.surface2}
                        tint={c.bad}
                        onPress={() =>
                          Alert.alert(t("ab.deleteQ"), undefined, [
                            { text: t("common.cancel"), style: "cancel" },
                            {
                              text: t("ab.delete"),
                              style: "destructive",
                              onPress: async () => {
                                await api.deleteAddress(a.id).catch(() => {});
                                haptic.warn();
                                load();
                              },
                            },
                          ])
                        }
                      />
                    </View>
                  </Card>
                </Animated.View>
              ))
            )}
            <Button label={t("ab.add")} icon="plus" onPress={() => setDraft(emptyAddress(user?.name, user?.phone))} />
          </>
        )}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}
