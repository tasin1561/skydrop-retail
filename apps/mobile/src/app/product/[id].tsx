import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { ActivityIndicator, StyleSheet, View, useWindowDimensions } from "react-native";
import Animated, { FadeIn, interpolate, useAnimatedScrollHandler, useAnimatedStyle, useSharedValue, Extrapolation } from "react-native-reanimated";
import { router, useFocusEffect, useLocalSearchParams } from "expo-router";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { api, waitForJob } from "@/api/client";
import { useStore } from "@/state/store";
import type { Product } from "@/lib/types";
import { STORES } from "@/lib/markets";
import { discountPct, weightKg } from "@/lib/pricing";
import { isUnavailableStock, unitMrp, unitPrice } from "@/lib/cart";
import { comboExists, groupVariants, initialSelection, pickVariant } from "@/lib/variants";
import { useAccent, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { captureCode, sharePage } from "@/lib/share";
import { Button, IconButton, Pill, Skeleton, Stars, T, Tap } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { QtyStepper } from "@/components/QtyStepper";
import { Rail, useAsync } from "@/components/Shop";
import { useToast } from "@/components/Toast";
import { AskAiButton } from "@/components/AskAiButton";
import { Gallery, type Media } from "@/components/product/Gallery";
import { VariantGroup } from "@/components/product/Variants";
import { DeliveryRoute, PriceBreakdown, Reviews, Specs } from "@/components/product/Details";
import { FetchingLive, ProductExtras } from "@/components/product/Extras";
import { OffersSection, Countdown, headlineDeal } from "@/components/offers/offers";
import { ShoppingSections } from "@/components/shopping/ShoppingSections";
import { useT } from "@/i18n";

type Phase = "idle" | "busy" | "done";

export default function ProductScreen() {
  const { id, ref, invite } = useLocalSearchParams<{ id: string; ref?: string; invite?: string }>();
  // Deep links like skydropretail://product/ID?ref=CODE (affiliate) or ?invite=CODE (refer a friend).
  useEffect(() => {
    if (ref) captureCode("ref", ref);
    if (invite) captureCode("invite", invite);
  }, [ref, invite]);
  return <ProductLoader key={id} id={id} />;
}

function ProductLoader({ id }: { id: string }) {
  const { t } = useT();
  const { c } = useTheme();
  const [p, setP] = useState<Product | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const [jobId, setJobId] = useState<string | null>(null);
  const { markViewed } = useStore();

  useEffect(() => {
    let live = true;
    api
      .product(id)
      .then((r) => {
        if (!live) return;
        setP(r.product);
        setPending(r.status === "pending");
        setJobId(r.status === "pending" ? (r.jobId ?? null) : null);
      })
      .catch((e: Error) => live && setError(e.message));
    markViewed(id);
    return () => {
      live = false;
    };
  }, [id, markViewed]);

  // Pending listing: poll the refresh job while this screen is focused, then reload.
  useFocusEffect(
    useCallback(() => {
      if (!pending || !jobId) return;
      const ctrl = new AbortController();
      waitForJob(jobId, ctrl.signal).then(async (done) => {
        if (ctrl.signal.aborted) return;
        if (done) {
          try {
            const r = await api.product(id);
            if (ctrl.signal.aborted) return;
            setP(r.product);
            setPending(r.status === "pending" && !!r.jobId && r.jobId !== jobId);
            setJobId(r.status === "pending" ? (r.jobId ?? null) : null);
            return;
          } catch {
            /* keep cached data */
          }
        }
        setPending(false);
        setJobId(null);
      });
      return () => ctrl.abort();
    }, [pending, jobId, id]),
  );

  if (error)
    return (
      <View style={{ flex: 1, backgroundColor: c.bg, alignItems: "center", justifyContent: "center", padding: 24, gap: 12 }}>
        <T v="h2">{t("m.productNA")}</T>
        <T v="body" tone="ink2" style={{ textAlign: "center" }}>
          {error}
        </T>
        <Button label={t("m.goBack")} onPress={() => router.back()} />
      </View>
    );
  if (!p) return <ProductSkeleton />;
  return <ProductView p={p} pending={pending} onSwap={setP} />;
}

function ProductSkeleton() {
  const { t } = useT();
  const { width } = useWindowDimensions();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <Skeleton w={width} h={width} r={0} />
      <View style={{ padding: 16, gap: 10 }}>
        <Skeleton w="35%" h={14} />
        <Skeleton h={20} />
        <Skeleton w="70%" h={20} />
        <Skeleton w="40%" h={28} style={{ marginTop: 8 }} />
        <Skeleton h={120} r={20} style={{ marginTop: 12 }} />
      </View>
      <View style={{ position: "absolute", top: insets.top + 8, left: 12 }}>
        <IconButton icon="arrow-left" label={t("common.back")} onPress={() => router.back()} />
      </View>
    </View>
  );
}

