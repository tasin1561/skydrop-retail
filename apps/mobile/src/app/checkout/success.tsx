// Payment gateway return target: skydropretail://checkout/success?order=<id>
// Opened when the gateway redirects back outside the in-app auth session (e.g. the browser
// was closed and the link opened the app). Refreshes the order and shows the success screen.
import { useEffect } from "react";
import { ActivityIndicator, View } from "react-native";
import { router, useLocalSearchParams } from "expo-router";
import { api } from "@/api/client";
import { useTheme } from "@/theme";
import { T } from "@/components/ui";
import { useT } from "@/i18n";

export default function PaymentReturn() {
  const { t } = useT();
  const { order } = useLocalSearchParams<{ order?: string }>();
  const { c } = useTheme();
  useEffect(() => {
    if (!order) {
      router.replace("/orders");
      return;
    }
    api
      .order(order)
      .then((o) => router.replace({ pathname: "/success", params: { id: o.id, total: String(o.total), paid: o.paymentStatus ?? "" } }))
      .catch(() => router.replace(`/order/${order}`));
  }, [order]);
  return (
    <View style={{ flex: 1, backgroundColor: c.bg, alignItems: "center", justifyContent: "center", gap: 12 }}>
      <ActivityIndicator color={c.brand} />
      <T v="small" tone="ink2">{t("m.confirmingPay")}</T>
    </View>
  );
}
