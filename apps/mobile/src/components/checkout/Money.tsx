// Checkout money module (mirrors apps/web/src/components/money/CheckoutMoney.tsx):
// card EMI option (feature "pay.emi"), store-credit wallet toggle (feature "wallet"), paid unboxing toggle
// (feature "unboxing") and the server quote (POST /checkout/quote) with Plus / wallet / EMI / unboxing lines.
import { useEffect, useState } from "react";
import { StyleSheet, View } from "react-native";
import Animated, { FadeIn, FadeOut } from "react-native-reanimated";
import { emiPlan, moneyApi, type EmiChoice, type EmiConfig, type MoneyQuote, type QuoteOptions } from "@/api/money";
import { isDemo } from "@/api/client";
import { useFeature } from "@/lib/features";
import { UNBOXING_FEE_BDT } from "@/api/fulfilment";
import { haptic } from "@/lib/haptics";
import { radius, useTheme } from "@/theme";
import { Card, Divider, IconChip, T, Tap } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { useT } from "@/i18n";
import type { CartItem } from "@/lib/types";
import type { ExtraLine } from "@/components/CartBits";

/** ON / OFF pill used by the option rows. */
function OnOff({ on }: { on: boolean }) {
  const { c } = useTheme();
  const { t } = useT();
  return (
    <View style={[styles.onoff, { backgroundColor: on ? c.brand : c.surface2 }]}>
      <T v="tiny" style={{ fontWeight: "800", color: on ? "#fff" : c.ink2 }}>
        {on ? t("f.on") : t("f.off")}
      </T>
    </View>
  );
}

/** GET /emi once (null while loading / when EMI is off). */
export function useEmiConfig(): EmiConfig | null {
  const on = useFeature("pay.emi");
  const [cfg, setCfg] = useState<EmiConfig | null>(null);
  useEffect(() => {
    if (!on) return;
    let live = true;
    moneyApi
      .emiConfig()
      .then((c) => live && setCfg(c))
      .catch(() => live && setCfg(null));
    return () => {
      live = false;
    };
  }, [on]);
  return on && cfg?.enabled && cfg.tenures.length ? cfg : null;
}

/** Payment step: "Card EMI" with tenure chips, bank chips and a monthly preview. */
export function EmiOption({ cfg, amount, value, onChange, disabled }: { cfg: EmiConfig; amount: number; value: EmiChoice | null; onChange: (v: EmiChoice | null) => void; disabled?: boolean }) {
  const { c } = useTheme();
  const { t, bdt, digits } = useT();
  const eligible = amount >= cfg.minOrderBdt;
  const active = !!value && eligible;
  const tenure = value?.tenure ?? cfg.tenures[Math.min(1, cfg.tenures.length - 1)];
  const banks = cfg.banks.filter((b) => b.rates[String(tenure)] !== undefined);
  const bank = value?.bank && banks.some((b) => b.code === value.bank) ? value.bank : (banks[0]?.code ?? "");
  const plan = emiPlan(cfg, amount, tenure, bank || null);

  return (
    <Card style={{ gap: 12, borderWidth: 2, borderColor: active ? c.brand : "transparent", opacity: eligible ? 1 : 0.6 }}>
      <Tap
        onPress={() => {
          if (!eligible || disabled) return;
          haptic.tap();
          onChange({ tenure, bank });
        }}
        disabled={!eligible || disabled}
        accessibilityRole="radio"
        accessibilityState={{ checked: active, disabled: !eligible }}
        accessibilityLabel={t("emi.label")}
        scaleTo={0.98}
        style={{ flexDirection: "row", gap: 12, alignItems: "center", minHeight: 44 }}
      >
        <IconChip icon="credit-card" />
        <View style={{ flex: 1 }}>
          <T v="h3" style={{ fontSize: 15 }}>{t("emi.label")}</T>
          <T v="tiny" tone="ink2">
            {eligible ? t("emi.payIn", { tenures: digits(cfg.tenures.join(", ")) }) : t("emi.availableOn", { amount: bdt(cfg.minOrderBdt) })}
          </T>
        </View>
        <View style={[styles.radio, { borderColor: active ? c.brand : c.ink3 }]}>{active && <View style={[styles.radioDot, { backgroundColor: c.brand }]} />}</View>
      </Tap>

      {active && (
        <Animated.View entering={FadeIn.duration(180)} exiting={FadeOut.duration(120)} style={{ gap: 10 }}>
          <Divider />
          <T v="tiny" tone="ink2" style={{ fontWeight: "700" }}>{t("emi.tenure")}</T>
          <View style={styles.chips} accessibilityRole="radiogroup" accessibilityLabel={t("emi.tenure")}>
            {cfg.tenures.map((n) => (
              <Chip key={n} on={n === tenure} label={t("emi.months", { count: n })} onPress={() => onChange({ tenure: n, bank })} />
            ))}
          </View>
          {banks.length > 0 && (
            <>
              <T v="tiny" tone="ink2" style={{ fontWeight: "700" }}>{t("emi.bank")}</T>
              <View style={styles.chips} accessibilityRole="radiogroup" accessibilityLabel={t("emi.bank")}>
                {banks.map((b) => (
                  <Chip
                    key={b.code}
                    on={b.code === bank}
                    label={`${b.name} · ${digits(b.rates[String(tenure)])}%`}
                    a11y={t("mx.emiBankA11y", { bank: b.name, pct: b.rates[String(tenure)] })}
                    onPress={() => onChange({ tenure, bank: b.code })}
                  />
                ))}
              </View>
            </>
          )}
          {plan.eligible ? (
            <View style={[styles.grid, { backgroundColor: c.surface2 }]}>
              {[
                [t("emi.monthly"), bdt(plan.monthlyBdt)],
                [t("emi.bankChargeLabel"), plan.chargeBdt ? bdt(plan.chargeBdt) : digits("0%")],
                [t("sum.total"), bdt(plan.totalBdt)],
              ].map(([k, v]) => (
                <View key={k} style={{ flex: 1, alignItems: "center" }}>
                  <T v="tiny" tone="ink3">{k}</T>
                  <T v="h3" style={{ fontVariant: ["tabular-nums"] }}>{v}</T>
                </View>
              ))}
            </View>
          ) : (
            <T v="tiny" tone="warn" style={{ fontWeight: "700" }}>
              {plan.reasonKey ? t(plan.reasonKey, { amount: bdt(cfg.minOrderBdt), bank: plan.bankName ?? "", count: tenure }) : ""}
            </T>
          )}
          <T v="tiny" tone="ink3">{t("emi.note", { pct: plan.ratePct })}</T>
        </Animated.View>
      )}
    </Card>
  );
}

