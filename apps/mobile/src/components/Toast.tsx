import { createContext, useCallback, useContext, useRef, useState, type ReactNode } from "react";
import { Pressable, StyleSheet, View } from "react-native";
import Animated, { FadeInUp, FadeOutUp } from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { router } from "expo-router";
import { shadow, useTheme } from "@/theme";
import { Icon } from "./Icon";
import { T } from "./ui";
import { DUR, EASE } from "@/lib/motion";

type Toast = { id: number; kind: "success" | "info" | "error"; title: string; body?: string; action?: { label: string; href: string } };
const Ctx = createContext<(t: Omit<Toast, "id">) => void>(() => {});

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toast, setToast] = useState<Toast | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const insets = useSafeAreaInsets();
  const { c } = useTheme();

  const show = useCallback((t: Omit<Toast, "id">) => {
    if (timer.current) clearTimeout(timer.current);
    setToast({ ...t, id: Date.now() });
    timer.current = setTimeout(() => setToast(null), 3200);
  }, []);

  const color = toast?.kind === "error" ? c.bad : toast?.kind === "info" ? c.brand : c.good;

  return (
    <Ctx.Provider value={show}>
      {children}
      {toast && (
        <Animated.View
          key={toast.id}
          entering={FadeInUp.duration(DUR.open).easing(EASE)}
          exiting={FadeOutUp.duration(180)}
          style={[styles.wrap, { top: insets.top + 8 }]}
          pointerEvents="box-none"
          accessibilityLiveRegion="polite"
        >
          <View style={[styles.toast, { backgroundColor: c.surface, borderColor: c.line }, shadow(c, 2)]}>
            <View style={[styles.dot, { backgroundColor: color }]}>
              <Icon name={toast.kind === "error" ? "x" : toast.kind === "info" ? "info" : "check"} size={14} color="#fff" />
            </View>
            <View style={{ flex: 1 }}>
              <T v="h3" style={{ fontSize: 14 }}>
                {toast.title}
              </T>
              {toast.body && (
                <T v="small" tone="ink2" numberOfLines={2}>
                  {toast.body}
                </T>
              )}
            </View>
            {toast.action && (
              <Pressable
                accessibilityRole="button"
                onPress={() => {
                  setToast(null);
                  router.push(toast.action!.href as never);
                }}
                style={styles.action}
                hitSlop={8}
              >
                <T v="small" style={{ color: c.brand, fontWeight: "800" }}>
                  {toast.action.label}
                </T>
              </Pressable>
            )}
          </View>
        </Animated.View>
      )}
    </Ctx.Provider>
  );
}

export function useToast() {
  return useContext(Ctx);
}

const styles = StyleSheet.create({
  wrap: { position: "absolute", left: 12, right: 12, zIndex: 1000 },
  toast: { flexDirection: "row", alignItems: "center", gap: 12, padding: 12, borderRadius: 18, borderWidth: StyleSheet.hairlineWidth },
  dot: { width: 28, height: 28, borderRadius: 14, alignItems: "center", justifyContent: "center" },
  action: { minHeight: 44, justifyContent: "center", paddingHorizontal: 6 },
});
