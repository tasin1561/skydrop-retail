import "react-native-reanimated";
import { useEffect } from "react";
import { useFonts } from "expo-font";
import { brandFont, FONT_FILES, setFontsLoaded } from "@/theme/fonts";
import { Platform } from "react-native";
import { DarkTheme, DefaultTheme, Stack, ThemeProvider as NavTheme } from "expo-router";
import * as SplashScreen from "expo-splash-screen";
import { StatusBar } from "expo-status-bar";
import { SafeAreaProvider } from "react-native-safe-area-context";
import { ThemeProvider, useTheme } from "@/theme";
import { StoreProvider, useStore } from "@/state/store";
import { ToastProvider } from "@/components/Toast";
import { listenForTaps } from "@/lib/push";
import { MaintenanceOverlay } from "@/components/system/MaintenanceScreen";
import { StorePicker } from "@/components/StorePicker";
import { LangProvider, useLangSync, useT } from "@/i18n";

export { ErrorBoundary } from "expo-router";

export const unstable_settings = { anchor: "(tabs)" };

SplashScreen.preventAutoHideAsync().catch(() => {});

export default function RootLayout() {
  // Load the brand font; never block the app on it (system font until ready, or if loading fails).
  const [fontsLoaded, fontError] = useFonts(FONT_FILES);
  setFontsLoaded(fontsLoaded && !fontError);
  if (!fontsLoaded && !fontError) return null; // splash stays up meanwhile
  return (
    <SafeAreaProvider>
      <ThemeProvider>
        <StoreProvider>
          <LangProvider>
            <ToastProvider>
              <Nav />
            </ToastProvider>
          </LangProvider>
        </StoreProvider>
      </ThemeProvider>
    </SafeAreaProvider>
  );
}

function Nav() {
  const { c } = useTheme();
  const { ready, user } = useStore();
  const { t } = useT();
  useLangSync(!!user); // signed-in shoppers: save the language to the profile (PATCH /me { locale })

  useEffect(() => {
    if (ready) SplashScreen.hideAsync().catch(() => {});
  }, [ready]);

  // Push taps deep-link to /order/{id} or /product/{id} (active once expo-notifications is installed, see src/lib/push.ts).
  useEffect(() => listenForTaps(), []);

  const base = c.dark ? DarkTheme : DefaultTheme;
  const navTheme = {
    ...base,
    colors: { ...base.colors, background: c.bg, card: c.surface, text: c.ink, border: c.line, primary: c.brand },
  };

  return (
    <NavTheme value={navTheme}>
      <StatusBar style={c.dark ? "light" : "dark"} />
      <Stack
        screenOptions={{
          headerShadowVisible: false,
          headerStyle: { backgroundColor: c.bg },
          headerTintColor: c.ink,
          headerTitleStyle: { fontWeight: "800", ...(brandFont("800") as { fontFamily?: string }) }, // brandFont follows `lang`
          headerBackButtonDisplayMode: "minimal",
          contentStyle: { backgroundColor: c.bg },
          // Native slide on Android, native push with swipe-back on iOS.
          animation: Platform.OS === "android" ? "slide_from_right" : "default",
          gestureEnabled: true,
          fullScreenGestureEnabled: true,
        }}
      >
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="product/[id]" options={{ headerShown: false }} />
        <Stack.Screen name="store/[market]" options={{ headerShown: false }} />
        <Stack.Screen name="search" options={{ headerShown: false, animation: "fade" }} />
        <Stack.Screen name="checkout/index" options={{ title: t("checkout.meta") }} />
        <Stack.Screen name="checkout/success" options={{ headerShown: false, gestureEnabled: false, animation: "fade" }} />
        <Stack.Screen name="deals" options={{ headerShown: false }} />
        <Stack.Screen name="points" options={{ title: t("acc.points") }} />
        <Stack.Screen name="wallet" options={{ title: t("acc.wallet") }} />
        <Stack.Screen name="plus" options={{ title: t("acc.plus") }} />
        <Stack.Screen name="success" options={{ headerShown: false, gestureEnabled: false, animation: "fade" }} />
        <Stack.Screen name="order/[id]" options={{ title: t("rw.order") }} />
        <Stack.Screen name="login" options={{ title: t("nav.signIn"), presentation: "modal" }} />
        <Stack.Screen name="addresses" options={{ title: t("acc.addresses") }} />
        <Stack.Screen name="wishlist" options={{ title: t("acc.wishlist") }} />
        <Stack.Screen name="request" options={{ title: t("common.requestProduct") }} />
        <Stack.Screen name="requests" options={{ title: t("m.myRequests") }} />
        <Stack.Screen name="profile" options={{ title: t("m.profile") }} />
        <Stack.Screen name="notifications" options={{ title: t("acc.notif") }} />
        <Stack.Screen name="notification-settings" options={{ title: t("mx.np.title") }} />
        <Stack.Screen name="group/index" options={{ title: t("grp.groupBuy") }} />
        <Stack.Screen name="group/[code]" options={{ title: t("grp.groupBuy") }} />
        <Stack.Screen name="bundles/index" options={{ title: t("bundles.title") }} />
        <Stack.Screen name="bundles/[slug]" options={{ title: t("bundles.title") }} />
        <Stack.Screen name="unboxing/[id]" options={{ title: t("ur.title") }} />
        <Stack.Screen name="invite" options={{ title: t("acc.invite") }} />
      </Stack>
      <StorePicker />
      <MaintenanceOverlay />
    </NavTheme>
  );
}