function ProductView({ p, pending, onSwap }: { p: Product; pending: boolean; onSwap: (p: Product) => void }) {
  const { t, bdt, inr } = useT();
  const { c } = useTheme();
  const a = useAccent(p.market);
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const toast = useToast();
  const { addToCart, wish, toggleWish, totals, user } = useStore();
  const store = STORES[p.market];
  const variants = useMemo(() => p.variants ?? [], [p.variants]);
  const groups = useMemo(() => groupVariants(variants), [variants]);
  const [sel, setSel] = useState<Record<string, string>>(() => initialSelection(p.id, variants));
  const [qty, setQty] = useState(1);
  const [add, setAdd] = useState<Phase>("idle");
  const [buy, setBuy] = useState<Phase>("idle");
  const [swapping, setSwapping] = useState<string | null>(null);
  const timers = useRef<ReturnType<typeof setTimeout>[]>([]);
  useEffect(() => () => timers.current.forEach(clearTimeout), []);

  const related = useAsync(() => api.related(p), [p.id]);

  const media: Media[] = useMemo(() => {
    const images = p.images?.length ? p.images : p.image ? [p.image] : [];
    const m: Media[] = images.map((src) => ({ kind: "image", src }));
    const vids: Media[] = (p.videos ?? []).map((v) => ({ kind: "video", hls: v.hls, thumb: v.thumb }));
    m.splice(Math.min(1, m.length), 0, ...vids);
    return m;
  }, [p]);

  const price = unitPrice(p);
  const mrp = unitMrp(p);
  const off = discountPct(p);
  const available = !isUnavailableStock(p.stock);
  const current = variants.find((v) => Object.entries(sel).every(([k, val]) => v.dimensions[k] === val));
  const variantLabel = current?.label ?? (Object.values(sel).length ? Object.values(sel).join(" / ") : undefined);
  const wished = wish.includes(p.id);
  const deal = headlineDeal(p, t);

  const pick = useCallback(
    async (key: string, value: string) => {
      const target = pickVariant(variants, sel, key, value);
      if (!target) return;
      setSel({ ...target.dimensions });
      // Variants with their own listing load in place (price and photos can differ).
      if (target.id && target.id !== p.id) {
        setSwapping(value);
        try {
          const r = await api.product(target.id);
          onSwap(r.product);
        } catch {
          /* keep the selection on this listing */
        } finally {
          setSwapping(null);
        }
      }
    },
    [variants, sel, p.id, onSwap],
  );

  function put() {
    addToCart({
      productId: p.id,
      market: p.market,
      title: p.title,
      image: p.image,
      placeholder: p.placeholder,
      variant: variantLabel,
      unitBdt: price,
      weightKg: weightKg(p),
      qty,
    });
  }

  function onAdd() {
    if (add !== "idle" || !available) return;
    haptic.light();
    setAdd("busy");
    timers.current.push(
      setTimeout(() => {
        put();
        haptic.success();
        setAdd("done");
        toast({ kind: "success", title: t("buy.added", { count: qty }), body: variantLabel ? `${variantLabel} · ${bdt(price * qty)}` : bdt(price * qty), action: { label: t("buy.viewCart"), href: "/cart" } });
        timers.current.push(setTimeout(() => setAdd("idle"), 1600));
      }, 500),
    );
  }

  function onBuy() {
    if (buy !== "idle" || !available) return;
    haptic.medium();
    setBuy("busy");
    timers.current.push(
      setTimeout(() => {
        put();
        setBuy("idle");
        router.push("/checkout");
      }, 400),
    );
  }

  // Header fades in once the gallery scrolls away.
  const y = useSharedValue(0);
  const onScroll = useAnimatedScrollHandler((e) => {
    y.value = e.contentOffset.y;
  });
  const headerBg = useAnimatedStyle(() => ({ opacity: interpolate(y.value, [width * 0.55, width * 0.8], [0, 1], Extrapolation.CLAMP) }));

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      <Animated.ScrollView onScroll={onScroll} scrollEventThrottle={16} contentContainerStyle={{ paddingBottom: 120 + insets.bottom }}>
        <View style={{ paddingTop: insets.top, backgroundColor: "#fff" }}>
          <Gallery media={media} placeholder={p.placeholder} title={p.title} />
        </View>

        <Animated.View entering={FadeIn.duration(250)} style={{ padding: 16, gap: 10 }}>
          <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
            <Pill text={t("m.fromSource", { source: store.source })} icon="globe" color={a.ink} bg={a.soft} />
            <Pill text={store.name} />
            {p.bestSeller && <Pill text={t("card.bestSeller")} icon="star" />}
            {p.demo && <Pill text={t("m.demoProduct")} icon="info" />}
          </View>
          {pending && <FetchingLive market={p.market} />}
          {!!p.brand && (
            <T v="small" style={{ color: a.ink, fontWeight: "800" }}>
              {p.brand}
            </T>
          )}
          <T v="h2" accessibilityRole="header" style={{ fontSize: 19, lineHeight: 25 }}>
            {p.title}
          </T>
          {p.rating > 0 && <Stars rating={p.rating} count={p.reviews} size={14} />}

          <View style={{ flexDirection: "row", alignItems: "baseline", gap: 10, marginTop: 4, flexWrap: "wrap" }}>
            <T v="h1" style={{ fontSize: 30, lineHeight: 36 }}>
              {bdt(price)}
            </T>
            {mrp > price && (
              <T v="body" tone="ink3" style={{ textDecorationLine: "line-through" }}>
                {bdt(mrp)}
              </T>
            )}
            {off > 0 && (
              <View style={[styles.off, { backgroundColor: c.coral }]}>
                <T v="small" tone="white" style={{ fontWeight: "800" }}>
                  {t("card.off", { pct: off })}
                </T>
              </View>
            )}
          </View>
          {deal && (
            <View style={[styles.deal, { backgroundColor: c.bad }]}>
              <Icon name="zap" size={13} color="#fff" />
              <T v="small" tone="white" style={{ fontWeight: "800" }}>
                {deal.label}
              </T>
              {deal.endsAt && <Countdown endsAt={deal.endsAt} color="#fff" size="small" />}
            </View>
          )}
          {!!p.couponInr && (
            <T v="small" style={{ color: c.good, fontWeight: "800" }}>
              {t("m.couponFrom", { amount: inr(p.couponInr), source: store.source })}
            </T>
          )}
          <T v="tiny" tone="ink2">{t("m.dutyShip")}</T>
          <ProductExtras id={p.id} market={p.market} priceBdt={price} inStock={available} />
          {!available && (
            <T v="small" tone="bad" style={{ fontWeight: "700" }}>
              {t("m.unavailOn", { source: store.source })}
            </T>
          )}
        </Animated.View>

        <OffersSection p={p} />

        {groups.length > 0 && (
          <View style={{ paddingHorizontal: 16, gap: 16, marginBottom: 16 }}>
            {groups.map((g) => (
              <VariantGroup
                key={g.key}
                group={g}
                market={p.market}
                selected={sel[g.key]}
                isAvailable={(v) => comboExists(variants, sel, g.key, v)}
                onPick={(v) => pick(g.key, v)}
              />
            ))}
            {swapping && (
              <View style={{ flexDirection: "row", alignItems: "center", gap: 8 }}>
                <ActivityIndicator size="small" color={a.accent} />
                <T v="small" style={{ color: a.ink, fontWeight: "700" }}>
                  {t("var.opening", { value: swapping })}
                </T>
              </View>
            )}
          </View>
        )}

        <View style={{ paddingHorizontal: 16, flexDirection: "row", alignItems: "center", gap: 12, marginBottom: 16 }}>
          <QtyStepper value={qty} onChange={setQty} />
          <T v="small" tone="ink2">
            {qty > 1 ? t("buy.forQty", { amount: bdt(price * qty), count: qty }) : t("buy.upTo10")}
          </T>
        </View>

        <View style={{ paddingHorizontal: 16, gap: 12 }}>
          <DeliveryRoute market={p.market} />
          <PriceBreakdown p={p} />
          <Specs p={p} />
          <Reviews p={p} />
          <ShoppingSections p={p} inStock={available} addLine={put} />
        </View>

        <Rail title={t("m.moreFrom", { name: store.name })} items={related.data ?? []} loading={related.loading} />
      </Animated.ScrollView>

      {/* floating header */}
      <View style={[styles.header, { paddingTop: insets.top + 6 }]} pointerEvents="box-none">
        <Animated.View style={[StyleSheet.absoluteFill, { backgroundColor: c.surface, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: c.line }, headerBg]} />
        <IconButton icon="arrow-left" label={t("common.back")} onPress={() => router.back()} />
        <Animated.View style={[{ flex: 1, paddingHorizontal: 8 }, headerBg]}>
          <T v="small" numberOfLines={1} style={{ fontWeight: "800" }}>
            {p.title}
          </T>
        </Animated.View>
        <AskAiButton productId={p.id} spaced />
        <IconButton
          icon="share-2"
          label={t("m.shareProduct")}
          onPress={() => {
            haptic.light();
            sharePage({ path: `/p/${encodeURIComponent(p.id)}`, title: p.title, user }).catch(() =>
              toast({ kind: "error", title: t("m.shareFailed") }),
            );
          }}
        />
        <View style={{ width: 8 }} />
        <IconButton
          icon="heart"
          label={wished ? t("wish.remove") : t("wish.save")}
          tint={wished ? c.coral : c.ink}
          onPress={() => {
            haptic.light();
            const on = toggleWish(p.id);
            toast({ kind: "info", title: on ? t("wish.saved") : t("wish.removed") });
          }}
        />
        <View style={{ width: 8 }} />
        <IconButton icon="shopping-cart" label={t("nav.cartCount", { count: totals.count })} badge={totals.count} onPress={() => router.navigate("/cart")} />
      </View>

      {/* sticky buy bar */}
      <View style={[styles.bar, { backgroundColor: c.surface, borderTopColor: c.line, paddingBottom: Math.max(insets.bottom, 12) }]}>
        <Tap
          onPress={onAdd}
          disabled={!available}
          accessibilityLabel={add === "done" ? t("ai.added") : t("buy.addToCart")}
          accessibilityState={{ busy: add === "busy", disabled: !available }}
          style={[styles.addBtn, { backgroundColor: a.soft, opacity: available ? 1 : 0.5 }]}
        >
          <Animated.View key={add} entering={FadeIn.duration(160)} style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
            {add === "busy" ? <ActivityIndicator size="small" color={a.ink} /> : <Icon name={add === "done" ? "check" : "shopping-cart"} size={18} color={a.ink} />}
            <T v="h3" style={{ color: a.ink, fontSize: 15 }}>
              {add === "busy" ? t("buy.adding") : add === "done" ? t("buy.addedShort") : t("buy.addToCart")}
            </T>
          </Animated.View>
        </Tap>
        <Button label={t("buy.buyNow")} mode={p.market} onPress={onBuy} busy={buy === "busy"} disabled={!available} style={{ flex: 1 }} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  off: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  deal: { flexDirection: "row", alignItems: "center", gap: 6, alignSelf: "flex-start", paddingHorizontal: 10, paddingVertical: 5, borderRadius: 10 },
  header: { position: "absolute", top: 0, left: 0, right: 0, flexDirection: "row", alignItems: "center", paddingHorizontal: 12, paddingBottom: 8 },
  bar: { position: "absolute", left: 0, right: 0, bottom: 0, flexDirection: "row", gap: 10, paddingHorizontal: 16, paddingTop: 12, borderTopWidth: StyleSheet.hairlineWidth },
  addBtn: { flex: 1, minHeight: 50, borderRadius: 16, alignItems: "center", justifyContent: "center" },
});
