// "AI" screen: Skydrop AI shopping assistant with streaming replies and product cards (feature "ai_assistant").
import { useCallback, useEffect, useRef, useState } from "react";
import {
  AccessibilityInfo,
  FlatList,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  TextInput,
  View,
} from "react-native";
import { Stack, router, useLocalSearchParams } from "expo-router";
import Animated, { FadeInDown, useAnimatedStyle, useSharedValue, withRepeat, withTiming } from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import * as ai from "@/api/assistant";
import type { AiCard, AiEvent, AiOrder, AiProduct } from "@/api/assistant";
import { useStore } from "@/state/store";
import { useFeature } from "@/lib/features";
import { haptic } from "@/lib/haptics";
import { radius, shadow, useTheme, withAlpha } from "@/theme";
import { useToast } from "@/components/Toast";
import { Icon } from "@/components/Icon";
import { Empty, IconButton, ProductImage, Stars, T, Tap } from "@/components/ui";
import { useT } from "@/i18n";
import type { MsgKey } from "@/i18n";
import { statusText, STAGES } from "@/lib/orderStatus";
import type { OrderStatus } from "@/lib/types";
import { listEnter, enterFade } from "@/lib/motion";

type Msg = {
  key: string;
  role: "user" | "assistant";
  text: string;
  cards: AiCard[];
  status: "streaming" | "done" | "stopped" | "error";
  tool?: string | null;
  serverId?: number | null;
  feedback?: "up" | "down";
  error?: string;
  request?: ai.AiRequest;
};

let n = 0;
const key = () => `m${Date.now()}${n++}`;

