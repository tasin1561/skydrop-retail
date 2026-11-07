import { useState } from "react";
import { StyleSheet, TextInput, View } from "react-native";
import Animated, { FadeIn } from "react-native-reanimated";
import { couponHint, findCoupon, type Totals } from "@/lib/cart";
import { formatBdt } from "@/lib/pricing";
import { useStore } from "@/state/store";
import { radius, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Icon } from "./Icon";
import { Button, Card, Divider, T } from "./ui";

export function CouponField() {
  const { c } = useTheme();
  const { coupon, setCoupon } = useStore();
  const [text, setText] = useState("");
  const [err, setErr] = useState<string | null>(null);
  const hint = couponHint(text);

  if (coupon)
    return (
      <Animated.View entering={FadeIn} style={[styles.applied, { backgroundColor: c.dark ? "rgba(34,197,94,0.12)" : "#ecfdf3" }]}>
        <Icon name="tag" size={18} color={c.good} />
        <View style={{ flex: 1 }}>
          <T v="h3" style={{ fontSize: 14, color: c.good }}>
            {coupon.code} applied
          </T>
          <T v="tiny" tone="ink2">
            {coupon.label}
          </T>
        </View>
        <Button label="Remove" kind="ghost" onPress={() => setCoupon("")} style={{ minHeight: 44, paddingHorizontal: 12 }} />
      </Animated.View>
    );

  const apply = () => {
    const found = findCoupon(text);
    if (!found) {
      haptic.error();
      setErr("That code isn't valid. Try SKYDROP10 or FREESHIP");
      return;
    }
    haptic.success();
    setErr(null);
    setCoupon(found.code);
    setText("");
  };

  return (
    <View>
      <View style={{ flexDirection: "row", gap: 8 }}>
        <TextInput
          value={text}
          onChangeText={(t) => {
            setText(t.toUpperCase());
            setErr(null);
          }}
          placeholder="Coupon code"
          placeholderTextColor={c.ink3}
          autoCapitalize="characters"
          autoCorrect={false}
          returnKeyType="done"
          onSubmitEditing={apply}
          accessibilityLabel="Coupon code"
          style={[
            styles.input,
            { color: c.ink, backgroundColor: c.surface2, borderColor: hint === "match" ? c.good : hint === "unknown" ? c.bad : "transparent" },
          ]}
        />
        <Button label="Apply" kind="soft" onPress={apply} disabled={!text.trim()} style={{ paddingHorizontal: 18 }} />
      </View>
      <T v="tiny" tone={err ? "bad" : "ink3"} style={{ marginTop: 6 }}>
        {err ?? "Try SKYDROP10 or FREESHIP"}
      </T>
    </View>
  );
}

export function SummaryLines({ t }: { t: Totals }) {
  const { c } = useTheme();
  const row = (label: string, value: string, tone?: string, bold?: boolean) => (
    <View style={styles.row}>
      <T v={bold ? "h3" : "small"} tone={bold ? "ink" : "ink2"}>
        {label}
      </T>
      <T v={bold ? "h2" : "small"} style={{ fontWeight: bold ? "800" : "700", color: tone ?? c.ink, fontVariant: ["tabular-nums"] }}>
        {value}
      </T>
    </View>
  );
  return (
    <Card style={{ gap: 8 }}>
      {row(`Items (${t.count})`, formatBdt(t.subtotal))}
      {row(`Skydrop shipping (${t.groups.length} ${t.groups.length === 1 ? "store" : "stores"})`, t.shipping ? formatBdt(t.shipping) : "Free")}
      {t.itemDiscount > 0 && row("Coupon discount", "-" + formatBdt(t.itemDiscount), c.good)}
      {t.shipDiscount > 0 && row("Free shipping coupon", "-" + formatBdt(t.shipDiscount), c.good)}
      <Divider style={{ marginVertical: 4 }} />
      {row("Total", formatBdt(t.total), undefined, true)}
      <T v="tiny" tone="ink3">
        Import duty included. Prices are verified live with the Indian stores at checkout.
      </T>
    </Card>
  );
}

const styles = StyleSheet.create({
  input: { flex: 1, minHeight: 50, borderRadius: radius.md, paddingHorizontal: 14, fontSize: 15, fontWeight: "700", letterSpacing: 1, borderWidth: 1.5 },
  applied: { flexDirection: "row", alignItems: "center", gap: 10, padding: 12, borderRadius: radius.md },
  row: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
});
