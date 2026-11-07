"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { api, apiEnabled } from "@/lib/apiClient";
import { pointsToEarn } from "@/lib/points";
import { formatBdt } from "@/lib/pricing";
import { useStore } from "@/state/store";

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
      if (apiEnabled && !watching) await api.watch(id, type, inStock ? priceBdt - 1 : undefined);
      const list = readJson<string[]>(WATCH_KEY, []);
      const next = watching ? list.filter((x) => x !== id) : [...list, id];
      localStorage.setItem(WATCH_KEY, JSON.stringify(next));
      setWatching(!watching);
      toast({
        kind: watching ? "info" : "success",
        title: watching ? "Alert removed" : inStock ? "We'll tell you when the price drops" : "We'll tell you when it's back",
        body: watching ? undefined : "You'll get an SMS as soon as it happens.",
      });
    } catch {
      toast({ kind: "error", title: "Couldn't set the alert", body: "Please try again." });
    } finally {
      setBusy(false);
    }
  }

  const earn = pointsToEarn(priceBdt);
  return (
    <div className="mt-3 flex flex-wrap items-center gap-2">
      {earn > 0 && (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-[var(--accent-soft)] px-3 py-1.5 text-xs font-bold text-accent">
          <Icon name="spark" size={14} stroke={2} /> Earn {earn} Skydrop Points
        </span>
      )}
      <button
        type="button"
        onClick={toggleWatch}
        disabled={busy}
        aria-pressed={watching}
        className={
          "inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 text-xs font-bold transition " +
          (watching ? "bg-accent text-white" : "bg-surface-2 text-ink-2 hover:text-accent")
        }
      >
        <Icon name={watching ? "check" : "clock"} size={14} stroke={2} />
        {watching ? (inStock ? `Watching price (${formatBdt(priceBdt)})` : "Back-in-stock alert on") : inStock ? "Alert me if the price drops" : "Tell me when it's back"}
      </button>
    </div>
  );
}

export function recentlyViewedIds(): string[] {
  return readJson<string[]>(RECENT_KEY, []);
}