export default function AssistantScreen() {
  const on = useFeature("ai_assistant");
  const { c } = useTheme();
  const { t, lang: appLang } = useT();
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ productId?: string; prompt?: string }>();
  const { cart, mode, user } = useStore();
  const [config, setConfig] = useState<ai.AiConfig | null>(null);
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const [draft, setDraft] = useState(params.prompt ?? "");
  const [busy, setBusy] = useState(false);
  // Reply/suggestion language: starts in the app language, the shopper can switch it here.
  const [lang, setLang] = useState<"en" | "bn">(appLang);
  const [remaining, setRemaining] = useState<number | null>(null);
  const conv = useRef<string | null>(null);
  const abort = useRef<AbortController | null>(null);
  const list = useRef<FlatList<Msg>>(null);

  useEffect(() => {
    ai.config().then(setConfig).catch(() => setConfig(null));
    return () => abort.current?.abort();
  }, [user]);

  const patch = useCallback((k: string, fn: (m: Msg) => Msg) => setMsgs((ms) => ms.map((m) => (m.key === k ? fn(m) : m))), []);

  const run = useCallback(
    async (req: ai.AiRequest, replyKey: string) => {
      const ctrl = new AbortController();
      abort.current = ctrl;
      setBusy(true);
      let text = "";
      try {
        await ai.chat({ ...req, conversationId: conv.current }, (e: AiEvent) => {
          if (e.type === "meta") conv.current = e.data.conversationId;
          else if (e.type === "text") {
            text += e.data.delta;
            patch(replyKey, (m) => ({ ...m, text: m.text + e.data.delta, tool: null }));
          } else if (e.type === "tool") patch(replyKey, (m) => ({ ...m, tool: e.data.name }));
          else if (e.type === "done") {
            if (typeof e.data.remaining === "number") setRemaining(e.data.remaining);
            patch(replyKey, (m) => ({ ...m, status: "done", serverId: e.data.messageId, tool: null }));
          } else if (e.type === "error") patch(replyKey, (m) => ({ ...m, status: "error", error: e.data.message }));
          else if (ai.CARD_TYPES.has(e.type)) patch(replyKey, (m) => ({ ...m, cards: [...m.cards, e as AiCard] }));
        }, ctrl.signal);
        patch(replyKey, (m) => (m.status === "streaming" ? { ...m, status: "done", tool: null } : m));
        AccessibilityInfo.announceForAccessibility(t("ai.aiSaid") + text.slice(0, 160));
      } catch (err) {
        if (ctrl.signal.aborted) patch(replyKey, (m) => ({ ...m, status: "stopped", tool: null }));
        else {
          const message = err instanceof ai.AiError ? err.message : t("ai.lost");
          if (err instanceof ai.AiError && err.code === "rate_limited") setRemaining(0);
          patch(replyKey, (m) => ({ ...m, status: "error", error: message, tool: null }));
          AccessibilityInfo.announceForAccessibility(message);
        }
      } finally {
        abort.current = null;
        setBusy(false);
      }
    },
    [patch, t],
  );

  const send = useCallback(
    (raw: string) => {
      const message = raw.trim();
      if (!message || busy) return;
      haptic.light();
      const req: ai.AiRequest = {
        message,
        store: mode,
        locale: ai.isBangla(message) ? "bn" : "en",
        context: { productId: params.productId, cartIds: cart.map((i) => i.productId).slice(0, 20) },
      };
      const reply: Msg = { key: key(), role: "assistant", text: "", cards: [], status: "streaming", request: req };
      setMsgs((ms) => [...ms, { key: key(), role: "user", text: message, cards: [], status: "done" }, reply]);
      setDraft("");
      void run(req, reply.key);
    },
    [busy, cart, mode, params.productId, run],
  );

  const regenerate = () => {
    const last = [...msgs].reverse().find((m) => m.role === "assistant" && m.request);
    if (!last?.request || busy) return;
    const reply: Msg = { key: key(), role: "assistant", text: "", cards: [], status: "streaming", request: last.request };
    setMsgs((ms) => [...ms.filter((m) => m.key !== last.key), reply]);
    void run(last.request, reply.key);
  };

  const clear = () => {
    abort.current?.abort();
    if (conv.current) void ai.clear(conv.current);
    conv.current = null;
    setMsgs([]);
  };

  const rate = (m: Msg, r: "up" | "down") => {
    haptic.light();
    patch(m.key, (x) => ({ ...x, feedback: r }));
    if (m.serverId) void ai.feedback(m.serverId, r);
  };

  if (!on) {
    return (
      <View style={{ flex: 1, backgroundColor: c.bg, justifyContent: "center" }}>
        <Stack.Screen options={{ title: "Skydrop AI" }} />
        <Empty icon="message-circle" title={t("rq.notAvail")} text={t("m.aiOff")} action={t("m.goBack")} onAction={() => router.back()} />
      </View>
    );
  }

  const out = remaining === 0 || config?.remaining === 0;
  const prompts = config?.prompts?.[lang] ?? [];
  const lastAssistant = [...msgs].reverse().find((m) => m.role === "assistant")?.key;

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: c.bg }} behavior={Platform.OS === "ios" ? "padding" : undefined} keyboardVerticalOffset={Platform.OS === "ios" ? 90 : 0}>
      <Stack.Screen
        options={{
          title: "Skydrop AI",
          headerRight: () => (msgs.length ? <IconButton icon="trash-2" label={t("ai.clear")} size={36} onPress={clear} /> : null),
        }}
      />
      {config?.basic || config?.demo ? (
        <View style={[styles.banner, { backgroundColor: c.surface2 }]}>
          <T v="tiny" tone="ink2">
            {config.demo ? t("ai.demo") : t("ai.basic")}
          </T>
        </View>
      ) : null}

      {msgs.length === 0 ? (
        <ScrollView contentContainerStyle={styles.empty} keyboardShouldPersistTaps="handled">
          <Animated.View entering={enterFade} style={[styles.orb, { backgroundColor: c.brand }]}>
            <Icon name="star" size={28} color="#fff" />
          </Animated.View>
          <T v="h3" style={{ textAlign: "center", maxWidth: 300 }}>
            {config?.greeting?.[lang] ?? t("ai.greeting")}
          </T>
          <View style={[styles.seg, { backgroundColor: c.surface2 }]} accessibilityRole="radiogroup">
            {(["en", "bn"] as const).map((l) => (
              <Tap
                key={l}
                onPress={() => setLang(l)}
                accessibilityRole="radio"
                accessibilityState={{ selected: lang === l }}
                style={[styles.segBtn, lang === l && { backgroundColor: c.surface, ...shadow(c) }]}
              >
                <T v="small" style={{ fontWeight: "700" }}>
                  {l === "en" ? "English" : "বাংলা"}
                </T>
              </Tap>
            ))}
          </View>
          <View style={styles.chips}>
            {params.productId && (
              <Chip label={lang === "bn" ? "এই পণ্য সম্পর্কে জানতে চাই" : t("m.askThis")} onPress={() => send(lang === "bn" ? "এই পণ্যটি কেমন? কবে পাব?" : "Is this product worth it, and when would it arrive?")} />
            )}
            {prompts.map((p, i) => (
              <Animated.View key={p} entering={listEnter(i)}>
                <Chip label={p} onPress={() => send(p)} disabled={out} />
              </Animated.View>
            ))}
          </View>
        </ScrollView>
      ) : (
        <FlatList
          ref={list}
          data={msgs}
          keyExtractor={(m) => m.key}
          contentContainerStyle={{ padding: 14, gap: 12 }}
          onContentSizeChange={() => list.current?.scrollToEnd({ animated: true })}
          keyboardShouldPersistTaps="handled"
          renderItem={({ item }) => <Bubble m={item} last={item.key === lastAssistant} busy={busy} onRate={rate} onRegenerate={regenerate} />}
        />
      )}

      <View style={[styles.compose, { backgroundColor: c.surface, borderTopColor: c.line, paddingBottom: Math.max(insets.bottom, 10) }]}>
        {out && (
          <T v="small" tone="warn" style={{ textAlign: "center", marginBottom: 6, fontWeight: "600" }}>
            {t("ai.limit")}
            {user ? t("ai.tomorrow") : t("ai.signInMore")}
          </T>
        )}
        <View style={[styles.input, { borderColor: c.line, backgroundColor: c.bg }]}>
          <TextInput
            value={draft}
            onChangeText={setDraft}
            placeholder={lang === "bn" ? "কী খুঁজছেন? বাজেট সহ লিখুন…" : "What are you looking for?"}
            placeholderTextColor={c.ink3}
            style={[styles.text, { color: c.ink }]}
            multiline
            maxLength={2000}
            editable={!out}
            accessibilityLabel={t("ai.message")}
            onSubmitEditing={() => send(draft)}
            submitBehavior="submit"
            returnKeyType="send"
          />
          {busy ? (
            <IconButton icon="square" label={t("ai.stop")} size={44} bg={c.ink} tint={c.bg} onPress={() => abort.current?.abort()} />
          ) : (
            <IconButton icon="arrow-up" label={t("ai.send")} size={44} bg={draft.trim() && !out ? c.brand : withAlpha(c.brand, 0.35)} tint="#fff" onPress={() => send(draft)} />
          )}
        </View>
        <T v="tiny" tone="ink3" style={{ textAlign: "center", marginTop: 6 }}>{t("ai.foot")}</T>
      </View>
    </KeyboardAvoidingView>
  );
}

