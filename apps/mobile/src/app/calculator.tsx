import { useEffect, useState } from "react";
import { ScrollView, StyleSheet, TextInput, View } from "react-native";
import { router } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { api, type Quote } from "@/api/client";
import { PRICING } from "@/lib/pricing";
import { haptic } from "@/lib/haptics";
import { useT, type MsgKey } from "@/i18n";
import { radius, useAccent, useTheme } from "@/theme";
import { Button, Card, Divider, T, Tap } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { CategoryIcon } from "@/components/CategoryIcon";

const CATS: { id: string; label: MsgKey; icon: string }[] = [
  { id: "fashion", label: "calc.cat.fashion", icon: "dress" },
  { id: "beauty", label: "calc.cat.beauty", icon: "lipstick" },
  { id: "electronics", label: "calc.cat.electronics", icon: "phone" },
  { id: "home", label: "calc.cat.home", icon: "home" },
  { id: "grocery", label: "calc.cat.grocery", icon: "basket" },
];

const up10 = (n: number) => Math.ceil(n / 10) * 10;

/** Same sums as the API (PricingService) with the bundled rates: demo mode, or when the server cannot be reached. */
function localQuote(inr: number, category: string, weight: number | null, qty: number): Quote {
  const base = inr * PRICING.fxInrToBdt * (1 + PRICING.fxBuffer);
  const imp = base * (PRICING.importPct[category] ?? 0.1);
  const total = up10((base + imp) * (1 + PRICING.margin));
  const each = weight ?? PRICING.defaultWeightKg[category] ?? 0.5;
  const items = total * qty;
  const shipping = items >= PRICING.shipping.freeAboveBdt ? 0 : up10(PRICING.shipping.baseBdt + PRICING.shipping.perKgBdt * Math.max(0.5, each * qty));
  return {
    qty,
    weightKg: each,
    weightAssumed: weight === null,
    unit: { storeBdt: Math.round(base), importBdt: Math.round(imp), serviceBdt: Math.max(0, total - Math.round(base) - Math.round(imp)), totalBdt: total },
    itemsBdt: items,
    shippingBdt: shipping,
    totalBdt: items + shipping,
  };
}

