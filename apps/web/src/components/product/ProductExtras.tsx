"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { api, apiEnabled } from "@/lib/apiClient";
import { track } from "@/lib/track";
import { pointsToEarn } from "@/lib/points";
import { useStore } from "@/state/store";
import { useT } from "@/i18n/client";

const WATCH_KEY = "skydrop-retail:watches";
const RECENT_KEY = "skydrop-retail:recent";

function readJson<T>(key: string, fallback: T): T {
  try {
    return JSON.parse(localStorage.getItem(key) ?? "") as T;
  } catch {
    return fallback;
  }
}

/** "Earn N points" and price-drop / back-in-stock alerts under the price. */
export function ProductExtras({ id, priceBdt, inStock }: { id: string; priceBdt: number; inStock: boolean }) {
  const { user } = useStore();
  const router = useRouter();
  const toast = useToast();
  const { t, bdt } = useT();
  const [watching, setWatching] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    // remember this product in "recently viewed"
    const list = readJson<string[]>(RECENT_KEY, []).filter((x) => x !== id);
    try {
      localStorage.setItem(RECENT_KEY, JSON.stringify([id, ...list].slice(0, 20)));
    } catch {
      /* ignore */
    }
    const w = readJson<string[]>(WATCH_KEY, []);
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setWatching(w.includes(id));
  }, [id]);

  async function toggleWatch() {
    if (apiEnabled && !user) {
      router.push(`/login?next=/p/${encodeURIComponent(id)}`);
      return;
    }
    const type = inStock ? "price_drop" : "back_in_stock";
    setBusy(true);
    try {
      if (apiEnabled) {
        // removing must reach the server too, or the SMS alerts keep coming
        if (watching) await api.unwatch(id);
        else await api.watch(id, type, inStock ? priceBdt - 1 : undefined);
      }
      if (!watching) track("Lead", { items: [{ id, price: priceBdt }], value: priceBdt });
      const list = readJson<string[]>(WATCH_KEY, []);
      const next = watching ? list.filter((x) => x !== id) : [...list, id];
      localStorage.setItem(WATCH_KEY, JSON.stringify(next));
      setWatching(!watching);
      toast({
        kind: watching ? "info" : "success",
        title: watching ? t("px.alertRemoved") : inStock ? t("px.tellDrop") : t("px.tellBack"),
        body: watching ? undefined : t("px.sms"),
      });
    } catch {
      toast({ kind: "error", title: t("px.alertFailed"), body: t("common.pleaseRetry") });
    } finally {
      setBusy(false);
    }
  }

  const earn = pointsToEarn(priceBdt);
  return (
    <div className="mt-3 flex flex-wrap items-center gap-2">
      {earn > 0 && (
        <span className="inline-flex min-h-9 items-center gap-1.5 rounded-[8px] border border-line-2 px-3 text-xs font-medium text-ink-2">
          <Icon name="spark" size={14} stroke={2} /> {t("px.earn", { count: earn })}
        </span>
      )}
      <button
        type="button"
        onClick={toggleWatch}
        disabled={busy}
        aria-pressed={watching}
        className={
          "inline-flex min-h-9 items-center gap-1.5 rounded-[8px] border px-3 text-xs font-medium transition " +
          (watching ? "border-primary bg-primary text-on-primary" : "border-line-2 text-ink-2 hover:border-ink-3 hover:text-ink")
        }
      >
        <Icon name={watching ? "check" : "clock"} size={14} stroke={2} />
        {watching ? (inStock ? t("px.watching", { price: bdt(priceBdt) }) : t("px.backOn")) : inStock ? t("px.alertDrop") : t("px.tellMe")}
      </button>
    </div>
  );
}

export function recentlyViewedIds(): string[] {
  return readJson<string[]>(RECENT_KEY, []);
}