function Chip({ label, onPress, disabled }: { label: string; onPress: () => void; disabled?: boolean }) {
  const { c } = useTheme();
  return (
    <Tap onPress={onPress} disabled={disabled} accessibilityLabel={label} style={[styles.chip, { borderColor: c.line, backgroundColor: c.surface, opacity: disabled ? 0.5 : 1 }]}>
      <T v="small" style={{ fontWeight: "600" }}>
        {label}
      </T>
    </Tap>
  );
}

function Caret() {
  const { c } = useTheme();
  const o = useSharedValue(1);
  useEffect(() => {
    o.value = withRepeat(withTiming(0, { duration: 500 }), -1, true);
  }, [o]);
  const s = useAnimatedStyle(() => ({ opacity: o.value }));
  return <Animated.View style={[{ width: 2, height: 16, backgroundColor: c.brand, marginLeft: 2 }, s]} />;
}

const TOOL: Record<string, MsgKey> = {
  search_products: "ai.tool.search",
  get_product: "ai.tool.product",
  compare_products: "ai.tool.compare",
  delivery_estimate: "ai.tool.delivery",
  get_my_orders: "ai.tool.orders",
  track_order: "ai.tool.track",
};

function Bubble({ m, last, busy, onRate, onRegenerate }: { m: Msg; last: boolean; busy: boolean; onRate: (m: Msg, r: "up" | "down") => void; onRegenerate: () => void }) {
  const { t } = useT();
  const { c } = useTheme();
  if (m.role === "user") {
    return (
      <Animated.View entering={FadeInDown.duration(220)} style={[styles.user, { backgroundColor: c.brand }]} accessibilityLabel={t("ai.youSaid") + m.text}>
        <T tone="white">{m.text}</T>
      </Animated.View>
    );
  }
  const streaming = m.status === "streaming";
  return (
    <Animated.View entering={FadeInDown.duration(220)} style={{ gap: 8 }}>
      {(m.text || streaming) && (
        <View style={[styles.bot, { backgroundColor: c.surface }, shadow(c)]} accessibilityLabel={m.text ? t("ai.aiSaid") + m.text.replace(/\*\*/g, "") : t("ai.replying")}>
          <View style={{ flexDirection: "row", flexWrap: "wrap", alignItems: "flex-end" }}>
            <T>{m.text.replace(/\*\*/g, "")}</T>
            {streaming && <Caret />}
          </View>
        </View>
      )}
      {streaming && m.tool && (
        <T v="tiny" tone="ink2">
          {t(TOOL[m.tool] ?? "ai.tool.working")}…
        </T>
      )}
      {m.cards.map((card, i) => (
        <Animated.View key={i} entering={listEnter(i)}>
          <Card card={card} />
        </Animated.View>
      ))}
      {m.status === "error" && (
        <T v="small" tone="bad" accessibilityRole="alert">
          {m.error}
        </T>
      )}
      {m.status === "stopped" && (
        <T v="tiny" tone="ink3">{t("ai.stopped")}</T>
      )}
      {!streaming && (
        <View style={{ flexDirection: "row", gap: 6 }}>
          {m.status === "done" && (
            <>
              <IconButton icon="thumbs-up" label={t("ai.helpful")} size={36} tint={m.feedback === "up" ? c.brand : c.ink3} bg="transparent" onPress={() => onRate(m, "up")} />
              <IconButton icon="thumbs-down" label={t("ai.notHelpful")} size={36} tint={m.feedback === "down" ? c.bad : c.ink3} bg="transparent" onPress={() => onRate(m, "down")} />
            </>
          )}
          {last && !busy && <IconButton icon="refresh-cw" label={t("ai.regenerate")} size={36} tint={c.ink3} bg="transparent" onPress={onRegenerate} />}
        </View>
      )}
    </Animated.View>
  );
}

