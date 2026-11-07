import { useState } from "react";
import { StyleSheet, TextInput, View, type StyleProp, type ViewStyle } from "react-native";
import Animated from "react-native-reanimated";
import { DEMO_COUPONS, couponFromApi, couponHint, couponText, findCoupon, type Totals } from "@/lib/cart";
import { useFeaturedCoupons } from "@/api/coupons";
import { api, ApiError } from "@/api/client";
import { useStore } from "@/state/store";
import { radius, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Icon } from "./Icon";
import { Button, Card, Divider, T } from "./ui";
import { useT } from "@/i18n";
import { enterFade } from "@/lib/motion";

/** `error`: the server's reason when the applied coupon no longer fits the cart (checkout quote). */
export function CouponField({ error }: { error?: string | null } = {}) {
  const tr = useT();
  const { t } = tr;
  const { c } = useTheme();
  const { coupon, setCoupon, cart, demo } = useStore();
  const featured = useFeaturedCoupons();
  const [text, setText] = useState("");
  const [err, setErr] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  // Demo mode knows only the two built-in codes; live codes (made in admin) are checked by the server.
  const hint = demo ? couponHint(text) : "empty";

  if (coupon)
    return (
      <Animated.View entering={enterFade} style={[styles.applied, { backgroundColor: c.dark ? "rgba(34,197,94,0.12)" : "#ecfdf3" }]}>
        <Icon name="tag" size={18} color={c.good} />
        <View style={{ flex: 1 }}>
          <T v="h3" style={{ fontSize: 14, color: c.good }}>
            {t("coupon.applied", { code: coupon.code })}
          </T>
          <T v="tiny" tone="ink2">
            {couponText(coupon, tr)}
            {coupon.firstOrderOnly ? " · " + t("coupon.firstOrder") : ""}
          </T>
          {!!error && (
            <T v="tiny" tone="bad">
              {t("coupon.notApplied", { reason: error })}
            </T>
          )}
        </View>
        <Button label={t("cart.remove")} kind="ghost" onPress={() => setCoupon("")} style={{ minHeight: 44, paddingHorizontal: 12 }} />
      </Animated.View>
    );

  const fail = (msg: string) => {
    haptic.error();
    setErr(msg);
  };
  const ok = (found: NonNullable<ReturnType<typeof findCoupon>>) => {
    haptic.success();
    setErr(null);
    setCoupon(found);
    setText("");
  };

  const apply = async () => {
    const code = text.trim().toUpperCase();
    if (!code || busy) return;
    if (demo) {
      const found = findCoupon(code);
      return found ? ok(found) : fail(t("m.couponInvalid"));
    }
    setBusy(true);
    try {
      const items = cart.map((i) => ({ productId: i.productId, variant: i.variant, qty: i.qty, unitBdt: i.unitBdt }));
      const q = await api.quote(items, code);
      const found = couponFromApi((q as { coupon?: unknown }).coupon);
      if (found) ok(found);
      else fail(t("mx.couponInvalid"));
      // Per-customer / first-order / store / schedule messages come back as 422 errors.coupon (shown below).
    } catch (e) {
      // 422 → the server's reason ("expired", "add ৳X more"…); network → generic retry message
      const reason = e instanceof ApiError ? (e.errors?.coupon?.[0] ?? (e.status === 422 ? e.message : null)) : null;
      fail(reason ?? t("mx.couponCheckFail"));
    } finally {
      setBusy(false);
    }
  };

  return (
    <View>
      <View style={{ flexDirection: "row", gap: 8 }}>
        <TextInput
          value={text}
          onChangeText={(v) => {
            setText(v.toUpperCase());
            setErr(null);
          }}
          placeholder={t("coupon.label")}
          placeholderTextColor={c.ink3}
          autoCapitalize="characters"
          autoCorrect={false}
          returnKeyType="done"
          onSubmitEditing={apply}
          accessibilityLabel={t("coupon.label")}
          style={[
            styles.input,
            { color: c.ink, backgroundColor: c.surface2, borderColor: hint === "match" ? c.good : hint === "unknown" ? c.bad : "transparent" },
          ]}
        />
        <Button
          label={busy ? "…" : t("coupon.apply")}
          kind="soft"
          onPress={apply}
          disabled={!text.trim() || busy}
          style={{ paddingHorizontal: 18 }}
        />
      </View>
      <T v="tiny" tone={err ? "bad" : "ink3"} style={{ marginTop: 6 }}>
        {err ??
          (demo
            ? t("coupon.tryDemo", { codes: Object.keys(DEMO_COUPONS).join(", ") })
            : featured.length
              ? t("coupon.tryFeatured", { codes: featured.slice(0, 2).map((x) => x.code).join(", ") })
              : t("mx.couponHint"))}
      </T>
    </View>
  );
}

