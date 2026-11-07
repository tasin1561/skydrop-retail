import { router, Stack } from "expo-router";
import { View } from "react-native";
import { Empty } from "@/components/ui";
import { useTheme } from "@/theme";

export default function NotFound() {
  const { c } = useTheme();
  return (
    <View style={{ flex: 1, backgroundColor: c.bg, justifyContent: "center" }}>
      <Stack.Screen options={{ title: "Not found" }} />
      <Empty icon="compass" title="This page wandered off" text="Let's get you back to the stores." action="Go home" onAction={() => router.replace("/")} />
    </View>
  );
}