function useAdd() {
  const { t } = useT();
  const { addToCart } = useStore();
  const toast = useToast();
  return (p: AiProduct, qty = 1, variant?: string | null) => {
    haptic.success();
    addToCart({ productId: p.id, market: p.market, title: p.title, image: p.image, placeholder: p.placeholder ?? undefined, variant: variant ?? undefined, unitBdt: p.priceBdt, weightKg: p.weightKg, qty });
    toast({ kind: "success", title: t("ai.added"), body: p.title.slice(0, 80), action: { label: t("buy.viewCart"), href: "/cart" } });
  };
}

function AddBtn({ p, qty, variant }: { p: AiProduct; qty?: number; variant?: string | null }) {
  const { t } = useT();
  const { c } = useTheme();
  const add = useAdd();
  const [done, setDone] = useState(false);
  const blocked = !p.inStock || p.restriction?.action === "block";
  return (
    <Tap
      disabled={blocked}
      onPress={() => {
        add(p, qty, variant);
        setDone(true);
      }}
      accessibilityLabel={blocked ? t("rq.notAvail") : t("ai.addTitle", { title: p.title })}
      style={[styles.add, { backgroundColor: blocked ? c.surface2 : c.brand }]}
    >
      <Icon name={done ? "check" : "shopping-cart"} size={14} color={blocked ? c.ink3 : "#fff"} />
      <T v="small" tone={blocked ? "ink3" : "white"} style={{ fontWeight: "700" }}>
        {blocked ? (p.inStock ? t("m.cantShip") : t("stock.out")) : done ? t("buy.addedShort") : t("buy.addToCart")}
      </T>
    </Tap>
  );
}

