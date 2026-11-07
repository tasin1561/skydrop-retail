// Entry point to the Skydrop AI screen (hidden when the "ai_assistant" feature is off).
import { View } from "react-native";
import { router } from "expo-router";
import { useFeature } from "@/lib/features";
import { useTheme } from "@/theme";
import { IconButton } from "./ui";
import { useT } from "@/i18n";

export function AskAiButton({ productId, spaced }: { productId?: string; spaced?: boolean }) {
  const { t } = useT();
  const on = useFeature("ai_assistant");
  const { c } = useTheme();
  if (!on) return null;
  return (
    <>
      <IconButton
        icon="message-circle"
        label={productId ? t("ai.askProduct") : t("ai.ask")}
        tint={c.brand}
        onPress={() => router.push({ pathname: "/assistant", params: productId ? { productId } : {} })}
      />
      {spaced && <View style={{ width: 8 }} />}
    </>
  );
}
