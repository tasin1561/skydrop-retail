import { useEffect, useState } from "react";
import { View } from "react-native";
import * as WebBrowser from "expo-web-browser";
import { router } from "expo-router";
import { api, ApiError, isDemo } from "@/api/client";
import { Button, Card, T } from "@/components/ui";
import { useToast } from "@/components/Toast";
import { useFeature } from "@/lib/features";
import { formatBdt } from "@/lib/pricing";
import { shopping, type PreorderInfo } from "@/lib/shopping";
import type { Product } from "@/lib/types";
import { useStore } from "@/state/store";

/**
 * Out of stock: restock waitlist count (the "Tell me when it's back" alert above adds you) and, when the admin
 * allows it, a pre-order with a deposit now and the balance when stock returns.
 */
export function WaitlistCard({ p, inStock }: { p: Product; inStock: boolean }) {
  const waitlistOn = useFeature("waitlist");
  const preordersOn = useFeature("preorders");
  const { user } = useStore();
  const toast = useToast();
  const [info, setInfo] = useState<PreorderInfo | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (inStock) return;
    let live = true;
    shopping
      .preorder(p, inStock)
      .then((i) => live && setInfo(i))
      .catch(() => live && setInfo(null));
    return () => {
      live = false;
    };
  }, [p, inStock]);

  if (inStock || !info) return null;
  const canPreorder = preordersOn && info.available && !!info.depositBdt;
  const waiting = waitlistOn && info.waitlist ? info.waitlist : 0;
  if (!canPreorder && !waiting) return null;

  async function preorder() {
    if (isDemo()) {
      toast({ kind: "info", title: "Demo store", body: "Pre-orders are taken once the live store is connected." });
      return;
    }
    if (!user) {
      router.push({ pathname: "/login", params: { next: `/product/${p.id}` } });
      return;
    }
    setBusy(true);
    try {
      const addresses = await api.addresses();
      const a = addresses.find((x) => x.isDefault) ?? addresses[0];
      if (!a) {
        toast({ kind: "info", title: "Add a delivery address first" });
        router.push("/addresses");
        return;
      }
      const returnUrl = "skydropretail://checkout/success";
      const r = await shopping.placePreorder({ productId: p.id, qty: 1, addressId: Number(a.id), payment: "bkash", returnUrl });
      if (r.payment.redirectUrl) await WebBrowser.openAuthSessionAsync(r.payment.redirectUrl, returnUrl);
      router.push(`/order/${r.order.id}`);
    } catch (e) {
      toast({ kind: "error", title: "Couldn't place the pre-order", body: e instanceof ApiError ? e.message : undefined });
    } finally {
      setBusy(false);
    }
  }

  return (
    <Card>
      <T v="h3">{canPreorder ? "Pre-order it now" : "Waiting for a restock"}</T>
      {waiting > 0 && (
        <T v="small" tone="ink2" style={{ marginTop: 4 }}>
          {waiting} {waiting === 1 ? "shopper is" : "shoppers are"} waiting for this item{info.watching ? ", including you" : ""}.
        </T>
      )}
      {canPreorder && (
        <View style={{ marginTop: 8, gap: 12 }}>
          <T v="small" tone="ink2">
            Pay a {info.depositPct}% deposit of {formatBdt(info.depositBdt!)} now and the rest when it&apos;s back{info.etaDays ? ` (about ${info.etaDays} days)` : ""}.
          </T>
          <Button label={`Pre-order · ${formatBdt(info.depositBdt!)} deposit`} onPress={preorder} busy={busy} mode={p.market} />
        </View>
      )}
    </Card>
  );
}
