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
          <ToastProvider>
            <Nav />
          </ToastProvider>
        </StoreProvider>
      </ThemeProvider>
    </SafeAreaProvider>
  );
}

function Nav() {
  const { c } = useTheme();
  const { ready } = useStore();

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
          headerTitleStyle: { fontWeight: "800", ...(brandFont("800") as { fontFamily?: string }) },
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
        <Stack.Screen name="checkout/index" options={{ title: "Checkout" }} />
        <Stack.Screen name="checkout/success" options={{ headerShown: false, gestureEnabled: false, animation: "fade" }} />
        <Stack.Screen name="deals" options={{ headerShown: false }} />
        <Stack.Screen name="points" options={{ title: "Skydrop Points" }} />
        <Stack.Screen name="wallet" options={{ title: "Skydrop Wallet" }} />
        <Stack.Screen name="plus" options={{ title: "Skydrop Plus" }} />
        <Stack.Screen name="success" options={{ headerShown: false, gestureEnabled: false, animation: "fade" }} />
        <Stack.Screen name="order/[id]" options={{ title: "Order" }} />
        <Stack.Screen name="login" options={{ title: "Sign in", presentation: "modal" }} />
        <Stack.Screen name="addresses" options={{ title: "Addresses" }} />
        <Stack.Screen name="wishlist" options={{ title: "Wishlist" }} />
        <Stack.Screen name="request" options={{ title: "Request a product" }} />
        <Stack.Screen name="requests" options={{ title: "My requests" }} />
        <Stack.Screen name="profile" options={{ title: "Profile" }} />
        <Stack.Screen name="notifications" options={{ title: "Notifications" }} />
      </Stack>
      <MaintenanceOverlay />
    </NavTheme>
  );
}