function Chip({ on, label, onPress, a11y }: { on: boolean; label: string; onPress: () => void; a11y?: string }) {
  const { c } = useTheme();
  return (
    <Tap
      onPress={() => {
        haptic.tap();
        onPress();
      }}
      accessibilityRole="radio"
      accessibilityState={{ checked: on }}
      accessibilityLabel={a11y ?? label}
      style={[styles.chip, { backgroundColor: on ? c.brand : c.surface2 }]}
    >
      <T v="small" style={{ fontWeight: "700", color: on ? "#fff" : c.ink }}>
        {label}
      </T>
    </Tap>
  );
}

/** Delivery step: paid unboxing check at the Kolkata hub (feature "unboxing"). */
export function UnboxingToggle({ checked, onChange, shipments, disabled }: { checked: boolean; onChange: (v: boolean) => void; shipments: number; disabled?: boolean }) {
  const on = useFeature("unboxing");
  const { c } = useTheme();
  const { t, bdt } = useT();
  if (!on) return null;
  const fee = UNBOXING_FEE_BDT * Math.max(1, shipments);
  return (
    <Tap
      onPress={() => {
        haptic.tap();
        onChange(!checked);
      }}
      disabled={disabled}
      accessibilityRole="switch"
      accessibilityState={{ checked, disabled: !!disabled }}
      accessibilityLabel={t("ub.title")}
      scaleTo={0.98}
    >
      <Card style={{ flexDirection: "row", alignItems: "center", gap: 12, borderWidth: 2, borderColor: checked ? c.brand : "transparent" }}>
        <IconChip icon="package" />
        <View style={{ flex: 1 }}>
          <T v="h3" style={{ fontSize: 15 }}>{t("ub.title")}</T>
          <T v="tiny" tone="ink2">
            {t("ub.desc", { fee: bdt(fee) })}
            {shipments > 1 ? t("ub.perStore", { fee: bdt(UNBOXING_FEE_BDT), count: shipments }) : ""}
          </T>
        </View>
        <OnOff on={checked} />
      </Card>
    </Tap>
  );
}

/** Debounced server quote with the money options. Live API only (demo totals come from the cart maths). */
export function useMoneyQuote(items: CartItem[], o: QuoteOptions, enabled: boolean) {
  const [q, setQ] = useState<MoneyQuote | null>(null);
  const [failed, setFailed] = useState(false);
  const key =
    items.map((i) => `${i.productId}:${i.variant ?? ""}:${i.qty}:${i.unitBdt}`).join("|") +
    `|${o.coupon}|${o.redeemPoints}|${o.payment}|${o.emiTenure}|${o.emiBank}|${o.useWallet}|${o.unboxing}`;
  const active = enabled && items.length > 0 && !isDemo();
  useEffect(() => {
    if (!active) return;
    let live = true;
    const tm = setTimeout(() => {
      moneyApi
        .quote(
          items.map((i) => ({ productId: i.productId, variant: i.variant, qty: i.qty, unitBdt: i.unitBdt })),
          o,
        )
        .then((r) => {
          if (!live) return;
          setQ(r);
          setFailed(false);
        })
        .catch(() => {
          if (!live) return;
          setQ(null);
          setFailed(true);
        });
    }, 250);
    return () => {
      live = false;
      clearTimeout(tm);
    };
    // `key` captures every input
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, active]);
  return { quote: active ? q : null, failed: active && failed };
}

