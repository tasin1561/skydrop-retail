import { StyleSheet, View } from "react-native";
import Animated from "react-native-reanimated";
import { useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Icon } from "./Icon";
import { T, Tap } from "./ui";
import { useT } from "@/i18n";
import { enterFade } from "@/lib/motion";

export function QtyStepper({ value, onChange, min = 1, max = 10, small }: { value: number; onChange: (n: number) => void; min?: number; max?: number; small?: boolean }) {
  const { t, digits } = useT();
  const { c } = useTheme();
  const size = small ? 40 : 48;
  const btn = (icon: string, next: number, disabled: boolean, label: string) => (
    <Tap
      onPress={() => {
        if (disabled) return;
        haptic.tap();
        onChange(next);
      }}
      disabled={disabled}
      accessibilityLabel={label}
      hitSlop={small ? 4 : 0}
      style={[styles.btn, { width: size, height: size, opacity: disabled ? 0.35 : 1 }]}
    >
      <Icon name={icon} size={18} color={c.ink} />
    </Tap>
  );
  return (
    <View style={[styles.wrap, { backgroundColor: c.surface2 }]} accessibilityLabel={t("m.qtyA11y", { count: value })} accessibilityRole="adjustable">
      {btn("minus", value - 1, value <= min, t("qty.dec"))}
      <Animated.View key={value} entering={enterFade} style={{ minWidth: 28, alignItems: "center" }}>
        <T v="h3">{digits(value)}</T>
      </Animated.View>
      {btn("plus", value + 1, value >= max, t("qty.inc"))}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { flexDirection: "row", alignItems: "center", borderRadius: 999, alignSelf: "flex-start" },
  btn: { alignItems: "center", justifyContent: "center", borderRadius: 999 },
});
