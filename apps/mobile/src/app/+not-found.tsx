import { router, Stack } from "expo-router";
import { View } from "react-native";
import { Empty } from "@/components/ui";
import { useTheme } from "@/theme";
import { useT } from "@/i18n";

export default function NotFound() {
  const { t } = useT();
  const { c } = useTheme();
  return (
    <View style={{ flex: 1, backgroundColor: c.bg, justifyContent: "center" }}>
      <Stack.Screen options={{ title: t("m.notFound") }} />
      <Empty icon="compass" title={t("m.wandered")} text={t("m.wanderedB")} action={t("m.goHome")} onAction={() => router.replace("/")} />
    </View>
  );
}
