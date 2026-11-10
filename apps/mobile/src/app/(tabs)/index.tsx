import { useCallback, useState } from "react";
import { RefreshControl, ScrollView, StyleSheet, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { router } from "expo-router";
import { api } from "@/api/client";
import { useStore } from "@/state/store";
import { STORES , storeText } from "@/lib/markets";
import { STORE_TEXT, radius, useTheme, withAlpha } from "@/theme";
import { StoreSwitcher } from "@/components/StoreSwitcher";
import { HowItWorks, Rail, SearchBar, StoreHero, useAsync } from "@/components/Shop";
import { CategoryTiles } from "@/components/home/CategoryTiles";
import { IconButton, Pill, T, Tap } from "@/components/ui";
import type { MarketId, StoreMode } from "@/lib/types";
import { useNotifications } from "@/lib/notifications";
import { AskAiButton } from "@/components/AskAiButton";
import { CouponPromo } from "@/components/CartBits";
import { useT } from "@/i18n";
import { StoreName } from "@/components/StoreName";

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

  // "Trending for you": top-rated women's clothing, bags/footwear and makeup, mixed so no single kind fills the row.
  const forYou = useAsync(async () => {
    const paths = ["fashion/women/clothing", "fashion/women", "beauty-personal-care/makeup"];
    const lists = await Promise.all(
      paths.map((category) => api.search({ q: "", store: mode, category, sort: "rating" }).then((r) => r.items).catch(() => [])),
    );
    const seen = new Set<string>();
    const out = [];
    for (let i = 0; out.length < 12 && lists.some((l) => i < l.length); i++) {
      for (const l of lists) {
        const p = l[i];
        if (p && !seen.has(p.id) && out.length < 12) {
          seen.add(p.id);
          out.push(p);
        }
      }
    }
    return out;
  }, [mode]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    forYou.reload();
    popular.reload();
    deals.reload();
    catRail.reload();
    live.reload();
    setTimeout(() => setRefreshing(false), 700);
  }, [popular, deals, catRail, live, forYou]);

  const markets = enabled.filter((m): m is MarketId => m !== "all");

  return (
    <ScrollView
      style={{ flex: 1, backgroundColor: c.bg }}
      contentContainerStyle={{ paddingTop: insets.top + 8, paddingBottom: 32 }}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={c.brand} colors={[c.brand]} />}
    >
      <View style={styles.top}>
        {/* One short line: the search box, store chips and category tiles get the first screen, not the header. */}
        <View style={{ flex: 1, minWidth: 0 }}>
          <T v="h2" accessibilityRole="header" numberOfLines={1} style={{ fontSize: 20 }} accessibilityHint={t("m.tagline")}>
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
        <StoreSwitcher value={mode} onChange={setMode} modes={enabled as StoreMode[]} compact label />
      </View>

      <SearchBar mode={mode} />
      {/* Category-first: photo tiles for what she shops most, then picks for her, then everything else. */}
      <CategoryTiles mode={mode} />
      <CouponPromo store={mode} style={{ paddingHorizontal: 16, marginTop: 14 }} />
      <Rail
        title={t("home.forYou")}
        items={forYou.data ?? []}
        loading={forYou.loading}
        onMore={() => router.push({ pathname: "/category/[...path]", params: { path: ["fashion", "women"], store: mode } })}
      />

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
      <View style={{ height: 24 }} />
      <StoreHero mode={mode} />
      <HowItWorks mode={mode} />
    </ScrollView>
  );
}


function StoreTile({ m }: { m: MarketId }) {
  const { t } = useT();
  const { c } = useTheme();
  const a = STORE_TEXT[m][c.dark ? "dark" : "light"];
  const s = STORES[m];
  return (
    <Tap style={{ flex: 1 }} onPress={() => router.push(`/store/${m}`)} accessibilityLabel={t("m.storeTileA11y", { name: s.name, source: s.source })}>
      <View style={[styles.tile, { backgroundColor: withAlpha(a.ring, c.dark ? 0.12 : 0.07), borderColor: withAlpha(a.ring, 0.35) }]}>
        <StoreName mode={m} size={17} />
        <T v="tiny" tone="ink2" style={{ marginTop: 4 }} numberOfLines={1}>
          {s.source}
        </T>
      </View>
    </Tap>
  );
}

const styles = StyleSheet.create({
  top: { flexDirection: "row", alignItems: "center", gap: 8, paddingHorizontal: 16, marginBottom: 10 },
  tile: { borderRadius: radius.md, borderWidth: 1, padding: 12, minHeight: 76, justifyContent: "center" },
});
