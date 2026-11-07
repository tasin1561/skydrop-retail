import { useMemo } from "react";
import { RefreshControl, SectionList, StyleSheet, View } from "react-native";
import { Stack, router } from "expo-router";
import Animated, { FadeInDown, LinearTransition } from "react-native-reanimated";
import { Icon } from "@/components/Icon";
import { Empty, IconChip, Skeleton, T, Tap } from "@/components/ui";
import { haptic } from "@/lib/haptics";
import { CHIP, timeAgo, useNotifications } from "@/lib/notifications";
import { openFromNotification } from "@/lib/push";
import { radius, useTheme, withAlpha } from "@/theme";
import { useStore } from "@/state/store";
import type { AppNotification } from "@/api/messaging";

export default function NotificationsScreen() {
  const { c } = useTheme();
  const { user } = useStore();
  const n = useNotifications();

  const sections = useMemo(() => {
    const start = new Date();
    start.setHours(0, 0, 0, 0);
    const today = n.items.filter((x) => new Date(x.createdAt) >= start);
    const earlier = n.items.filter((x) => new Date(x.createdAt) < start);
    return [
      { title: "Today", data: today },
      { title: "Earlier", data: earlier },
    ].filter((s) => s.data.length > 0);
  }, [n.items]);

  const header = (
    <Stack.Screen
      options={{
        title: "Notifications",
        headerRight: () =>
          n.unread > 0 ? (
            <Tap onPress={() => (haptic.light(), n.markAllRead())} accessibilityLabel="Mark all as read" style={{ paddingHorizontal: 8, paddingVertical: 6 }}>
              <T v="label" tone="brand" style={{ fontWeight: "700" }}>
                Read all
              </T>
            </Tap>
          ) : null,
      }}
    />
  );

  if (!user) {
    return (
      <View style={{ flex: 1, backgroundColor: c.bg }}>
        {header}
        <Empty icon="bell" title="Sign in for updates" text="Order, payment and delivery updates show up here." action="Sign in" onAction={() => router.push("/login")} />
      </View>
    );
  }
  if (!n.enabled) {
    return (
      <View style={{ flex: 1, backgroundColor: c.bg }}>
        {header}
        <Empty icon="bell-off" title="Not available" text="Notifications are switched off right now." />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: c.bg }}>
      {header}
      {!n.loaded ? (
        <View style={{ padding: 16, gap: 10 }}>
          {[0, 1, 2, 3].map((i) => (
            <Skeleton key={i} h={72} r={radius.md} />
          ))}
        </View>
      ) : (
        <SectionList
          sections={sections}
          keyExtractor={(x) => String(x.id)}
          contentContainerStyle={{ padding: 16, paddingBottom: 40, flexGrow: 1 }}
          stickySectionHeadersEnabled={false}
          refreshControl={<RefreshControl refreshing={n.loading && n.loaded} onRefresh={n.refresh} tintColor={c.brand} colors={[c.brand]} />}
          onEndReached={n.loadMore}
          onEndReachedThreshold={0.4}
          renderSectionHeader={({ section }) => (
            <T v="tiny" tone="ink3" style={styles.section}>
              {section.title.toUpperCase()}
            </T>
          )}
          renderItem={({ item, index }) => <Row n={item} index={index} onRead={n.markRead} onRemove={n.remove} />}
          ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
          ListEmptyComponent={<Empty icon="bell" title="You are all caught up" text="Order updates, price drops and offers will show up here." />}
        />
      )}
    </View>
  );
}

function Row({ n, index, onRead, onRemove }: { n: AppNotification; index: number; onRead: (id: number) => void; onRemove: (id: number) => void }) {
  const { c } = useTheme();
  const [icon, color] = CHIP[n.type] ?? [n.icon ?? "info", c.brand];
  return (
    <Animated.View entering={FadeInDown.delay(Math.min(index, 8) * 35).springify().damping(18)} layout={LinearTransition.springify()}>
      <Tap
        onPress={() => {
          onRead(n.id);
          if (n.link) openFromNotification({ link: n.link, route: (n.data as { route?: string })?.route });
        }}
        onLongPress={() => (haptic.medium(), onRemove(n.id))}
        accessibilityLabel={`${n.read ? "" : "Unread. "}${n.title}. ${n.body ?? ""}. Long press to delete.`}
        style={[styles.row, { backgroundColor: n.read ? c.surface : withAlpha(c.brand, c.dark ? 0.14 : 0.06), borderColor: c.line }]}
      >
        <IconChip icon={icon} color={color} size={40} />
        <View style={{ flex: 1, minWidth: 0 }}>
          <T v="body" style={{ fontWeight: n.read ? "600" : "800", color: n.read ? c.ink2 : c.ink }} numberOfLines={2}>
            {n.title}
          </T>
          {!!n.body && (
            <T v="label" tone="ink2" numberOfLines={3} style={{ marginTop: 2 }}>
              {n.body}
            </T>
          )}
          <T v="tiny" tone="ink3" style={{ marginTop: 4 }}>
            {timeAgo(n.createdAt)}
          </T>
        </View>
        <View style={{ alignItems: "center", gap: 10 }}>
          {!n.read && <View style={[styles.dot, { backgroundColor: c.brand, shadowColor: c.brand }]} />}
          <Tap onPress={() => onRemove(n.id)} accessibilityLabel="Delete notification" hitSlop={10}>
            <Icon name="x" size={16} color={c.ink3} />
          </Tap>
        </View>
      </Tap>
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  section: { fontWeight: "800", letterSpacing: 0.8, marginTop: 8, marginBottom: 8, marginLeft: 4 },
  row: { flexDirection: "row", alignItems: "flex-start", gap: 12, padding: 12, borderRadius: radius.md, borderWidth: StyleSheet.hairlineWidth },
  dot: { width: 9, height: 9, borderRadius: 5, marginTop: 6, shadowOpacity: 0.5, shadowRadius: 4, shadowOffset: { width: 0, height: 0 } },
});
