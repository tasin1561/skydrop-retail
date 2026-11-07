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
import Animated, { FadeIn, FadeInDown, useAnimatedStyle, useSharedValue, withRepeat, withTiming } from "react-native-reanimated";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import * as ai from "@/api/assistant";
import type { AiCard, AiEvent, AiOrder, AiProduct } from "@/api/assistant";
import { useStore } from "@/state/store";
import { useFeature } from "@/lib/features";
import { formatBdt } from "@/lib/pricing";
import { haptic } from "@/lib/haptics";
import { radius, shadow, useTheme, withAlpha } from "@/theme";
import { useToast } from "@/components/Toast";
import { Icon } from "@/components/Icon";
import { Empty, IconButton, ProductImage, Stars, T, Tap } from "@/components/ui";

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
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ productId?: string; prompt?: string }>();
  const { cart, mode, user } = useStore();
  const [config, setConfig] = useState<ai.AiConfig | null>(null);
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const [draft, setDraft] = useState(params.prompt ?? "");
  const [busy, setBusy] = useState(false);
  const [lang, setLang] = useState<"en" | "bn">("en");
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
        AccessibilityInfo.announceForAccessibility(`Skydrop AI: ${text.slice(0, 160)}`);
      } catch (err) {
        if (ctrl.signal.aborted) patch(replyKey, (m) => ({ ...m, status: "stopped", tool: null }));
        else {
          const message = err instanceof ai.AiError ? err.message : "Connection lost. Please try again.";
          if (err instanceof ai.AiError && err.code === "rate_limited") setRemaining(0);
          patch(replyKey, (m) => ({ ...m, status: "error", error: message, tool: null }));
          AccessibilityInfo.announceForAccessibility(message);
        }
      } finally {
        abort.current = null;
        setBusy(false);
      }
    },
    [patch],
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
        <Empty icon="message-circle" title="Not available" text="The AI assistant is turned off right now." action="Go back" onAction={() => router.back()} />
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
          headerRight: () => (msgs.length ? <IconButton icon="trash-2" label="Clear conversation" size={36} onPress={clear} /> : null),
        }}
      />
      {config?.basic || config?.demo ? (
        <View style={[styles.banner, { backgroundColor: c.surface2 }]}>
          <T v="tiny" tone="ink2">
            {config.demo ? "Demo mode · answers from the sample catalog" : "Basic mode · showing search results"}
          </T>
        </View>
      ) : null}

      {msgs.length === 0 ? (
        <ScrollView contentContainerStyle={styles.empty} keyboardShouldPersistTaps="handled">
          <Animated.View entering={FadeIn.duration(400)} style={[styles.orb, { backgroundColor: c.brand }]}>
            <Icon name="star" size={28} color="#fff" />
          </Animated.View>
          <T v="h3" style={{ textAlign: "center", maxWidth: 300 }}>
            {config?.greeting?.[lang] ?? "Hi! Tell me what you're looking for and your budget in taka."}
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
              <Chip label={lang === "bn" ? "এই পণ্য সম্পর্কে জানতে চাই" : "Ask about this product"} onPress={() => send(lang === "bn" ? "এই পণ্যটি কেমন? কবে পাব?" : "Is this product worth it, and when would it arrive?")} />
            )}
            {prompts.map((p, i) => (
              <Animated.View key={p} entering={FadeInDown.delay(80 + i * 50).springify().damping(18)}>
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
            Daily AI limit reached.{user ? " Try again tomorrow." : " Sign in for more messages."}
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
            accessibilityLabel="Message Skydrop AI"
            onSubmitEditing={() => send(draft)}
            submitBehavior="submit"
            returnKeyType="send"
          />
          {busy ? (
            <IconButton icon="square" label="Stop generating" size={44} bg={c.ink} tint={c.bg} onPress={() => abort.current?.abort()} />
          ) : (
            <IconButton icon="arrow-up" label="Send message" size={44} bg={draft.trim() && !out ? c.brand : withAlpha(c.brand, 0.35)} tint="#fff" onPress={() => send(draft)} />
          )}
        </View>
        <T v="tiny" tone="ink3" style={{ textAlign: "center", marginTop: 6 }}>
          AI can make mistakes. Prices are re-checked at checkout.
        </T>
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

const TOOL: Record<string, string> = {
  search_products: "Searching the catalog",
  get_product: "Reading product details",
  compare_products: "Comparing",
  delivery_estimate: "Checking delivery dates",
  get_my_orders: "Looking up your orders",
  track_order: "Tracking your order",
};

function Bubble({ m, last, busy, onRate, onRegenerate }: { m: Msg; last: boolean; busy: boolean; onRate: (m: Msg, r: "up" | "down") => void; onRegenerate: () => void }) {
  const { c } = useTheme();
  if (m.role === "user") {
    return (
      <Animated.View entering={FadeInDown.duration(220)} style={[styles.user, { backgroundColor: c.brand }]} accessibilityLabel={`You said: ${m.text}`}>
        <T tone="white">{m.text}</T>
      </Animated.View>
    );
  }
  const streaming = m.status === "streaming";
  return (
    <Animated.View entering={FadeInDown.duration(220)} style={{ gap: 8 }}>
      {(m.text || streaming) && (
        <View style={[styles.bot, { backgroundColor: c.surface }, shadow(c)]} accessibilityLabel={m.text ? `Skydrop AI: ${m.text.replace(/\*\*/g, "")}` : "Skydrop AI is replying"}>
          <View style={{ flexDirection: "row", flexWrap: "wrap", alignItems: "flex-end" }}>
            <T>{m.text.replace(/\*\*/g, "")}</T>
            {streaming && <Caret />}
          </View>
        </View>
      )}
      {streaming && m.tool && (
        <T v="tiny" tone="ink2">
          {TOOL[m.tool] ?? "Working"}…
        </T>
      )}
      {m.cards.map((card, i) => (
        <Animated.View key={i} entering={FadeInDown.delay(i * 60).duration(260)}>
          <Card card={card} />
        </Animated.View>
      ))}
      {m.status === "error" && (
        <T v="small" tone="bad" accessibilityRole="alert">
          {m.error}
        </T>
      )}
      {m.status === "stopped" && (
        <T v="tiny" tone="ink3">
          Stopped.
        </T>
      )}
      {!streaming && (
        <View style={{ flexDirection: "row", gap: 6 }}>
          {m.status === "done" && (
            <>
              <IconButton icon="thumbs-up" label="Helpful" size={36} tint={m.feedback === "up" ? c.brand : c.ink3} bg="transparent" onPress={() => onRate(m, "up")} />
              <IconButton icon="thumbs-down" label="Not helpful" size={36} tint={m.feedback === "down" ? c.bad : c.ink3} bg="transparent" onPress={() => onRate(m, "down")} />
            </>
          )}
          {last && !busy && <IconButton icon="refresh-cw" label="Regenerate answer" size={36} tint={c.ink3} bg="transparent" onPress={onRegenerate} />}
        </View>
      )}
    </Animated.View>
  );
}

function useAdd() {
  const { addToCart } = useStore();
  const toast = useToast();
  return (p: AiProduct, qty = 1, variant?: string | null) => {
    haptic.success();
    addToCart({ productId: p.id, market: p.market, title: p.title, image: p.image, placeholder: p.placeholder ?? undefined, variant: variant ?? undefined, unitBdt: p.priceBdt, weightKg: p.weightKg, qty });
    toast({ kind: "success", title: "Added to cart", body: p.title.slice(0, 80), action: { label: "View cart", href: "/cart" } });
  };
}

function AddBtn({ p, qty, variant }: { p: AiProduct; qty?: number; variant?: string | null }) {
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
      accessibilityLabel={blocked ? "Not available" : `Add ${p.title} to cart`}
      style={[styles.add, { backgroundColor: blocked ? c.surface2 : c.brand }]}
    >
      <Icon name={done ? "check" : "shopping-cart"} size={14} color={blocked ? c.ink3 : "#fff"} />
      <T v="small" tone={blocked ? "ink3" : "white"} style={{ fontWeight: "700" }}>
        {blocked ? (p.inStock ? "Can't ship" : "Out of stock") : done ? "Added" : "Add to cart"}
      </T>
    </Tap>
  );
}

function ProductTile({ p }: { p: AiProduct }) {
  const { c } = useTheme();
  return (
    <View style={[styles.tile, { backgroundColor: c.surface }, shadow(c)]}>
      <Tap onPress={() => router.push({ pathname: "/product/[id]", params: { id: p.id } })} accessibilityLabel={`${p.title}, ${formatBdt(p.priceBdt)}`} style={{ flex: 1 }}>
        <ProductImage uri={p.image} placeholder={p.placeholder ?? undefined} style={{ height: 120, borderTopLeftRadius: radius.md, borderTopRightRadius: radius.md }} />
        <View style={{ padding: 8, gap: 3, flex: 1 }}>
          <T v="tiny" numberOfLines={2} style={{ fontWeight: "600", color: c.ink }}>
            {p.title}
          </T>
          {p.rating > 0 && <Stars rating={p.rating} count={p.reviews} />}
          <T v="h3">{formatBdt(p.priceBdt)}</T>
        </View>
      </Tap>
      <View style={{ paddingHorizontal: 8, paddingBottom: 8 }}>
        <AddBtn p={p} />
      </View>
    </View>
  );
}

function OrderRow({ o }: { o: AiOrder }) {
  const { c } = useTheme();
  return (
    <Tap onPress={() => router.push({ pathname: "/order/[id]", params: { id: o.id } })} style={[styles.card, { backgroundColor: c.surface }, shadow(c)]} accessibilityLabel={`Order ${o.id}, ${o.status}`}>
      <T v="h3">
        {o.id} · {o.status.replace(/_/g, " ")}
      </T>
      <T v="small" tone="ink2">
        {o.itemCount} items · {formatBdt(o.total)}
        {o.expectedBy ? ` · expected by ${o.expectedBy}` : ""}
      </T>
    </Tap>
  );
}

function Card({ card }: { card: AiCard }) {
  const { c } = useTheme();
  switch (card.type) {
    case "products":
      return (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 10, paddingRight: 14 }} accessibilityLabel={`${card.data.items.length} products`}>
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
              {formatBdt(card.data.product.priceBdt)}
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
            {card.data.minDays}-{card.data.maxDays} days to your door
          </T>
          <T v="small" tone="ink2">
            Order today: arrives {card.data.earliest} to {card.data.latest}
          </T>
          {card.data.cutoffs.map((x) => (
            <T key={x.name} v="small" tone={x.status === "on_time" ? "good" : x.status === "maybe" ? "warn" : "bad"}>
              {x.name}: {x.status === "on_time" ? `order by ${x.orderBy}` : x.status === "maybe" ? "may arrive late" : "too late"}
            </T>
          ))}
        </View>
      );
    case "login":
      return (
        <Tap onPress={() => router.push("/login")} style={[styles.card, { backgroundColor: c.surface }, shadow(c)]} accessibilityLabel="Sign in to see your orders">
          <T v="h3">Sign in to see your orders</T>
          <T v="small" tone="ink2">
            Phone number and one-time code
          </T>
        </Tap>
      );
    case "priceHistory":
      return (
        <View style={[styles.card, { backgroundColor: c.surface }, shadow(c)]}>
          <T v="small">
            Now {formatBdt(card.data.currentBdt)} · low {formatBdt(Math.min(...card.data.points.map((p) => p.bdt)))} · high{" "}
            {formatBdt(Math.max(...card.data.points.map((p) => p.bdt)))}
          </T>
        </View>
      );
    case "request":
      return (
        <View style={[styles.card, { backgroundColor: c.surface }, shadow(c)]}>
          <T v="small">Request sent: {card.data.title}</T>
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