/** Admin-featured coupons (GET /coupons/featured) for the home screen and cart; renders nothing when none is live. */
export function CouponPromo({ store = "all", style }: { store?: "all" | "amazon" | "flipkart" | "bigbasket"; style?: StyleProp<ViewStyle> }) {
  const tr = useT();
  const { c } = useTheme();
  const items = useFeaturedCoupons(store).slice(0, 2);
  if (!items.length) return null;
  return (
    <View style={[{ gap: 8 }, style]}>
      {items.map((cp) => (
        <View key={cp.code} style={[styles.promo, { backgroundColor: c.surface2 }]} accessibilityRole="text">
          <Icon name="tag" size={18} color={c.brand} />
          <View style={{ flex: 1 }}>
            <T v="h3" style={{ fontSize: 14 }}>
              {cp.code}
            </T>
            <T v="tiny" tone="ink2">
              {couponText(cp, tr)}
              {cp.firstOrderOnly ? " · " + tr.t("coupon.firstOrder") : ""}
            </T>
          </View>
        </View>
      ))}
    </View>
  );
}

export type ExtraLine = { key: string; label: string; amount: number; sign: "+" | "−"; good?: boolean };

/** Order summary. `extra` / `total` / `earn` come from the server quote at checkout (unboxing, Plus, bundles, EMI). */
export function SummaryLines({ t: tot, extra = [], total, earn }: { t: Totals; extra?: ExtraLine[]; total?: number; earn?: number }) {
  const { c } = useTheme();
  const { t, bdt } = useT();
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
      {row(t("sum.items", { count: tot.count }), bdt(tot.subtotal))}
      {row(t("m.shipStores", { count: tot.groups.length }), tot.shipping ? bdt(tot.shipping) : t("common.free"))}
      {tot.itemDiscount > 0 && row(t("m.couponDiscount"), "-" + bdt(tot.itemDiscount), c.good)}
      {tot.shipDiscount > 0 && row(t("m.freeShipCoupon"), "-" + bdt(tot.shipDiscount), c.good)}
      {tot.pointsDiscount > 0 && row(t("sum.points", { count: tot.pointsRedeemed }), "-" + bdt(tot.pointsDiscount), c.good)}
      {extra.map((l) => (
        <View key={l.key}>{row(l.label, (l.sign === "−" ? "-" : "+") + bdt(l.amount), l.good ? c.good : undefined)}</View>
      ))}
      <Divider style={{ marginVertical: 4 }} />
      {row(t("sum.total"), bdt(total ?? tot.total), undefined, true)}
      {(earn ?? tot.pointsToEarn) > 0 && (
        <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
          <Icon name="star" size={13} color={c.brand} />
          <T v="tiny" style={{ color: c.brand, fontWeight: "800" }}>
            {t("sum.earn", { count: earn ?? tot.pointsToEarn })}
          </T>
        </View>
      )}
      <T v="tiny" tone="ink3">{t("m.dutyIncluded")}</T>
    </Card>
  );
}

const styles = StyleSheet.create({
  input: { flex: 1, minWidth: 0, minHeight: 50, borderRadius: radius.md, paddingHorizontal: 14, fontSize: 15, fontWeight: "700", letterSpacing: 1, borderWidth: 1.5 },
  applied: { flexDirection: "row", alignItems: "center", gap: 10, padding: 12, borderRadius: radius.md },
  promo: { flexDirection: "row", alignItems: "center", gap: 10, padding: 12, borderRadius: radius.md },
  row: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
});