function ProductTile({ p }: { p: AiProduct }) {
  const { bdt } = useT();
  const { c } = useTheme();
  return (
    <View style={[styles.tile, { backgroundColor: c.surface }, shadow(c)]}>
      <Tap onPress={() => router.push({ pathname: "/product/[id]", params: { id: p.id } })} accessibilityLabel={`${p.title}, ${bdt(p.priceBdt)}`} style={{ flex: 1 }}>
        <ProductImage uri={p.image} placeholder={p.placeholder ?? undefined} style={{ height: 120, borderTopLeftRadius: radius.md, borderTopRightRadius: radius.md }} />
        <View style={{ padding: 8, gap: 3, flex: 1 }}>
          <T v="tiny" numberOfLines={2} style={{ fontWeight: "600", color: c.ink }}>
            {p.title}
          </T>
          {p.rating > 0 && <Stars rating={p.rating} count={p.reviews} />}
          <T v="h3">{bdt(p.priceBdt)}</T>
        </View>
      </Tap>
      <View style={{ paddingHorizontal: 8, paddingBottom: 8 }}>
        <AddBtn p={p} />
      </View>
    </View>
  );
}

const ST = Object.fromEntries([...STAGES.map((s) => [s.status, 1]), ["cancelled", 1]]);

function OrderRow({ o }: { o: AiOrder }) {
  const tr = useT();
  const { t, bdt } = tr;
  const { c } = useTheme();
  return (
    <Tap onPress={() => router.push({ pathname: "/order/[id]", params: { id: o.id } })} style={[styles.card, { backgroundColor: c.surface }, shadow(c)]} accessibilityLabel={t("m.orderA11yStatus", { id: o.id, status: o.status in ST ? statusText(o.status as OrderStatus, tr) : o.status.replace(/_/g, " ") })}>
      <T v="h3">
        {o.id} · {o.status in ST ? statusText(o.status as OrderStatus, tr) : o.status.replace(/_/g, " ")}
      </T>
      <T v="small" tone="ink2">
        {t("ai.orderLine", { items: t("cart.items", { count: o.itemCount }), total: bdt(o.total) })}
        {o.expectedBy ? t("ai.expectedBy", { date: o.expectedBy }) : ""}
      </T>
    </Tap>
  );
}

function Card({ card }: { card: AiCard }) {
  const { t, bdt } = useT();
  const { c } = useTheme();
  switch (card.type) {
    case "products":
      return (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 10, paddingRight: 14 }} accessibilityLabel={t("ai.products", { count: card.data.items.length })}>
          {card.data.items.map((p) => (
            <ProductTile key={p.id} p={p} />
          ))}
        </ScrollView>
      );
    case "compare":
      return (
        <ScrollView horizontal showsHorizontalScrollIndicator={false}>
          <View style={[styles.card, { backgroundColor: c.surface, padding: 0 }, shadow(c)]}>
            {card.data.rows.map((r) => (
              <View key={r.label} style={[styles.row, { borderBottomColor: c.line }]}>
                <T v="small" tone="ink2" style={{ width: 90, fontWeight: "600" }}>
                  {r.label}
                </T>
                {r.values.map((v, i) => (
                  <T key={i} v="small" style={{ width: 120 }}>
                    {v}
                  </T>
                ))}
              </View>
            ))}
            <View style={[styles.row, { borderBottomWidth: 0 }]}>
              <View style={{ width: 90 }} />
              {card.data.items.map((p) => (
                <View key={p.id} style={{ width: 120 }}>
                  <AddBtn p={p} />
                </View>
              ))}
            </View>
          </View>
        </ScrollView>
      );
    case "orders":
      return (
        <View style={{ gap: 8 }}>
          {card.data.items.map((o) => (
            <OrderRow key={o.id} o={o} />
          ))}
        </View>
      );
    case "order":
      return <OrderRow o={card.data} />;
    case "cart":
      return (
        <View style={[styles.card, styles.cartRow, { backgroundColor: c.surface }, shadow(c)]}>
          <ProductImage uri={card.data.product.image} placeholder={card.data.product.placeholder ?? undefined} size={52} style={{ borderRadius: 12 }} />
          <View style={{ flex: 1 }}>
            <T v="small" numberOfLines={2} style={{ fontWeight: "600" }}>
              {card.data.product.title}
            </T>
            <T v="tiny" tone="ink2">
              {bdt(card.data.product.priceBdt)}
              {card.data.variant ? ` · ${card.data.variant}` : ""}
            </T>
          </View>
          <AddBtn p={card.data.product} qty={card.data.qty} variant={card.data.variant} />
        </View>
      );
    case "delivery":
      return (
        <View style={[styles.card, { backgroundColor: c.surface }, shadow(c)]}>
          <T v="h3">
            {t("ai.daysDoor", { min: card.data.minDays, max: card.data.maxDays })}
          </T>
          <T v="small" tone="ink2">
            {t("ai.orderToday", { from: card.data.earliest, to: card.data.latest })}
          </T>
          {card.data.cutoffs.map((x) => (
            <T key={x.name} v="small" tone={x.status === "on_time" ? "good" : x.status === "maybe" ? "warn" : "bad"}>
              {x.name}: {x.status === "on_time" ? t("m.orderBy", { time: x.orderBy }) : x.status === "maybe" ? t("m.mayLate") : t("m.tooLate")}
            </T>
          ))}
        </View>
      );
    case "login":
      return (
        <Tap onPress={() => router.push("/login")} style={[styles.card, { backgroundColor: c.surface }, shadow(c)]} accessibilityLabel={t("ai.signInOrders")}>
          <T v="h3">{t("ai.signInOrders")}</T>
          <T v="small" tone="ink2">{t("ai.otpLine")}</T>
        </Tap>
      );
    case "priceHistory":
      return (
        <View style={[styles.card, { backgroundColor: c.surface }, shadow(c)]}>
          <T v="small">
            {t("ai.now", { amount: bdt(card.data.currentBdt) })} · {t("ai.low", { amount: bdt(Math.min(...card.data.points.map((p) => p.bdt))) })} ·{" "}
            {t("ai.high", { amount: bdt(Math.max(...card.data.points.map((p) => p.bdt))) })}
          </T>
        </View>
      );
    case "request":
      return (
        <View style={[styles.card, { backgroundColor: c.surface }, shadow(c)]}>
          <T v="small">{t("m.reqSentShort", { title: card.data.title })}</T>
        </View>
      );
  }
}

