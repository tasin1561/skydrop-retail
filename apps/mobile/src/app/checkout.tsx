import { useCallback, useEffect, useRef, useState } from "react";
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View, type ScrollViewInstance } from "react-native";
import { router, useFocusEffect } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Animated, { FadeInRight, FadeOutLeft } from "react-native-reanimated";
import { api } from "@/api/client";
import type { PaymentMethod } from "@/api/types";
import { useStore } from "@/state/store";
import type { Address, CartItem } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { deliveryWindow, formatBdt } from "@/lib/pricing";
import { addDays, cartKey } from "@/lib/cart";
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

const PAYMENTS: { id: PaymentMethod; label: string; icon: string; desc: string }[] = [
  { id: "bkash", label: "bKash", icon: "smartphone", desc: "Pay from your bKash account" },
  { id: "nagad", label: "Nagad", icon: "smartphone", desc: "Pay from your Nagad account" },
  { id: "card", label: "Card (SSLCommerz)", icon: "credit-card", desc: "Visa, Mastercard, Amex via SSLCommerz" },
  { id: "advance50", label: "Pay 50% advance, rest on delivery", icon: "dollar-sign", desc: "Half now, the rest to the Skydrop rider" },
];

type Phase = "idle" | "verifying" | "placing";

export default function Checkout() {
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const toast = useToast();
  const { cart, totals, coupon, user, demo, clearCart, setQty, removeItem, addToCart } = useStore();
  const [step, setStep] = useState(0);
  const [addresses, setAddresses] = useState<Address[]>([]);
  const [addressId, setAddressId] = useState<string | null>(null);
  const [draft, setDraft] = useState<AddressDraft | null>(null);
  const [payment, setPayment] = useState<PaymentMethod>("bkash");
  const [phase, setPhase] = useState<Phase>("idle");
  const [checks, setChecks] = useState<CheckState[]>(["wait", "wait", "wait"]);
  const [changed, setChanged] = useState<{ item: CartItem; fresh: number }[]>([]);
  const [failed, setFailed] = useState<CartItem[]>([]);
  const [verifyError, setVerifyError] = useState<string | null>(null);
  const [verified, setVerified] = useState(false);
  const scroll = useRef<ScrollViewInstance>(null);

  const loadAddresses = useCallback(() => {
    if (!user && !demo) return;
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

  // Any cart change invalidates a previous verification.
  const cartSig = cart.map((i) => cartKey(i) + i.qty + ":" + i.unitBdt).join(",");
  useEffect(() => setVerified(false), [cartSig]);

  useEffect(() => {
    scroll.current?.scrollTo({ y: 0, animated: true });
  }, [step]);

  if (!cart.length && phase === "idle")
    return <Empty icon="shopping-cart" title="Your cart is empty" text="Add something before checking out." action="Start shopping" onAction={() => router.navigate("/")} />;

  if (!user && !demo)
    return <Empty icon="user" title="Sign in to check out" text="We use your phone number to keep you updated on delivery." action="Sign in" onAction={() => router.push({ pathname: "/login", params: { next: "/checkout" } })} />;

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
      toast({ kind: "error", title: "Couldn't save address", body: (e as Error).message });
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
      setVerified(true);
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
          payment,
          coupon: coupon?.code,
        },
        address,
      );
      haptic.success();
      clearCart();
      router.replace({ pathname: "/success", params: { id: r.order.id, total: String(r.order.total) } });
    } catch (e) {
      haptic.error();
      toast({ kind: "error", title: "Couldn't place the order", body: (e as Error).message });
      setPhase("idle");
    }
  }

  const canNext = step === 0 ? !!address && !draft : true;

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.bg }} behavior={Platform.OS === "ios" ? "padding" : undefined} keyboardVerticalOffset={Platform.OS === "ios" ? 100 : 0}>
      <ScrollView ref={scroll} contentContainerStyle={{ padding: 16, paddingBottom: 140, gap: 14 }} keyboardShouldPersistTaps="handled">
        <Stepper step={step} onJump={setStep} />
        {demo && (
          <View style={[styles.demo, { backgroundColor: withAlpha(c.warn, 0.12) }]}>
            <Icon name="info" size={16} color={c.warn} />
            <T v="tiny" style={{ flex: 1, color: c.ink }}>
              Demo mode: payments aren't connected yet. Placing an order saves it on this phone only and no money is taken.
            </T>
          </View>
        )}

        <Animated.View key={step} entering={FadeInRight.duration(220)} exiting={FadeOutLeft.duration(150)} style={{ gap: 14 }}>
          <T v="h2" accessibilityRole="header">
            {["Delivery address", "Delivery", "Payment method", "Review your order"][step]}
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
                    accessibilityLabel={`${a.name}, ${a.area}, ${a.district}`}
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
                          {a.line}, {a.area}, {a.district}, {a.division}
                        </T>
                      </View>
                    </Card>
                  </Tap>
                );
              })}
              {draft ? (
                <Card style={{ gap: 14 }}>
                  <AddressForm value={draft} onChange={setDraft} />
                  <Button label="Save and use this address" icon="check" onPress={saveDraft} disabled={!addressValid(draft)} />
                  {addresses.length > 0 && <Button label="Cancel" kind="ghost" onPress={() => setDraft(null)} />}
                </Card>
              ) : (
                <Button label="Add a new address" kind="soft" icon="plus" onPress={() => setDraft(emptyAddress(user?.name, user?.phone))} />
              )}
            </>
          )}

          {step === 1 && (
            <>
              <T v="small" tone="ink2">
                Each store ships as its own Skydrop parcel. Items from different stores are bought separately in India and can arrive on different days.
              </T>
              {totals.groups.map((g) => {
                const free = g.shipping === 0 || coupon?.kind === "freeship";
                return (
                  <Card key={g.market} style={{ flexDirection: "row", gap: 12, alignItems: "center" }}>
                    <IconChip icon="truck" mode={g.market} />
                    <View style={{ flex: 1 }}>
                      <T v="tiny" style={{ fontWeight: "700", color: c.ink2 }}>
                        From {STORES[g.market].source}, India · {g.count} item{g.count === 1 ? "" : "s"}
                      </T>
                      <T v="h3" style={{ fontSize: 14 }}>
                        Skydrop door delivery · Standard
                      </T>
                      <T v="tiny" tone="ink2">
                        Arrives {addDays(now, w.min)} - {addDays(now, w.max)} ({w.min}-{w.max} days)
                      </T>
                    </View>
                    <View style={{ alignItems: "flex-end" }}>
                      <T v="small" style={{ fontWeight: "800", color: free ? c.good : c.ink }}>
                        {free ? "Free" : formatBdt(g.shipping)}
                      </T>
                      {free && g.shipping > 0 && (
                        <T v="tiny" tone="ink3" style={{ textDecorationLine: "line-through" }}>
                          {formatBdt(g.shipping)}
                        </T>
                      )}
                    </View>
                  </Card>
                );
              })}
              <View style={{ flexDirection: "row", gap: 8 }}>
                {[
                  ["shopping-bag", "Bought in India", "1-3 days"],
                  ["send", "Kolkata hub to Bangladesh", "3-5 days"],
                  ["home", "To your door", "1-3 days"],
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
            </>
          )}

          {step === 2 && (
            <>
              {PAYMENTS.map((p) => {
                const on = payment === p.id;
                return (
                  <Tap
                    key={p.id}
                    onPress={() => {
                      haptic.tap();
                      setPayment(p.id);
                    }}
                    accessibilityRole="radio"
                    accessibilityState={{ checked: on }}
                    accessibilityLabel={p.label}
                    scaleTo={0.98}
                  >
                    <Card style={{ flexDirection: "row", gap: 12, alignItems: "center", borderWidth: 2, borderColor: on ? c.brand : "transparent" }}>
                      <IconChip icon={p.icon} />
                      <View style={{ flex: 1 }}>
                        <T v="h3" style={{ fontSize: 15 }}>
                          {p.label}
                        </T>
                        <T v="tiny" tone="ink2">
                          {p.desc}
                        </T>
                        {p.id === "advance50" && (
                          <T v="tiny" style={{ color: c.brand, fontWeight: "700", marginTop: 2 }}>
                            {formatBdt(Math.ceil(totals.total / 2))} now, {formatBdt(totals.total - Math.ceil(totals.total / 2))} on delivery
                          </T>
                        )}
                      </View>
                      <View style={[styles.radio, { borderColor: on ? c.brand : c.ink3 }]}>{on && <View style={[styles.radioDot, { backgroundColor: c.brand }]} />}</View>
                    </Card>
                  </Tap>
                );
              })}
              <T v="tiny" tone="ink3">
                {demo ? "Payments aren't connected yet in this demo build. No payment screen will open." : "You'll be taken to the payment page after you place the order."}
              </T>
            </>
          )}

          {step === 3 && (
            <>
              <Card style={{ gap: 10 }}>
                {[
                  { icon: "map-pin", title: "Deliver to", text: address ? `${address.name}, ${address.line}, ${address.area}, ${address.district}` : "-", s: 0 },
                  { icon: "truck", title: "Delivery", text: `Skydrop door delivery, ${w.min}-${w.max} days`, s: 1 },
                  { icon: "credit-card", title: "Payment", text: PAYMENTS.find((p) => p.id === payment)!.label, s: 2 },
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
                    <Button label="Change" kind="ghost" onPress={() => setStep(r.s)} style={{ minHeight: 44, paddingHorizontal: 12 }} />
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
                        From {STORES[i.market].source} · Qty {i.qty}
                        {i.variant ? ` · ${i.variant}` : ""}
                      </T>
                    </View>
                    <T v="small" style={{ fontWeight: "800" }}>
                      {formatBdt(i.unitBdt * i.qty)}
                    </T>
                  </View>
                ))}
              </Card>
              <Card>
                <CouponField />
              </Card>
              {(phase === "verifying" || checks.some((s) => s !== "wait")) && <VerifyPanel states={checks} changed={changed} failed={failed} error={verifyError} />}
              {failed.length > 0 && (
                <Button
                  label="Remove unavailable items"
                  kind="danger"
                  onPress={() => {
                    failed.forEach((i) => removeItem(cartKey(i)));
                    setFailed([]);
                    setChecks(["wait", "wait", "wait"]);
                  }}
                />
              )}
              <SummaryLines t={totals} />
              <T v="tiny" tone="ink3">
                By placing this order you agree that Skydrop Retail buys these items for you from the Indian stores named above and Skydrop delivers them to your address. Prices are checked live before purchase.
              </T>
            </>
          )}
        </Animated.View>
      </ScrollView>

      <View style={[styles.bar, { backgroundColor: c.surface, borderTopColor: c.line, paddingBottom: Math.max(insets.bottom, 12) }]}>
        {phase !== "idle" ? (
          <VanDrive label={phase === "verifying" ? "Verifying price, stock and delivery" : "Placing your order"} />
        ) : (
          <View style={{ flexDirection: "row", alignItems: "center", gap: 12 }}>
            <View style={{ flex: 1 }}>
              <T v="tiny" tone="ink3">
                Total
              </T>
              <T v="h2">{formatBdt(totals.total)}</T>
              {verified && step === 3 && <Pill text="Verified" icon="check" color={c.good} bg={withAlpha(c.good, 0.12)} />}
            </View>
            {step < 3 ? (
              <Button
                label="Continue"
                icon="arrow-right"
                disabled={!canNext}
                onPress={() => {
                  haptic.light();
                  setStep(step + 1);
                }}
                style={{ flex: 1.4 }}
              />
            ) : (
              <Button label={verified ? "Place order" : "Verify and place order"} kind="coral" icon="shield" onPress={place} disabled={!address || failed.length > 0} style={{ flex: 1.8 }} />
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
  bar: { position: "absolute", left: 0, right: 0, bottom: 0, paddingHorizontal: 16, paddingTop: 12, borderTopWidth: StyleSheet.hairlineWidth },
});
