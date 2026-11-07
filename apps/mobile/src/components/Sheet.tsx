// Bottom sheet built on Modal + Reanimated (transform/opacity only).
import { useEffect, useState, type ReactNode } from "react";
import { Modal, Pressable, ScrollView, StyleSheet, View, useWindowDimensions } from "react-native";
import Animated, { Easing, runOnJS, useAnimatedStyle, useSharedValue, withTiming } from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useTheme } from "@/theme";
import { T, IconButton } from "./ui";
import { useT } from "@/i18n";
import { DUR, timing } from "@/lib/motion";

export function Sheet({
  open,
  onClose,
  title,
  children,
  footer,
}: {
  open: boolean;
  onClose: () => void;
  title: string;
  children: ReactNode;
  footer?: ReactNode;
}) {
  const { t } = useT();
  const { c } = useTheme();
  const { height } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const [mounted, setMounted] = useState(open);
  if (open && !mounted) setMounted(true);
  const y = useSharedValue(height);
  const fade = useSharedValue(0);

  useEffect(() => {
    if (open) {
      y.value = withTiming(0, timing(DUR.open));
      fade.value = withTiming(1, { duration: 200 });
    } else if (mounted) {
      fade.value = withTiming(0, { duration: 180 });
      y.value = withTiming(height, { duration: 220, easing: Easing.in(Easing.cubic) }, (done) => {
        if (done) runOnJS(setMounted)(false);
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const sheet = useAnimatedStyle(() => ({ transform: [{ translateY: y.value }] }));
  const backdrop = useAnimatedStyle(() => ({ opacity: fade.value }));

  if (!mounted) return null;
  return (
    <Modal transparent visible statusBarTranslucent navigationBarTranslucent onRequestClose={onClose} animationType="none">
      <Animated.View style={[StyleSheet.absoluteFill, { backgroundColor: c.overlay }, backdrop]}>
        <Pressable style={StyleSheet.absoluteFill} onPress={onClose} accessibilityLabel={t("common.close")} />
      </Animated.View>
      <Animated.View
        style={[styles.sheet, { backgroundColor: c.surface, maxHeight: height * 0.88, paddingBottom: insets.bottom + 12 }, sheet]}
        accessibilityViewIsModal
      >
        <View style={[styles.grabber, { backgroundColor: c.line }]} />
        <View style={styles.head}>
          <T v="h2" accessibilityRole="header">
            {title}
          </T>
          <IconButton icon="x" label={t("common.close")} onPress={onClose} bg={c.surface2} size={40} />
        </View>
        <ScrollView contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 12 }} keyboardShouldPersistTaps="handled">
          {children}
        </ScrollView>
        {footer && <View style={{ paddingHorizontal: 16, paddingTop: 8 }}>{footer}</View>}
      </Animated.View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  sheet: { position: "absolute", left: 0, right: 0, bottom: 0, borderTopLeftRadius: 24, borderTopRightRadius: 24 },
  grabber: { alignSelf: "center", width: 40, height: 5, borderRadius: 3, marginTop: 8 },
  head: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", paddingHorizontal: 16, paddingVertical: 10 },
});