const styles = StyleSheet.create({
  banner: { paddingVertical: 6, alignItems: "center" },
  empty: { flexGrow: 1, alignItems: "center", justifyContent: "center", gap: 16, padding: 20 },
  orb: { width: 64, height: 64, borderRadius: 32, alignItems: "center", justifyContent: "center" },
  seg: { flexDirection: "row", padding: 3, borderRadius: radius.pill },
  segBtn: { minHeight: 36, minWidth: 64, paddingHorizontal: 14, borderRadius: radius.pill, alignItems: "center", justifyContent: "center" },
  chips: { flexDirection: "row", flexWrap: "wrap", justifyContent: "center", gap: 8 },
  chip: { minHeight: 44, paddingHorizontal: 14, paddingVertical: 10, borderRadius: radius.pill, borderWidth: 1, justifyContent: "center" },
  user: { alignSelf: "flex-end", maxWidth: "85%", paddingHorizontal: 13, paddingVertical: 9, borderRadius: 18, borderBottomRightRadius: 4 },
  bot: { alignSelf: "flex-start", maxWidth: "92%", paddingHorizontal: 13, paddingVertical: 10, borderRadius: 18, borderBottomLeftRadius: 4 },
  tile: { width: 150, borderRadius: radius.md, overflow: "hidden" },
  add: { minHeight: 44, borderRadius: 12, flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 6, paddingHorizontal: 10 },
  card: { borderRadius: radius.md, padding: 12, gap: 4 },
  cartRow: { flexDirection: "row", alignItems: "center", gap: 10 },
  row: { flexDirection: "row", alignItems: "center", gap: 8, paddingHorizontal: 12, paddingVertical: 8, borderBottomWidth: StyleSheet.hairlineWidth },
  compose: { paddingHorizontal: 12, paddingTop: 8, borderTopWidth: StyleSheet.hairlineWidth },
  input: { flexDirection: "row", alignItems: "flex-end", gap: 8, borderWidth: 1, borderRadius: 24, paddingLeft: 14, padding: 4 },
  text: { flex: 1, minHeight: 44, maxHeight: 120, fontSize: 15, paddingTop: 12, paddingBottom: 10 },
});
