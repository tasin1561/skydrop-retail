import { useCallback, useState } from "react";
import { RefreshControl, ScrollView, StyleSheet, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { router } from "expo-router";
import { api } from "@/api/client";
import { useStore } from "@/state/store";
import { STORES , storeText } from "@/lib/markets";
import { ACCENTS, radius, useTheme } from "@/theme";
import { LinearGradient } from "expo-linear-gradient";
import { Icon } from "@/components/Icon";
import { StoreSwitcher } from "@/components/StoreSwitcher";
import { CategoryChips, HowItWorks, Rail, SearchBar, StoreHero, useAsync } from "@/components/Shop";
import { IconButton, Pill, T, Tap } from "@/components/ui";
import type { MarketId, StoreMode } from "@/lib/types";
import { useNotifications } from "@/lib/notifications";
import { AskAiButton } from "@/components/AskAiButton";
import { CouponPromo } from "@/components/CartBits";
import { useT } from "@/i18n";

export default function Home() {
  const { t } = useT();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const { mode, setMode, enabled, demo, totals, recent } = useStore();
  const [refreshing, setRefreshing] = useState(false);
  const notes = useNotifications();

  const popular = useAsync(() => api.rail(mode, "popular"), [mode]);
  const deals = useAsync(() => api.rail(mode, "deals"), [mode]);
  const live = useAsync(() => api.offers(mode, 12), [mode]);
  const recentKey = recent.join(",");
  const viewed = useAsync(() => api.products(recent), [recentKey]);
  const firstCat = STORES[mode].categories[0]?.slug;
  const catRail = useAsync(() => api.rail(mode, "popular", firstCat), [mode, firstCat]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    popular.reload();
    deals.reload();
    catRail.reload();
    live.reload();
    setTimeout(() => setRefreshing(false), 700);
  }, [popular, deals, catRail, live]);

  const markets = enabled.filter((m): m is MarketId => m !== "all");

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: c.bg }}
      contentContainerStyle={{ paddingTop: insets.top + 8, paddingBottom: 32 }}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={c.brand} colors={[c.brand]} />}
    >
      <View style={styles.top}>
        <View style={{ flex: 1 }}>
          <T v="label" tone="ink3">{t("m.tagline")}</T>
          <T v="h1" accessibilityRole="header">
            Skydrop Retail
          </T>
        </View>
        {demo && <Pill text={t("m.demoData")} icon="info" />}
        <AskAiButton />
        <IconButton icon="zap" label={t("home.liveDeals")} tint={c.coral} onPress={() => router.push({ pathname: "/deals", params: { store: mode } })} />
        {notes.enabled && (
          <IconButton
            icon="bell"
            label={notes.unread > 0 ? t("nt.unreadAria", { count: notes.unread }) : t("meta.notifications")}
            badge={notes.unread}
            onPress={() => router.push("/notifications")}
          />
        )}
        <IconButton icon="shopping-cart" label={t("nav.cartCount", { count: totals.count })} badge={totals.count} onPress={() => router.navigate("/cart")} />
      </View>

      <View style={{ paddingHorizontal: 16, marginBottom: 12 }}>
        <StoreSwitcher value={mode} onChange={setMode} modes={enabled as StoreMode[]} compact />
      </View>

      <SearchBar mode={mode} />
      <View style={{ height: 14 }} />
      <StoreHero mode={mode} />
      <View style={{ height: 16 }} />
      <CategoryChips mode={mode} />
      <CouponPromo store={mode} style={{ paddingHorizontal: 16, marginTop: 14 }} />

      {mode === "all" && markets.length > 0 && (
        <View style={{ marginTop: 20, paddingHorizontal: 16, gap: 10 }}>
          <T v="h2" accessibilityRole="header">{t("deals.stores")}</T>
          <View style={{ flexDirection: "row", gap: 10 }}>
            {markets.map((m) => (
              <StoreTile key={m} m={m} />
            ))}
          </View>
        </View>
      )}

      <Rail
        title={t("home.liveDeals")}
        items={live.data ?? []}
        loading={live.loading}
        onMore={() => router.push({ pathname: "/deals", params: { store: mode } })}
      />
      <Rail
        title={mode === "all" ? t("m.popularAll") : t("m.popularOn", { store: STORES[mode].name })}
        items={popular.data ?? []}
        loading={popular.loading}
        onMore={() => router.push({ pathname: "/search", params: { store: mode } })}
      />
      <Rail title={t("m.biggestSavings")} items={deals.data ?? []} loading={deals.loading} onMore={() => router.push({ pathname: "/search", params: { store: mode, sort: "discount" } })} />
      {firstCat && (
        <Rail
          title={storeText(mode, t).categories[0].label}
          items={catRail.data ?? []}
          loading={catRail.loading}
          onMore={() => router.push({ pathname: "/search", params: { store: mode, category: firstCat } })}
        />
      )}
      {recent.length > 0 && (
        <Rail
          title={t("home.recent")}
          // keep the viewing order and the current store filter
          items={recent
            .map((id) => viewed.data?.find((p) => p.id === id))
            .filter((p): p is NonNullable<typeof p> => !!p && (mode === "all" || p.market === mode))}
          loading={viewed.loading && !viewed.data}
        />
      )}
      <HowItWorks mode={mode} />
    </ScrollView>
  );
}


function StoreTile({ m }: { m: MarketId }) {
  const { t } = useT();
  const a = ACCENTS[m];
  const s = STORES[m];
  return (
    <Tap style={{ flex: 1 }} onPress={() => router.push(`/store/${m}`)} accessibilityLabel={t("m.storeTileA11y", { name: s.name, source: s.source })}>
      <LinearGradient colors={[a.heroA, a.heroB]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={styles.tile}>
        <Icon name={s.icon} size={20} color="#fff" />
        <T v="h3" tone="white" style={{ fontSize: 14, marginTop: 8 }} numberOfLines={1}>
          {s.name}
        </T>
        <T v="tiny" style={{ color: "rgba(255,255,255,0.85)" }} numberOfLines={1}>
          {s.source}
        </T>
      </LinearGradient>
    </Tap>
  );
}

const styles = StyleSheet.create({
  top: { flexDirection: "row", alignItems: "center", gap: 10, paddingHorizontal: 16, marginBottom: 14 },
  tile: { borderRadius: radius.md, padding: 12, minHeight: 92 },
});
