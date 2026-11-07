import { useCallback, useEffect, useRef, useState } from "react";
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View, type ScrollViewInstance } from "react-native";
import { router, useFocusEffect } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Animated, { FadeInRight, FadeOutLeft } from "react-native-reanimated";
import * as WebBrowser from "expo-web-browser";
import { api, ApiError, isDemo } from "@/api/client";
import type { EmiChoice } from "@/api/money";
import { useFeature } from "@/lib/features";
import { CheckoutMoney, EmiOption, UnboxingToggle, quoteExtraLines, useEmiConfig, useMoneyQuote } from "@/components/checkout/Money";
import type { PaymentMethod } from "@/api/types";
import { useStore } from "@/state/store";
import type { Address, CartItem } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { deliveryWindow } from "@/lib/pricing";
import { addDays, cartKey, computeTotals } from "@/lib/cart";
import { maxRedeem } from "@/lib/points";
import { prettyPhone } from "@/lib/phone";
import { sized } from "@/lib/variants";
import { radius, useTheme, withAlpha } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Button, Card, Empty, IconChip, Pill, ProductImage, T, Tap } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { AddressForm, addressValid, emptyAddress, type AddressDraft } from "@/components/AddressForm";
import { CouponField, SummaryLines } from "@/components/CartBits";
import { Stepper, VanDrive, VerifyPanel, type CheckState } from "@/components/checkout/Bits";
import { useToast } from "@/components/Toast";
import { useT } from "@/i18n";
import type { MsgKey, TFn } from "@/i18n";
import { bdName } from "@/lib/bd";

// label/desc are dictionary keys (bKash and Nagad are brand names and stay as they are).
// Each method is behind its admin switch (pay.bkash, pay.nagad, pay.card, pay.advance50; card EMI is pay.emi).
const PAYMENTS: { id: Exclude<PaymentMethod, "card_emi">; feature: string; label: MsgKey | "bKash" | "Nagad"; icon: string; desc: MsgKey }[] = [
  { id: "bkash", feature: "pay.bkash", label: "bKash", icon: "smartphone", desc: "pay.bkashDesc" },
  { id: "nagad", feature: "pay.nagad", label: "Nagad", icon: "smartphone", desc: "pay.nagadDesc" },
  { id: "card", feature: "pay.card", label: "pay.cardLabel", icon: "credit-card", desc: "pay.cardDesc" },
  { id: "advance50", feature: "pay.advance50", label: "pay.halfLabel", icon: "dollar-sign", desc: "pay.halfDesc" },
];
const payLabel = (l: (typeof PAYMENTS)[number]["label"], t: TFn) => (l === "bKash" || l === "Nagad" ? l : t(l));

type Phase = "idle" | "verifying" | "placing";

const RETURN_URL = "skydropretail://checkout/success";

