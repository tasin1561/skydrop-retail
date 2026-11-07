"use client";

import "./shopping.css";
import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { ProductImage } from "@/components/ProductBits";
import { useToast } from "@/components/Toast";
import { apiEnabled, ApiError } from "@/lib/apiClient";
import { MARKET_IDS, STORES } from "@/lib/markets";
import { groupSavingPreview, groups } from "@/lib/shopping/client";
import type { Group } from "@/lib/shopping/types";
import type { MarketId } from "@/lib/types";
import { useStore } from "@/state/store";
import { useT } from "@/i18n/client";
import { rich } from "@/i18n/rich";
import { hasKey, type MsgKey } from "@/i18n/core";
import { StoreName } from "@/components/store/StoreName";

const HOURS = [24, 48, 72] as const;

/** /group: start a group buy from your cart for one store, and see your groups. */
export function GroupStart({ initialMarket }: { initialMarket?: MarketId }) {
  const { cart, user, addresses, ready } = useStore();
  const router = useRouter();
  const toast = useToast();
  const { t, bdt, digits } = useT();
  const counts = useMemo(() => Object.fromEntries(MARKET_IDS.map((m) => [m, cart.filter((i) => i.market === m).length])) as Record<MarketId, number>, [cart]);
  const best = [...MARKET_IDS].sort((a, b) => counts[b] - counts[a])[0];
  const [market, setMarket] = useState<MarketId | null>(initialMarket ?? null);
  const m = market ?? best;
  const items = cart.filter((i) => i.market === m);
  const [hours, setHours] = useState<(typeof HOURS)[number]>(48);
  const [busy, setBusy] = useState(false);
  const [mine, setMine] = useState<Group[] | null>(null);

  useEffect(() => {
    if (!ready || (apiEnabled && !user)) return;
    let live = true;
    groups
      .mine()
      .then((g) => live && setMine(g))
      .catch(() => live && setMine([]));
    return () => {
      live = false;
    };
  }, [ready, user]);

  async function start() {
    if (apiEnabled && !user) {
      router.push(`/login?next=/group?market=${m}`);
      return;
    }
    setBusy(true);
    try {
      const addr = addresses.find((a) => a.isDefault) ?? addresses[0];
      const g = await groups.create(
        m,
        hours,
        {
          items: items.map((i) => ({ productId: i.productId, variant: i.variant ?? null, qty: i.qty })),
          ...(addr && /^\d+$/.test(addr.id) ? { addressId: Number(addr.id) } : {}),
          payment: "bkash",
        },
        items,
        user?.name?.split(" ")[0] ?? t("grp.you"),
      );
      router.push(`/group/${g.code}`);
    } catch (e) {
      toast({ kind: "error", title: t("grp.startFailed"), body: e instanceof ApiError ? e.message : t("common.pleaseRetry") });
      setBusy(false);
    }
  }

  const ti = HOURS.indexOf(hours);
  const preview = groupSavingPreview(items);
  return (
    <div className="flex flex-col gap-5">
      <section className="card p-4 sm:p-6">
        <h2 className="font-extrabold">{t("grp.step1")}</h2>
        <div className="mt-3 grid grid-cols-3 gap-2">
          {MARKET_IDS.map((id) => (
            <button
              key={id}
              type="button"
              data-store={id}
              onClick={() => setMarket(id)}
              aria-pressed={m === id}
              className={
                "flex min-h-[64px] flex-col items-center justify-center gap-0.5 rounded-2xl border px-2 text-center transition " +
                (m === id ? "border-accent bg-[var(--accent-soft)]" : "border-line bg-surface")
              }
            >
              <StoreName mode={id} size="text-sm sm:text-base" className="leading-tight" />
              <span className="text-xs font-semibold text-ink-2">{t("grp.inCart", { count: counts[id] })}</span>
            </button>
          ))}
        </div>

        <h2 className="mt-6 font-extrabold">{t("grp.step2")}</h2>
        <div className="sh-tabs mt-3" role="tablist" aria-label={t("grp.deadline")}>
          <span className="sh-tabs__ind" aria-hidden style={{ width: "calc((100% - 8px) / 3)", transform: `translateX(${ti * 100}%)` }} />
          {HOURS.map((h) => (
            <button key={h} type="button" role="tab" aria-selected={h === hours} onClick={() => setHours(h)}>
              {t("grp.hours", { count: h })}
            </button>
          ))}
        </div>

        <h2 className="mt-6 font-extrabold">{t("grp.step3")}</h2>
        {items.length ? (
          <ul className="mt-3 flex flex-col gap-2">
            {items.map((i) => (
              <li key={i.productId + (i.variant ?? "")} className="flex items-center gap-3 rounded-2xl bg-surface-2/70 p-2 pr-3">
                <span className="h-11 w-11 shrink-0 overflow-hidden rounded-xl bg-white p-1">
                  <ProductImage p={i} className="h-full w-full" />
                </span>
                <span className="line-clamp-2 min-w-0 flex-1 text-[13px]">{i.title}</span>
                <span className="text-xs font-bold tabular-nums text-ink-2">×{digits(i.qty)}</span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-2 text-sm text-ink-2">
            {rich(t("grp.noItems", { store: STORES[m].name }), {
  shop: (
    <Link href={`/store/${m}`} className="font-semibold text-accent">
      {t("grp.shopStore", { store: STORES[m].name })}
    </Link>
  ),
})}
          </p>
        )}

        {preview > 0 && (
          <p className="mt-4 rounded-xl bg-[color-mix(in_srgb,var(--good)_12%,transparent)] px-3 py-2 text-sm font-semibold text-good">
            {t("grp.preview", { amount: bdt(preview) })}
          </p>
        )}
        <button type="button" className="btn btn-primary mt-5 min-h-12 w-full" disabled={busy || !ready} onClick={start}>
          {busy ? t("grp.starting") : t("grp.start")}
        </button>
        <p className="mt-2 text-center text-xs text-ink-2">{t("grp.everyonePays")}</p>
      </section>

      {mine && mine.length > 0 && (
        <section>
          <h2 className="mb-2 font-extrabold">{t("grp.yours")}</h2>
          <ul className="flex flex-col gap-2">
            {mine.map((g) => (
              <li key={g.code}>
                <Link href={`/group/${g.code}`} className="card flex min-h-[56px] items-center gap-3 px-4 py-3">
                  <span className="min-w-0 flex-1 text-sm font-semibold text-ink-2">
                    <StoreName mode={g.market} size="text-[15px]" /> · {t("grp.members", { count: g.members.length })}
                  </span>
                  <span className="rounded-full bg-surface-2 px-2.5 py-1 text-xs font-bold capitalize text-ink-2">{hasKey("grp.st." + g.status) ? t(("grp.st." + g.status) as MsgKey) : g.status}</span>
                </Link>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}