/** Cost calculator (same as the website's /calculator): a rupee price in, the delivered taka cost out, line by line. */
export default function CalculatorScreen() {
  const { t, bdt, inr: fmtInr, digits } = useT();
  const { c } = useTheme();
  const a = useAccent("all");
  const insets = useSafeAreaInsets();
  const [price, setPrice] = useState("");
  const [category, setCategory] = useState("fashion");
  const [weight, setWeight] = useState("");
  const [qty, setQty] = useState(1);
  const [quote, setQuote] = useState<Quote | null>(null);

  const inr = Number(price.replace(/[^\d.]/g, ""));
  const kg = weight.trim() ? Number(weight.replace(/[^\d.]/g, "")) : null;
  const valid = inr >= 1 && inr <= 2_000_000 && (kg === null || (kg >= 0.01 && kg <= 60));

  useEffect(() => {
    if (!valid) return;
    let live = true;
    const id = setTimeout(() => {
      api
        .priceQuote({ inr, category, qty, weightKg: kg })
        .catch(() => localQuote(inr, category, kg, qty))
        .then((q) => live && setQuote(q));
    }, 250);
    return () => {
      live = false;
      clearTimeout(id);
    };
  }, [inr, category, kg, qty, valid]);

  const shown = valid ? quote : null;
  const field = [styles.field, { backgroundColor: c.surface2, borderColor: c.line, color: c.ink }];
  const rows: [MsgKey, number][] = shown
    ? [
        ["calc.rowStore", shown.unit.storeBdt * shown.qty],
        ["calc.rowImport", shown.unit.importBdt * shown.qty],
        ["calc.rowService", shown.unit.serviceBdt * shown.qty],
      ]
    : [];

  return (
    <ScrollView style={{ flex: 1, backgroundColor: c.bg }} contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 32, gap: 14 }} keyboardShouldPersistTaps="handled">
      <T v="small" tone="ink2">
        {t("calc.sub")}
      </T>

      <Card style={{ gap: 16 }}>
        <View style={{ gap: 6 }}>
          <T v="small" style={{ fontWeight: "800" }}>
            {t("calc.price")}
          </T>
          <View>
            <T v="h3" tone="ink3" style={styles.rupee}>
              ₹
            </T>
            <TextInput
              value={price}
              onChangeText={setPrice}
              keyboardType="decimal-pad"
              placeholder="1499"
              placeholderTextColor={c.ink3}
              accessibilityLabel={t("calc.price")}
              accessibilityHint={t("calc.priceHint")}
              style={[field, { paddingLeft: 34 }]}
            />
          </View>
          <T v="tiny" tone="ink2">
            {t("calc.priceHint")}
          </T>
        </View>

        <View style={{ gap: 8 }}>
          <T v="small" style={{ fontWeight: "800" }}>
            {t("calc.category")}
          </T>
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }} accessibilityRole="radiogroup">
            {CATS.map((x) => {
              const on = x.id === category;
              return (
                <Tap
                  key={x.id}
                  onPress={() => {
                    haptic.tap();
                    setCategory(x.id);
                  }}
                  accessibilityRole="radio"
                  accessibilityState={{ checked: on }}
                  accessibilityLabel={t(x.label)}
                  style={[styles.chip, { backgroundColor: on ? a.accent : c.surface, borderColor: on ? a.accent : c.line }]}
                >
                  <CategoryIcon name={x.icon} size={16} color={on ? "#fff" : c.ink} />
                  <T v="small" style={{ fontWeight: "700", color: on ? "#fff" : c.ink }}>
                    {t(x.label)}
                  </T>
                </Tap>
              );
            })}
          </View>
        </View>

        <View style={{ flexDirection: "row", gap: 12 }}>
          <View style={{ flex: 1, gap: 6 }}>
            <T v="small" style={{ fontWeight: "800" }}>
              {t("calc.weight")}
            </T>
            <TextInput value={weight} onChangeText={setWeight} keyboardType="decimal-pad" placeholder={t("calc.weightPh")} placeholderTextColor={c.ink3} accessibilityLabel={t("calc.weight")} style={field} />
          </View>
          <View style={{ flex: 1, gap: 6 }}>
            <T v="small" style={{ fontWeight: "800" }}>
              {t("calc.qty")}
            </T>
            <View style={[styles.stepper, { backgroundColor: c.surface2, borderColor: c.line }]}>
              <Tap onPress={() => setQty((q) => Math.max(1, q - 1))} disabled={qty <= 1} accessibilityLabel={t("calc.less")} style={[styles.step, qty <= 1 && { opacity: 0.35 }]}>
                <Icon name="minus" size={18} color={c.ink} />
              </Tap>
              <T v="h3" accessibilityLiveRegion="polite">
                {digits(qty)}
              </T>
              <Tap onPress={() => setQty((q) => Math.min(20, q + 1))} disabled={qty >= 20} accessibilityLabel={t("calc.more")} style={[styles.step, qty >= 20 && { opacity: 0.35 }]}>
                <Icon name="plus" size={18} color={c.ink} />
              </Tap>
            </View>
          </View>
        </View>
      </Card>

      <Card style={{ gap: 4 }}>
        <T v="label" tone="ink3">
          {t("calc.result")}
        </T>
        {shown ? (
          <View accessibilityLiveRegion="polite">
            <T v="h1" style={{ fontSize: 34, lineHeight: 40 }}>
              {bdt(shown.totalBdt)}
            </T>
            <T v="small" tone="ink2" style={{ marginBottom: 8 }}>
              {t("calc.delivered", { inr: fmtInr(inr * shown.qty) })}
            </T>
            {rows.map(([k, v]) => (
              <View key={k}>
                <Divider />
                <View style={styles.line}>
                  <T v="small" tone="ink2">
                    {t(k)}
                  </T>
                  <T v="small" style={{ fontWeight: "700" }}>
                    {bdt(v)}
                  </T>
                </View>
              </View>
            ))}
            <Divider />
            <View style={styles.line}>
              <View style={{ flex: 1 }}>
                <T v="small" tone="ink2">
                  {t("calc.rowDelivery")}
                </T>
                <T v="tiny" tone="ink3">
                  {t(shown.weightAssumed ? "calc.weightAssumed" : "calc.weightGiven", { kg: digits(+(shown.weightKg * shown.qty).toFixed(2)) })}
                </T>
              </View>
              <T v="small" style={{ fontWeight: "700", color: shown.shippingBdt === 0 ? c.good : c.ink }}>
                {shown.shippingBdt === 0 ? t("calc.free") : bdt(shown.shippingBdt)}
              </T>
            </View>
            <Divider />
            <View style={styles.line}>
              <T v="h3">{t("calc.rowTotal")}</T>
              <T v="h3">{bdt(shown.totalBdt)}</T>
            </View>
            <T v="tiny" tone="ink2" style={{ lineHeight: 17 }}>
              {t("calc.note")}
            </T>
          </View>
        ) : (
          <T v="small" tone="ink2" style={{ paddingVertical: 18, textAlign: "center" }}>
            {t("calc.empty")}
          </T>
        )}
      </Card>

      <Button label={t("calc.ctaShop")} onPress={() => router.navigate("/categories")} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  field: { minHeight: 50, borderRadius: radius.sm, borderWidth: 1, paddingHorizontal: 14, fontSize: 16, fontWeight: "700" },
  rupee: { position: "absolute", left: 14, top: 13, zIndex: 1 },
  chip: { flexDirection: "row", alignItems: "center", gap: 6, minHeight: 44, paddingHorizontal: 14, borderRadius: 999, borderWidth: 1 },
  stepper: { minHeight: 50, borderRadius: radius.sm, borderWidth: 1, flexDirection: "row", alignItems: "center", justifyContent: "space-between", paddingHorizontal: 4 },
  step: { width: 44, height: 44, alignItems: "center", justifyContent: "center" },
  line: { flexDirection: "row", alignItems: "center", justifyContent: "space-between", gap: 12, paddingVertical: 10 },
});