export default function Checkout() {
  const { t, bdt, lang } = useT();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const toast = useToast();
  const { cart, totals: baseTotals, coupon, setCoupon, user, demo, clearCart, removeItem, addToCart } = useStore();
  const [usePoints, setUsePoints] = useState(false);
  const [pointsBalance, setPointsBalance] = useState(0);
  const redeemable = maxRedeem(pointsBalance, baseTotals.subtotal);
  const totals = usePoints && redeemable.points > 0 ? computeTotals(cart, coupon, redeemable.points) : baseTotals;
  const [step, setStep] = useState(0);
  const [addresses, setAddresses] = useState<Address[]>([]);
  const [addressId, setAddressId] = useState<string | null>(null);
  const [draft, setDraft] = useState<AddressDraft | null>(null);
  const payOn = { bkash: useFeature("pay.bkash"), nagad: useFeature("pay.nagad"), card: useFeature("pay.card"), advance50: useFeature("pay.advance50") };
  const methods = PAYMENTS.filter((p) => payOn[p.id]);
  const [picked, setPayment] = useState<Exclude<PaymentMethod, "card_emi">>("bkash");
  // A switched-off method falls back to the first one still on.
  const payment = methods.some((m) => m.id === picked) ? picked : (methods[0]?.id ?? picked);
  // money module: card EMI (instead of a method), store-credit wallet, paid unboxing
  // 50% advance: which gateway collects the advance (paymentVia); the rider collects the rest.
  const viaOptions = methods.filter((m): m is (typeof PAYMENTS)[number] & { id: "bkash" | "nagad" | "card" } => m.id !== "advance50");
  const [viaPicked, setVia] = useState<"bkash" | "nagad" | "card">("bkash");
  const via = viaOptions.some((m) => m.id === viaPicked) ? viaPicked : (viaOptions[0]?.id ?? "bkash");
  const emiCfg = useEmiConfig();
  const [emi, setEmi] = useState<EmiChoice | null>(null);
  const [useWallet, setUseWallet] = useState(false);
  const [unboxing, setUnboxing] = useState(false);
  const unboxingOn = useFeature("unboxing");
  const emiActive = !!emi && !!emiCfg && baseTotals.total >= emiCfg.minOrderBdt;
  const { quote, failed: quoteFailed } = useMoneyQuote(
    cart,
    {
      coupon: coupon?.code,
      redeemPoints: usePoints ? redeemable.points : 0,
      useWallet,
      payment: emiActive ? "card_emi" : payment,
      emiTenure: emiActive ? emi!.tenure : undefined,
      emiBank: emiActive ? emi!.bank : undefined,
      unboxing: unboxingOn && unboxing,
    },
    !!user && step >= 2,
  );
  const [phase, setPhase] = useState<Phase>("idle");
  const [checks, setChecks] = useState<CheckState[]>(["wait", "wait", "wait"]);
  const [changed, setChanged] = useState<{ item: CartItem; fresh: number }[]>([]);
  const [failed, setFailed] = useState<CartItem[]>([]);
  const [verifyError, setVerifyError] = useState<string | null>(null);
  const [verifiedSig, setVerifiedSig] = useState<string | null>(null);
  const scroll = useRef<ScrollViewInstance>(null);

  const loadAddresses = useCallback(() => {
    if (!user && !(demo && isDemo())) return; // `demo` state starts true until the API answers; the client knows
    api
      .addresses()
      .then((list) => {
        setAddresses(list);
        setAddressId((cur) => cur ?? list.find((a) => a.isDefault)?.id ?? list[0]?.id ?? null);
        if (!list.length) setDraft((d) => d ?? emptyAddress(user?.name, user?.phone));
      })
      .catch(() => {});
  }, [user, demo]);
  useFocusEffect(loadAddresses);

  useFocusEffect(
    useCallback(() => {
      if (!user && !(demo && isDemo())) return; // `demo` state starts true until the API answers; the client knows
      api
        .points()
        .then((p) => setPointsBalance(p.balance))
        .catch(() => setPointsBalance(0));
    }, [user, demo]),
  );

  // Any cart change invalidates a previous verification.
  const cartSig = cart.map((i) => cartKey(i) + i.qty + ":" + i.unitBdt).join(",");
  const verified = verifiedSig === cartSig;

  useEffect(() => {
    scroll.current?.scrollTo({ y: 0, animated: true });
  }, [step]);

  if (!cart.length && phase === "idle")
    return <Empty icon="shopping-cart" title={t("m.cartEmpty")} text={t("m.addFirst")} action={t("hero.start")} onAction={() => router.navigate("/")} />;

  if (!user && !demo)
    return <Empty icon="user" title={t("m.signInCheckout")} text={t("m.phoneUpdates")} action={t("nav.signIn")} onAction={() => router.push({ pathname: "/login", params: { next: "/checkout" } })} />;

  const address = addresses.find((a) => a.id === addressId) ?? null;
  const w = deliveryWindow();
  const now = new Date().toISOString();

  async function saveDraft() {
    if (!draft || !addressValid(draft)) return;
    try {
      const a = await api.saveAddress({ ...draft, isDefault: addresses.length === 0 || draft.isDefault });
      haptic.success();
      setDraft(null);
      setAddressId(a.id);
      loadAddresses();
    } catch (e) {
      toast({ kind: "error", title: t("m.saveAddrFailed"), body: (e as Error).message });
    }
  }

  async function runVerify() {
    setPhase("verifying");
    setVerifyError(null);
    setChanged([]);
    setFailed([]);
    setChecks(["run", "wait", "wait"]);
    try {
      const items = cart.map((i) => ({ productId: i.productId, variant: i.variant, qty: i.qty, unitBdt: i.unitBdt, weightKg: i.weightKg }));
      const r = await api.verify(items);
      const byId = new Map(r.results.map((x) => [x.productId, x]));
      const ch = cart.filter((i) => byId.get(i.productId)?.ok && byId.get(i.productId)?.changed).map((item) => ({ item, fresh: byId.get(item.productId)!.freshBdt! }));
      const bad = cart.filter((i) => !byId.get(i.productId)?.ok);
      const undeliverable = cart.filter((i) => byId.get(i.productId)?.ok && byId.get(i.productId)?.deliverable === false);
      setChecks([ch.length ? "warn" : "ok", "run", "wait"]);
      await new Promise((res) => setTimeout(res, 350));
      setChecks([ch.length ? "warn" : "ok", bad.length ? "fail" : "ok", "run"]);
      await new Promise((res) => setTimeout(res, 350));
      setChecks([ch.length ? "warn" : "ok", bad.length ? "fail" : "ok", undeliverable.length ? "fail" : "ok"]);
      // Apply fresh prices to the cart so totals match what the server will charge.
      for (const { item, fresh } of ch) {
        removeItem(cartKey(item));
        addToCart({ ...item, unitBdt: fresh });
      }
      setChanged(ch);
      if (bad.length || undeliverable.length) {
        haptic.error();
        setFailed([...bad, ...undeliverable]);
        setPhase("idle");
        return false;
      }
      haptic.success();
      // Signature of the cart after fresh prices are applied, so later edits invalidate it.
      const fresh = new Map(ch.map((x) => [cartKey(x.item), x.fresh]));
      setVerifiedSig(cart.map((i) => cartKey(i) + i.qty + ":" + (fresh.get(cartKey(i)) ?? i.unitBdt)).join(","));
      setPhase("idle");
      return ch.length === 0;
    } catch (e) {
      haptic.error();
      setChecks(["fail", "wait", "wait"]);
      setVerifyError((e as Error).message);
      setPhase("idle");
      return false;
    }
  }

  async function place() {
    if (!address) return;
    let ok = verified;
    if (!ok) ok = await runVerify();
    if (!ok) return; // price changes need a second look
    setPhase("placing");
    try {
      const r = await api.placeOrder(
        {
          items: cart.map((i) => ({ productId: i.productId, variant: i.variant, qty: i.qty, unitBdt: i.unitBdt, weightKg: i.weightKg })),
          addressId: address.id,
          payment: emiActive ? "card_emi" : payment,
          coupon: coupon?.code,
          redeemPoints: totals.pointsRedeemed || undefined,
          // The API appends ?order=<id>; the app handles it at /checkout/success.
          returnUrl: RETURN_URL,
          paymentVia: payment === "advance50" && !emiActive ? via : undefined,
          useWallet: useWallet || undefined,
          emiTenure: emiActive ? emi!.tenure : undefined,
          emiBank: emiActive ? emi!.bank : undefined,
          unboxing: unboxingOn && unboxing ? true : undefined,
        },
        address,
      );
      clearCart();
      setCoupon("");
      const order = r.order;
      if (r.payment.redirectUrl) {
        // Gateway page in an auth session; it closes itself when it reaches the return URL.
        const res = await WebBrowser.openAuthSessionAsync(r.payment.redirectUrl, RETURN_URL);
        let fresh = order;
        try {
          fresh = await api.order(order.id);
        } catch {
          /* show what we have */
        }
        // "partial" = the 50% advance was paid (the rider collects the rest).
        if (res.type === "success" || fresh.paymentStatus === "paid" || fresh.paymentStatus === "partial") {
          haptic.success();
          router.replace({ pathname: "/success", params: { id: fresh.id, total: String(fresh.total), paid: fresh.paymentStatus ?? "" } });
        } else {
          haptic.warn();
          toast({ kind: "info", title: t("m.payNotFinished"), body: t("m.payNotFinishedBody") });
          router.replace(`/order/${order.id}`);
        }
        return;
      }
      haptic.success();
      router.replace({ pathname: "/success", params: { id: order.id, total: String(order.total), paid: r.payment.status === "paid" ? "paid" : (order.paymentStatus ?? "") } });
    } catch (e) {
      haptic.error();
      const err = e instanceof ApiError ? e : null;
      const prices = (err?.data?.prices as { productId: string; variant?: string | null; oldBdt: number; freshBdt: number }[] | undefined) ?? [];
      if (err?.status === 409 && prices.length) {
        // Store prices moved since verification: reprice the cart and let the shopper confirm again.
        for (const pr of prices) {
          const line = cart.find((i) => i.productId === pr.productId && (i.variant ?? null) === (pr.variant ?? null)) ?? cart.find((i) => i.productId === pr.productId);
          if (line) {
            removeItem(cartKey(line));
            addToCart({ ...line, unitBdt: pr.freshBdt });
          }
        }
        setVerifiedSig(null);
        const up = prices.some((x) => x.freshBdt > x.oldBdt);
        toast({ kind: "error", title: t("m.placeFailed"), body: t("co.pricesChanged", { count: prices.length }) + (up ? t("co.someUp") : "") + t("co.cartUpdated") });
      } else {
        toast({ kind: "error", title: t("m.placeFailed"), body: err?.status === 409 ? t("co.pricesMoved") : (e as Error).message });
      }
      setPhase("idle");
    }
  }

  const canNext = step === 0 ? !!address && !draft : step === 2 ? methods.length > 0 || emiActive : true;
  // Server total (with unboxing / Plus / EMI) once the quote is in, else the cart maths.
  const payTotal = quote?.total ?? totals.total;
  const viaLabel = (id: "bkash" | "nagad" | "card") => (id === "bkash" ? "bKash" : id === "nagad" ? "Nagad" : t("pay.card"));
  const methodText = emiActive
    ? t("co.cardEmi", { count: emi!.tenure })
    : payment === "advance50"
      ? t("pay.halfSummary", { now: bdt(Math.ceil(payTotal / 2)), later: bdt(payTotal - Math.ceil(payTotal / 2)) }) + (viaOptions.length > 1 ? ` · ${viaLabel(via)}` : "")
      : payLabel((PAYMENTS.find((p) => p.id === payment) ?? PAYMENTS[0]).label, t);

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.bg }} behavior={Platform.OS === "ios" ? "padding" : undefined} keyboardVerticalOffset={Platform.OS === "ios" ? 100 : 0}>
      <ScrollView ref={scroll} contentContainerStyle={{ padding: 16, paddingBottom: 140, gap: 14 }} keyboardShouldPersistTaps="handled">
        <Stepper step={step} onJump={setStep} />
        {demo && (
          <View style={[styles.demo, { backgroundColor: withAlpha(c.warn, 0.12) }]}>
            <Icon name="info" size={16} color={c.warn} />
            <T v="tiny" style={{ flex: 1, color: c.ink }}>{t("m.demoCheckout")}</T>
          </View>
        )}

        <Animated.View key={step} entering={FadeInRight.duration(220)} exiting={FadeOutLeft.duration(150)} style={{ gap: 14 }}>
          <T v="h2" accessibilityRole="header">
            {t((["co.h.address", "co.h.delivery", "co.h.payment", "co.h.review"] as const)[step])}
          </T>

          {step === 0 && (
            <>
              {addresses.map((a) => {
                const on = a.id === addressId && !draft;
                return (
                  <Tap
                    key={a.id}
                    onPress={() => {
                      haptic.tap();
                      setDraft(null);
                      setAddressId(a.id);
                    }}
                    accessibilityRole="radio"
                    accessibilityState={{ checked: on }}
                    accessibilityLabel={`${a.name}, ${a.area}, ${bdName(a.district, lang)}`}
                    scaleTo={0.98}
                  >
                    <Card style={[{ flexDirection: "row", gap: 12, borderWidth: 2, borderColor: on ? c.brand : "transparent" }]}>
                      <View style={[styles.radio, { borderColor: on ? c.brand : c.ink3 }]}>{on && <View style={[styles.radioDot, { backgroundColor: c.brand }]} />}</View>
                      <View style={{ flex: 1 }}>
                        <T v="h3" style={{ fontSize: 15 }}>
                          {a.name}
                        </T>
                        <T v="small" tone="ink2">
                          {prettyPhone(a.phone)}
                        </T>
                        <T v="small" tone="ink2">
                          {a.line}, {a.area}, {bdName(a.district, lang)}, {bdName(a.division, lang)}
                        </T>
                      </View>
                    </Card>
                  </Tap>
                );
              })}
              {draft ? (
                <Card style={{ gap: 14 }}>
                  <AddressForm value={draft} onChange={setDraft} />
                  <Button label={t("m.saveUseAddr")} icon="check" onPress={saveDraft} disabled={!addressValid(draft)} />
                  {addresses.length > 0 && <Button label={t("common.cancel")} kind="ghost" onPress={() => setDraft(null)} />}
                </Card>
              ) : (
                <Button label={t("addr.addNew")} kind="soft" icon="plus" onPress={() => setDraft(emptyAddress(user?.name, user?.phone))} />
              )}
            </>
          )}

          {step === 1 && (
            <>
              <T v="small" tone="ink2">{t("del.intro")}</T>
              {totals.groups.map((g) => {
                const free = g.shipping === 0 || coupon?.kind === "freeship";
                return (
                  <Card key={g.market} style={{ flexDirection: "row", gap: 12, alignItems: "center" }}>
                    <IconChip icon="truck" mode={g.market} />
                    <View style={{ flex: 1 }}>
                      <T v="tiny" style={{ fontWeight: "700", color: c.ink2 }}>
                        {t("del.fromLine", { source: STORES[g.market].source, items: t("cart.items", { count: g.count }) })}
                      </T>
                      <T v="h3" style={{ fontSize: 14 }}>{t("del.standard")}</T>
                      <T v="tiny" tone="ink2">
                        {t("del.arrives", { from: addDays(now, w.min, lang), to: addDays(now, w.max, lang), min: w.min, max: w.max })}
                      </T>
                    </View>
                    <View style={{ alignItems: "flex-end" }}>
                      <T v="small" style={{ fontWeight: "800", color: free ? c.good : c.ink }}>
                        {free ? t("common.free") : bdt(g.shipping)}
                      </T>
                      {free && g.shipping > 0 && (
                        <T v="tiny" tone="ink3" style={{ textDecorationLine: "line-through" }}>
                          {bdt(g.shipping)}
                        </T>
                      )}
                    </View>
                  </Card>
                );
              })}
              <View style={{ flexDirection: "row", gap: 8 }}>
                {[
                  ["shopping-bag", t("del.bought"), t("del.d13")],
                  ["send", t("del.hubToBd"), t("del.d35")],
                  ["home", t("del.toDoor"), t("del.d13")],
                ].map(([i, a, b]) => (
                  <Card key={a} pad={10} style={{ flex: 1, alignItems: "center", gap: 4 }}>
                    <Icon name={i} size={18} color={c.brand} />
                    <T v="tiny" style={{ fontWeight: "700", textAlign: "center" }}>
                      {a}
                    </T>
                    <T v="tiny" tone="ink2">
                      {b}
                    </T>
                  </Card>
                ))}
              </View>
              {!demo && <UnboxingToggle checked={unboxing} onChange={setUnboxing} shipments={totals.groups.length} disabled={phase !== "idle"} />}
            </>
          )}

          {step === 2 && (
            <>
              {methods.map((p) => {
                const on = payment === p.id && !emiActive;
                return (
                  <Tap
                    key={p.id}
                    onPress={() => {
                      haptic.tap();
                      setEmi(null);
                      setPayment(p.id);
                    }}
                    accessibilityRole="radio"
                    accessibilityState={{ checked: on }}
                    accessibilityLabel={payLabel(p.label, t)}
                    scaleTo={0.98}
                  >
                    <Card style={{ flexDirection: "row", gap: 12, alignItems: "center", borderWidth: 2, borderColor: on ? c.brand : "transparent" }}>
                      <IconChip icon={p.icon} />
                      <View style={{ flex: 1 }}>
                        <T v="h3" style={{ fontSize: 15 }}>
                          {payLabel(p.label, t)}
                        </T>
                        <T v="tiny" tone="ink2">
                          {t(p.desc)}
                        </T>
                        {p.id === "advance50" && (
                          <T v="tiny" style={{ color: c.brand, fontWeight: "700", marginTop: 2 }}>
                            {t("pay.halfNow", { now: bdt(Math.ceil(payTotal / 2)), later: bdt(payTotal - Math.ceil(payTotal / 2)) })}
                          </T>
                        )}
                      </View>
                      <View style={[styles.radio, { borderColor: on ? c.brand : c.ink3 }]}>{on && <View style={[styles.radioDot, { backgroundColor: c.brand }]} />}</View>
                    </Card>
                  </Tap>
                );
              })}
              {payment === "advance50" && !emiActive && viaOptions.length > 1 && (
                <Animated.View entering={FadeInRight.duration(180)} style={{ gap: 8 }}>
                  <T v="tiny" tone="ink2" style={{ fontWeight: "700" }}>{t("mx.advanceVia")}</T>
                  <View style={{ flexDirection: "row", gap: 8 }} accessibilityRole="radiogroup" accessibilityLabel={t("mx.advanceVia")}>
                    {viaOptions.map((v) => {
                      const on = via === v.id;
                      return (
                        <Tap
                          key={v.id}
                          onPress={() => {
                            haptic.tap();
                            setVia(v.id);
                          }}
                          accessibilityRole="radio"
                          accessibilityState={{ checked: on }}
                          accessibilityLabel={viaLabel(v.id)}
                          style={[styles.via, { backgroundColor: on ? c.brand : c.surface2 }]}
                        >
                          <T v="small" style={{ fontWeight: "700", color: on ? "#fff" : c.ink }}>{viaLabel(v.id)}</T>
                        </Tap>
                      );
                    })}
                  </View>
                </Animated.View>
              )}
              {emiCfg && !demo && (
                <EmiOption
                  cfg={emiCfg}
                  amount={totals.total}
                  value={emi}
                  disabled={phase !== "idle"}
                  onChange={(v) => {
                    setEmi(v);
                    if (v) setPayment("card");
                  }}
                />
              )}
              <T v="tiny" tone="ink3">
                {demo ? t("m.payDemo") : t("m.payNext")}
              </T>
            </>
          )}

          {step === 3 && (
            <>
              <Card style={{ gap: 10 }}>
                {[
                  { icon: "map-pin", title: t("co.deliverTo"), text: address ? `${address.name}, ${address.line}, ${address.area}, ${bdName(address.district, lang)}` : "-", s: 0 },
                  { icon: "truck", title: t("co.h.delivery"), text: t("m.doorDays", { min: w.min, max: w.max }), s: 1 },
                  { icon: "credit-card", title: t("co.payment"), text: methodText, s: 2 },
                ].map((r) => (
                  <View key={r.title} style={{ flexDirection: "row", gap: 10, alignItems: "center" }}>
                    <IconChip icon={r.icon} size={32} />
                    <View style={{ flex: 1 }}>
                      <T v="tiny" tone="ink3">
                        {r.title}
                      </T>
                      <T v="small" numberOfLines={2} style={{ fontWeight: "600" }}>
                        {r.text}
                      </T>
                    </View>
                    <Button label={t("co.change")} kind="ghost" onPress={() => setStep(r.s)} style={{ minHeight: 44, paddingHorizontal: 12 }} />
                  </View>
                ))}
              </Card>
              <Card style={{ gap: 10 }}>
                {cart.map((i) => (
                  <View key={cartKey(i)} style={{ flexDirection: "row", gap: 10, alignItems: "center" }}>
                    <ProductImage uri={sized(i.image, "SL160")} placeholder={i.placeholder} size={48} style={{ borderRadius: 10 }} />
                    <View style={{ flex: 1 }}>
                      <T v="small" numberOfLines={1} style={{ fontWeight: "600" }}>
                        {i.title}
                      </T>
                      <T v="tiny" tone="ink2">
                        {t("m.itemLine", { source: STORES[i.market].source, qty: i.qty })}
                        {i.variant ? ` · ${i.variant}` : ""}
                      </T>
                    </View>
                    <T v="small" style={{ fontWeight: "800" }}>
                      {bdt(i.unitBdt * i.qty)}
                    </T>
                  </View>
                ))}
              </Card>
              {pointsBalance >= 100 && (
                <Tap
                  onPress={() => {
                    if (redeemable.points === 0) return;
                    haptic.tap();
                    setUsePoints(!usePoints);
                  }}
                  disabled={redeemable.points === 0 || phase !== "idle"}
                  accessibilityRole="switch"
                  accessibilityState={{ checked: usePoints, disabled: redeemable.points === 0 }}
                  accessibilityLabel={t("m.usePointsA11y", { count: redeemable.points, amount: bdt(redeemable.valueBdt) })}
                  scaleTo={0.98}
                >
                  <Card style={{ flexDirection: "row", alignItems: "center", gap: 12, borderWidth: 2, borderColor: usePoints ? c.brand : "transparent" }}>
                    <IconChip icon="star" />
                    <View style={{ flex: 1 }}>
                      <T v="h3" style={{ fontSize: 15 }}>
                        {t("co.usePoints", { count: redeemable.points })}
                      </T>
                      <T v="tiny" tone="ink2">
                        {redeemable.points > 0 ? t("co.pointsSave", { amount: bdt(redeemable.valueBdt), count: pointsBalance }) : t("m.pointsHave", { count: pointsBalance })}
                      </T>
                    </View>
                    <View style={[styles.onoff, { backgroundColor: usePoints ? c.brand : c.surface2 }]}>
                      <T v="tiny" style={{ fontWeight: "800", color: usePoints ? "#fff" : c.ink2 }}>
                        {usePoints ? t("f.on") : t("f.off")}
                      </T>
                    </View>
                  </Card>
                </Tap>
              )}
              <Card>
                <CouponField />
              </Card>
              {(phase === "verifying" || checks.some((s) => s !== "wait")) && <VerifyPanel states={checks} changed={changed} failed={failed} error={verifyError} />}
              {failed.length > 0 && (
                <Button
                  label={t("m.removeUnavail")}
                  kind="danger"
                  onPress={() => {
                    failed.forEach((i) => removeItem(cartKey(i)));
                    setFailed([]);
                    setChecks(["wait", "wait", "wait"]);
                  }}
                />
              )}
              <SummaryLines t={totals} extra={quoteExtraLines(quote, t)} total={quote?.total} earn={quote?.pointsToEarn} />
              <CheckoutMoney q={quote} useWallet={useWallet} setUseWallet={setUseWallet} lock={phase !== "idle"} methodLabel={methodText} />
              {quoteFailed && !demo && <T v="tiny" tone="ink3">{t("mx.quoteFail")}</T>}
              <T v="tiny" tone="ink3">{t("m.agreeShort")}</T>
            </>
          )}
        </Animated.View>
      </ScrollView>

      <View style={[styles.bar, { backgroundColor: c.surface, borderTopColor: c.line, paddingBottom: Math.max(insets.bottom, 12) }]}>
        {phase !== "idle" ? (
          <VanDrive label={phase === "verifying" ? t("co.verifying") : t("van.placing")} />
        ) : (
          <View style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
            <View style={{ flex: 1 }}>
              <T v="tiny" tone="ink3">{quote && quote.payNow !== quote.total ? t("money.payNow") : t("sum.total")}</T>
              <T v="h2">{bdt(quote ? quote.payNow : totals.total)}</T>
              {verified && step === 3 && <Pill text={t("st.verified.short")} icon="check" color={c.good} bg={withAlpha(c.good, 0.12)} />}
            </View>
            {step < 3 ? (
              <Button
                label={t("common.continue")}
                icon="arrow-right"
                disabled={!canNext}
                onPress={() => {
                  haptic.light();
                  setStep(step + 1);
                }}
                style={{ flex: 1.4 }}
              />
            ) : (
              <Button label={verified ? t("m.placeOrder") : t("m.verifyPlace")} kind="coral" icon="shield" onPress={place} disabled={!address || failed.length > 0} style={{ flex: 1.8 }} />
            )}
          </View>
        )}
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  demo: { flexDirection: "row", gap: 8, alignItems: "flex-start", padding: 12, borderRadius: radius.md },
  radio: { width: 22, height: 22, borderRadius: 11, borderWidth: 2, alignItems: "center", justifyContent: "center", marginTop: 2 },
  radioDot: { width: 10, height: 10, borderRadius: 5 },
  via: { flex: 1, minHeight: 44, borderRadius: radius.md, alignItems: "center", justifyContent: "center", paddingHorizontal: 8 },
  onoff: { paddingHorizontal: 10, paddingVertical: 5, borderRadius: 999 },
  bar: { position: "absolute", left: 0, right: 0, bottom: 0, paddingHorizontal: 16, paddingTop: 12, borderTopWidth: StyleSheet.hairlineWidth },
});
