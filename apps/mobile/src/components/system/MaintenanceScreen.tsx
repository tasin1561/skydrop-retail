import { useState } from "react";
import { View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { Button, IconChip, T } from "@/components/ui";
import { reloadFeatures, useMaintenance } from "@/lib/features";
import { useTheme } from "@/theme";
import { useT } from "@/i18n";

/**
 * Full-screen "We'll be right back" when the storefront is in maintenance (Admin → System → Settings).
 * Shown over the whole app on start (GET /features → maintenance.on); "Try again" re-checks.
 */
export function MaintenanceOverlay() {
  const { t } = useT();
  const m = useMaintenance();
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const [busy, setBusy] = useState(false);
  if (!m.on) return null;
  return (
    <View
      accessibilityRole="alert"
      accessibilityLiveRegion="polite"
      style={{
        position: "absolute",
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        zIndex: 1000,
        backgroundColor: c.bg,
        alignItems: "center",
        justifyContent: "center",
        paddingTop: insets.top + 24,
        paddingBottom: insets.bottom + 24,
        paddingHorizontal: 28,
        gap: 12,
      }}
    >
      <IconChip icon="tool" size={72} />
      <T v="small" tone="ink3" style={{ marginTop: 12, letterSpacing: 2, fontWeight: "700" }}>
        SKYDROP RETAIL
      </T>
      <T v="h1" style={{ textAlign: "center" }}>
        {t("maint.title")}
      </T>
      <T v="body" tone="ink2" style={{ textAlign: "center", maxWidth: 360 }}>
        {m.message}
      </T>
      <Button
        label={busy ? t("coupon.checking") : t("maint.retry")}
        icon="refresh"
        busy={busy}
        style={{ marginTop: 12, paddingHorizontal: 28 }}
        onPress={() => {
          setBusy(true);
          reloadFeatures().finally(() => setBusy(false));
        }}
      />
      <T v="small" tone="ink3" style={{ textAlign: "center", marginTop: 8 }}>{t("m.safe")}</T>
    </View>
  );
}