/** Server-quote lines the cart maths doesn't know (Plus, unboxing, bundles, EMI), for SummaryLines. */
export function quoteExtraLines(q: MoneyQuote | null, t: ReturnType<typeof useT>["t"]): ExtraLine[] {
  if (!q) return [];
  return [
    ...(q.membership?.lines ?? []).map((l) => ({ key: "plus-" + l.key, label: `Plus · ${l.label}`, amount: l.amountBdt, sign: "−" as const, good: true })),
    ...((q.bundleDiscount ?? 0) > 0 ? [{ key: "bundle", label: t("bundle.saving"), amount: q.bundleDiscount ?? 0, sign: "−" as const, good: true }] : []),
    ...((q.unboxingFee ?? 0) > 0 ? [{ key: "unboxing", label: t("ub.title"), amount: q.unboxingFee ?? 0, sign: "+" as const }] : []),
    ...(q.emiCharge > 0 && q.emi
      ? [{ key: "emi", label: t("wallet.emiCharge", { bank: q.emi.bankName ?? t("wallet.bank"), pct: q.emi.ratePct }), amount: q.emiCharge, sign: "+" as const }]
      : []),
  ];
}

/** Review step: store-credit wallet toggle and, when the wallet pays part of it, "Pay now". */
export function CheckoutMoney({ q, useWallet, setUseWallet, lock, methodLabel }: { q: MoneyQuote | null; useWallet: boolean; setUseWallet: (v: boolean) => void; lock?: boolean; methodLabel: string }) {
  const walletOn = useFeature("wallet");
  const { c } = useTheme();
  const { t, bdt } = useT();
  if (!q) return null;
  const balance = q.wallet?.balanceBdt ?? 0;
  return (
    <View style={{ gap: 12 }}>
      {walletOn && balance > 0 && (
        <Tap
          onPress={() => {
            if (lock) return;
            haptic.tap();
            setUseWallet(!useWallet);
          }}
          disabled={lock}
          accessibilityRole="switch"
          accessibilityState={{ checked: useWallet, disabled: !!lock }}
          accessibilityLabel={t("wallet.use")}
          scaleTo={0.98}
        >
          <Card style={{ flexDirection: "row", alignItems: "center", gap: 12, borderWidth: 2, borderColor: useWallet ? c.brand : "transparent" }}>
            <IconChip icon="credit-card" />
            <View style={{ flex: 1 }}>
              <T v="h3" style={{ fontSize: 15 }}>{t("wallet.use")}</T>
              <T v="tiny" tone="ink2">
                {useWallet && q.walletApplied > 0
                  ? t("wallet.applied", { amount: bdt(q.walletApplied), left: bdt(balance - q.walletApplied) })
                  : t("wallet.balance", { amount: bdt(balance) })}
              </T>
            </View>
            <OnOff on={useWallet} />
          </Card>
        </Tap>
      )}

      {q.walletApplied > 0 && (
        <Animated.View entering={FadeIn.duration(200)}>
          <Card style={{ gap: 8 }}>
            {q.membership && (
              <T v="tiny" tone="good" style={{ fontWeight: "800" }}>
                PLUS · {t("plus.saved", { amount: bdt(q.membership.savedBdt) })}
              </T>
            )}
            <View style={styles.row}>
              <T v="small" tone="ink2">{t("money.orderTotal")}</T>
              <T v="small" style={{ fontWeight: "700", fontVariant: ["tabular-nums"] }}>{bdt(q.total)}</T>
            </View>
            <View style={styles.row}>
              <View style={{ flexDirection: "row", alignItems: "center", gap: 6, flex: 1 }}>
                <Icon name="credit-card" size={14} color={c.good} />
                <T v="small" style={{ color: c.good }}>{t("wallet.name")}</T>
              </View>
              <T v="small" style={{ color: c.good, fontWeight: "700", fontVariant: ["tabular-nums"] }}>−{bdt(q.walletApplied)}</T>
            </View>
            <Divider style={{ marginVertical: 2 }} />
            <View style={styles.row}>
              <T v="h3">{t("money.payNow")}</T>
              <T v="h2" style={{ fontVariant: ["tabular-nums"] }}>{bdt(q.payNow)}</T>
            </View>
            <T v="tiny" tone="ink2">
              {q.payNow <= 0 ? t("mx.walletCovers") : t("mx.walletPartial", { amount: bdt(q.walletApplied), rest: bdt(q.payNow), method: methodLabel })}
            </T>
          </Card>
        </Animated.View>
      )}
      {q.membership && q.walletApplied === 0 && (
        <T v="tiny" tone="good" style={{ fontWeight: "800" }}>
          PLUS · {t("plus.saved", { amount: bdt(q.membership.savedBdt) })}
        </T>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  onoff: { paddingHorizontal: 10, paddingVertical: 5, borderRadius: 999 },
  radio: { width: 22, height: 22, borderRadius: 11, borderWidth: 2, alignItems: "center", justifyContent: "center" },
  radioDot: { width: 10, height: 10, borderRadius: 5 },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: { minHeight: 44, paddingHorizontal: 14, borderRadius: radius.md, alignItems: "center", justifyContent: "center" },
  grid: { flexDirection: "row", padding: 12, borderRadius: radius.md },
  row: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", gap: 10 },
});
