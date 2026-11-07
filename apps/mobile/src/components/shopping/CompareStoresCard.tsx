import { useEffect, useState } from "react";
import { StyleSheet, View } from "react-native";
import { router } from "expo-router";
import { Card, IconChip, T, Tap } from "@/components/ui";
import { Icon } from "@/components/Icon";
import { useFeature } from "@/lib/features";
import { STORES } from "@/lib/markets";
import { shopping, type CompareResult } from "@/lib/shopping";
import { useTheme } from "@/theme";
import { useT } from "@/i18n";

/** t("cmp.title"): the same product on other listings; tap a row to switch to it. */
export function CompareStoresCard({ id }: { id: string }) {
  const { t, bdt } = useT();
  const on = useFeature("compare_stores");
  const { c } = useTheme();
  const [data, setData] = useState<CompareResult | null>(null);

  useEffect(() => {
    if (!on) return;
    let live = true;
    shopping
      .compare(id)
      .then((r) => live && setData(r))
      .catch(() => live && setData(null));
    return () => {
      live = false;
    };
  }, [on, id]);

  if (!on || !data || data.items.length < 2) return null;
  const best = data.items.find((r) => r.best);
  return (
    <Card pad={0}>
      <View style={{ padding: 16, paddingBottom: 8 }}>
        <T v="h3">{t("cmp.title")}</T>
        {best && !best.current && data.savingBdt > 0 && (
          <T v="small" tone="good" style={{ fontWeight: "700", marginTop: 4 }}>
            {t("cmp.save", { amount: bdt(data.savingBdt), store: STORES[best.market].name })}
          </T>
        )}
      </View>
      {data.items.map((r) => (
        <Tap
          key={r.productId}
          disabled={r.current}
          onPress={() => router.push(`/product/${encodeURIComponent(r.productId)}`)}
          accessibilityLabel={[t("m.cmpA11y", { store: STORES[r.market].name, price: bdt(r.priceBdt) }), r.best ? t("cmp.best") : "", r.current ? t("cmp.viewing") : ""].filter(Boolean).join(", ")}
          style={[styles.row, { borderTopColor: c.line, backgroundColor: r.current ? c.surface2 : "transparent" }]}
          scaleTo={0.99}
        >
          <IconChip icon={STORES[r.market].icon} mode={r.market} size={34} />
          <View style={{ flex: 1 }}>
            <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }}>
              <T v="body" style={{ fontWeight: "800" }}>
                {STORES[r.market].name}
              </T>
              {r.best && (
                <View style={[styles.tag, { backgroundColor: c.good }]}>
                  <T v="tiny" tone="white" style={{ fontWeight: "800" }}>
                    {t("cmp.best")}
                  </T>
                </View>
              )}
            </View>
            <T v="small" tone="ink2">
              {r.inStock ? t("cmp.days", { min: r.delivery.min, max: r.delivery.max }) : t("stock.out")}
              {r.current ? ` · ${t("cmp.viewing")}` : ""}
            </T>
          </View>
          <T v="body" style={{ fontWeight: "800", color: r.best ? c.good : c.ink }}>
            {bdt(r.priceBdt)}
          </T>
          {!r.current && <Icon name="chevron-right" size={16} color={c.ink3} />}
        </Tap>
      ))}
    </Card>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", alignItems: "center", gap: 10, minHeight: 60, paddingHorizontal: 16, paddingVertical: 10, borderTopWidth: StyleSheet.hairlineWidth },
  tag: { borderRadius: 999, paddingHorizontal: 6, paddingVertical: 2 },
});
