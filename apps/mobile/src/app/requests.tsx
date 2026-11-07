import { useCallback, useState } from "react";
import { FlatList, View } from "react-native";
import { router, useFocusEffect } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { Button, Card, Empty, Skeleton, T } from "@/components/ui";
import { shopping, type ProductRequest } from "@/lib/shopping";
import { useTheme } from "@/theme";
import { useT } from "@/i18n";
import type { MsgKey } from "@/i18n";

const LABEL: Record<string, MsgKey> = { new: "rq.st.new", sourcing: "rq.st.sourcing", quoted: "rq.st.quoted", ordered: "rq.st.ordered", declined: "rq.notAvail" };

/** My product requests with status and quote. */
export default function RequestsScreen() {
  const { t, bdt } = useT();
  const { c } = useTheme();
  const [items, setItems] = useState<ProductRequest[] | null>(null);

  useFocusEffect(
    useCallback(() => {
      let live = true;
      shopping
        .myRequests()
        .then((r) => live && setItems(r))
        .catch(() => live && setItems([]));
      return () => {
        live = false;
      };
    }, []),
  );

  if (!items) return <View style={{ padding: 16, gap: 12 }}>{[0, 1, 2].map((i) => <Skeleton key={i} h={90} />)}</View>;
  if (!items.length) return <Empty icon="search" title={t("m.noReqs")} text={t("m.noReqsB")} action={t("common.requestProduct")} onAction={() => router.push("/request")} />;
  return (
    <FlatList
      data={items}
      keyExtractor={(r) => r.id}
      contentContainerStyle={{ padding: 16, gap: 12 }}
      ListFooterComponent={<Button label={t("req.new")} kind="soft" icon="plus" onPress={() => router.push("/request")} style={{ marginTop: 4 }} />}
      renderItem={({ item: r }) => (
        <Card style={{ gap: 6 }}>
          <T v="body" style={{ fontWeight: "800" }} numberOfLines={2}>
            {r.description || r.url}
          </T>
          <T v="small" tone="ink2">
            {t("m.reqMeta", { id: r.id, qty: r.qty })}
            <T v="small" style={{ fontWeight: "800", color: r.status === "declined" ? c.ink3 : c.brand }}>{LABEL[r.status] ? t(LABEL[r.status]) : r.status}</T>
          </T>
          {r.quoteBdt != null && (
            <T v="small">
              {t("rq.ourPrice", { price: bdt(r.quoteBdt) })}
              {r.quoteNote ? ` · ${r.quoteNote}` : ""}
            </T>
          )}
          {r.payUrl && r.orderId && <Button label={t("common.payNow")} onPress={() => router.push(`/order/${r.orderId}`)} />}
          {!r.payUrl && r.url ? <Button label={t("m.openLink")} kind="ghost" onPress={() => WebBrowser.openBrowserAsync(r.url!)} /> : null}
        </Card>
      )}
    />
  );
}
