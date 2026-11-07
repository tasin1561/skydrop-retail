// Infinite two-column product grid backed by /search, with pull to refresh and skeletons.
import { useCallback, useEffect, useRef, useState, type ReactElement } from "react";
import { ActivityIndicator, FlatList, RefreshControl, View, useWindowDimensions } from "react-native";
import { useIsFocused } from "expo-router";
import { api, waitForJob } from "@/api/client";
import type { SearchParams } from "@/api/types";
import type { Product } from "@/lib/types";
import { useTheme } from "@/theme";
import { ProductCard, ProductCardSkeleton } from "./ProductCard";
import { Empty, T } from "./ui";
import { FetchingLive } from "./product/Extras";

export function ProductGrid({ params, header, onTotal }: { params: SearchParams; header?: ReactElement; onTotal?: (n: number) => void }) {
  const { c } = useTheme();
  const { width } = useWindowDimensions();
  const W = (width - 32 - 12) / 2;
  const [items, setItems] = useState<Product[]>([]);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [loading, setLoading] = useState(true);
  const [more, setMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const reqId = useRef(0);
  const [job, setJob] = useState<string | null>(null);
  const focused = useIsFocused();

  const key = JSON.stringify(params);
  // New query: show skeletons straight away (adjusting state during render, not in an effect).
  const [shownKey, setShownKey] = useState(key);
  if (key !== shownKey) {
    setShownKey(key);
    setLoading(true);
    setJob(null);
  }

  const load = useCallback(
    async (pg: number) => {
      // Callers set the loading/more flags (keeps this free of synchronous setState for effects).
      const id = ++reqId.current;
      try {
        const r = await api.search({ ...params, page: pg });
        if (id !== reqId.current) return;
        setItems((cur) => (pg === 1 ? r.items : [...cur, ...r.items.filter((x) => !cur.some((y) => y.id === x.id))]));
        setPage(r.page);
        setPages(r.pages);
        setError(null);
        onTotal?.(r.total);
        // pending: the API is fetching fresh results from the store; poll the job, then reload.
        if (pg === 1) setJob(r.status === "pending" ? (r.jobId ?? null) : null);
      } catch (e) {
        if (id === reqId.current) setError((e as Error).message);
      } finally {
        if (id === reqId.current) {
          setLoading(false);
          setMore(false);
        }
      }
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [key],
  );

  useEffect(() => {
    // load() only sets state after its first await, so this doesn't cascade renders.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    load(1);
  }, [load]);

  // Poll GET /jobs/{id} while the screen is focused; stop when it blurs.
  useEffect(() => {
    if (!job || !focused) return;
    const ctrl = new AbortController();
    waitForJob(job, ctrl.signal).then((done) => {
      if (ctrl.signal.aborted) return;
      setJob(null);
      if (done) load(1);
    });
    return () => ctrl.abort();
  }, [job, focused, load]);

  const store = params.store && params.store !== "all" ? params.store : null;

  return (
    <FlatList
      data={loading ? [] : items}
      numColumns={2}
      keyExtractor={(p) => p.id}
      columnWrapperStyle={{ gap: 12 }}
      contentContainerStyle={{ paddingHorizontal: 16, gap: 12, paddingBottom: 40 }}
      renderItem={({ item }) => <ProductCard p={item} width={W} />}
      ListHeaderComponent={
        header || job ? (
          <View style={{ gap: 8 }}>
            {header}
            {job && store && <FetchingLive market={store} />}
            {job && !store && (
              <T v="tiny" tone="ink3">
                Fetching live results from the stores…
              </T>
            )}
          </View>
        ) : undefined
      }
      keyboardDismissMode="on-drag"
      keyboardShouldPersistTaps="handled"
      onEndReachedThreshold={0.6}
      onEndReached={() => {
        if (!loading && !more && page < pages) {
          setMore(true);
          load(page + 1);
        }
      }}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          tintColor={c.brand}
          colors={[c.brand]}
          onRefresh={async () => {
            setRefreshing(true);
            await load(1);
            setRefreshing(false);
          }}
        />
      }
      ListEmptyComponent={
        loading ? (
          <View style={{ gap: 12 }}>
            {[0, 1].map((r) => (
              <View key={r} style={{ flexDirection: "row", gap: 12 }}>
                <ProductCardSkeleton width={W} />
                <ProductCardSkeleton width={W} />
              </View>
            ))}
          </View>
        ) : error ? (
          <Empty icon="wifi-off" title="Couldn't load products" text={error} action="Try again" onAction={() => {
              setLoading(true);
              load(1);
            }}
          />
        ) : (
          <Empty icon="search" title="No matches" text="Try a shorter search, another store or fewer filters." />
        )
      }
      ListFooterComponent={
        more ? (
          <ActivityIndicator color={c.brand} style={{ marginVertical: 16 }} />
        ) : !loading && items.length > 0 && page >= pages ? (
          <T v="tiny" tone="ink3" style={{ textAlign: "center", marginVertical: 16 }}>
            That's everything
          </T>
        ) : undefined
      }
      initialNumToRender={6}
      windowSize={7}
      removeClippedSubviews
    />
  );
}
