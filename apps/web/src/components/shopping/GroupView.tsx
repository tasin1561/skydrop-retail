"use client";

import "./shopping.css";
import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Icon } from "@/components/Icon";
import { Countdown } from "@/components/offers/Countdown";
import { CopyButton } from "@/components/share/CopyButton";
import { useToast } from "@/components/Toast";
import { apiEnabled, ApiError } from "@/lib/apiClient";
import { STORES } from "@/lib/markets";
import { groups } from "@/lib/shopping/client";
import type { Group } from "@/lib/shopping/types";
import { useStore } from "@/state/store";
import { useT } from "@/i18n/client";
import { rich } from "@/i18n/rich";
import { hasKey, type MsgKey } from "@/i18n/core";

/** /group/[code]: live group buy view: members, deadline, shipping split and "each member saves ৳X". */
export function GroupView({ code }: { code: string }) {
  const { cart, user, addresses, ready } = useStore();
  const router = useRouter();
  const toast = useToast();
  const { t, bdt, digits } = useT();
  const [g, setG] = useState<Group | null | undefined>(undefined);
  const [busy, setBusy] = useState<string | null>(null);

  const load = useCallback(() => {
    groups
      .get(code)
      .then(setG)
      .catch(() => setG(null));
  }, [code]);

  useEffect(() => {
    if (!ready) return;
    load();
    const t = setInterval(() => !document.hidden && load(), 20_000); // live member list
    return () => clearInterval(t);
  }, [ready, load, user]);

  if (g === undefined) return <div className="skeleton h-72 rounded-2xl" aria-hidden />;
  if (g === null) {
    return (
      <div className="card p-8 text-center">
        <h1 className="text-lg font-extrabold">{t("grp.notFound")}</h1>
        <p className="mt-1 text-sm text-ink-2">{t("grp.notFoundBody")}</p>
        <Link href="/group" className="btn btn-primary mt-4 min-h-11">
{t("grp.startOwn")}
</Link>
      </div>
    );
  }

  const store = STORES[g.market];
  const mine = cart.filter((i) => i.market === g.market);
  const open = g.status === "open";
  const joined = !!g.you && g.you.status !== "left";
  const need = Math.max(0, g.minMembers - g.members.length);

  async function act(kind: "join" | "leave" | "close" | "cancel") {
    if (apiEnabled && !user) {
      router.push(`/login?next=/group/${code}`);
      return;
    }
    setBusy(kind);
    try {
      const addr = addresses.find((a) => a.isDefault) ?? addresses[0];
      const next =
        kind === "join"
          ? await groups.join(
              code,
              {
                items: mine.map((i) => ({ productId: i.productId, variant: i.variant ?? null, qty: i.qty })),
                ...(addr && /^\d+$/.test(addr.id) ? { addressId: Number(addr.id) } : {}),
                payment: g!.you?.payment ?? "bkash",
              },
              mine,
            )
          : await groups[kind](code);
      setG(next);
      toast({ kind: "success", title: { join: joined ? t("grp.updated") : t("grp.joined"), leave: t("grp.left"), close: t("grp.closed"), cancel: t("grp.cancelled") }[kind] });
    } catch (e) {
      toast({ kind: "error", title: t("common.didntWork"), body: e instanceof ApiError ? e.message : t("common.pleaseRetry") });
    } finally {
      setBusy(null);
    }
  }

  async function share() {
    const text = t("grp.shareText", { store: store.name });
    try {
      if (navigator.share) await navigator.share({ title: t("grp.groupBuy"), text, url: g!.shareUrl });
    } catch {
      /* dismissed */
    }
  }

  return (
    <div data-store={g.market} className="flex flex-col gap-4">
      <header className="card overflow-hidden">
        <div className="bg-[var(--hero-a)] px-5 py-6 text-white">
          <p className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-bold">
            <Icon name={store.icon} size={14} /> {t("grp.groupBuyStore", { store: store.name })}
          </p>
          <h1 className="mt-3 text-2xl font-extrabold tracking-tight">{g.isOwner || g.owner === "You" ? t("grp.yourOrder") : t("grp.ownerOrder", { owner: g.owner })}</h1>
          {open ? (
            <p className="mt-1 text-sm text-white/90">
              {rich(t("grp.closesIn", { source: store.source }), { time: <Countdown endsAt={g.closesAt} compact /> })}
            </p>
          ) : (
            <p className="mt-1 text-sm capitalize text-white/90">{hasKey("grp.st." + g.status) ? t(("grp.st." + g.status) as MsgKey) : g.status}{g.closeNote ? ` · ${g.closeNote}` : ""}</p>
          )}
        </div>
        <div className="grid grid-cols-3 divide-x divide-line text-center">
          <div className="p-3">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-ink-2">{t("grp.eachSaves")}</p>
            <p key={g.totals.eachSavesBdt} className="sh-bump text-lg font-extrabold tabular-nums text-good">
              {bdt(g.totals.eachSavesBdt)}
            </p>
          </div>
          <div className="p-3">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-ink-2">{t("grp.shipping")}</p>
            <p className="text-sm font-bold tabular-nums">
              {g.totals.grouped && g.totals.soloShippingBdt !== g.totals.groupShippingBdt && <s className="mr-1 text-ink-3">{bdt(g.totals.soloShippingBdt)}</s>}
              {bdt(g.totals.groupShippingBdt)}
            </p>
          </div>
          <div className="p-3">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-ink-2">{t("grp.membersTitle")}</p>
            <p className="text-lg font-extrabold tabular-nums">{digits(g.members.length)}</p>
          </div>
        </div>
      </header>

      {open && need > 0 && (
        <p className="flex items-center gap-2 rounded-2xl bg-[var(--accent-soft)] px-4 py-3 text-sm font-semibold text-accent">
          <Icon name="user" size={16} /> {t("grp.need", { count: need })}
        </p>
      )}

      {open && (
        <section className="card flex flex-wrap items-center gap-2 p-3">
          <code className="min-w-0 flex-1 truncate rounded-xl bg-surface-2 px-3 py-2.5 text-xs">{g.shareUrl}</code>
          <CopyButton text={g.shareUrl} label={t("common.copyLink")} />
          <button type="button" className="btn btn-ghost min-h-11 px-3 text-sm" onClick={share} aria-label={t("common.share")}>
            <Icon name="share" size={16} />
          </button>
        </section>
      )}

      <section className="card p-4">
        <h2 className="font-extrabold">{t("grp.membersTitle")}</h2>
        <ul className="mt-3 flex flex-col gap-2">
          {g.members.map((m, i) => (
            <li key={i} className="sh-member flex min-h-[52px] items-center gap-3 rounded-2xl bg-surface-2/70 px-3 py-2" style={{ ["--i" as string]: i }}>
              <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-accent text-sm font-extrabold text-white">{m.name.slice(0, 1).toUpperCase()}</span>
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-bold">
                  {/* demo groups name a signed-out shopper "You": show it in the page language, without "You (you)" */}
                  {m.isYou && m.name === "You" ? t("grp.you") : m.name}
                  {m.isYou && m.name !== "You" && t("grp.youSuffix")}
                  {m.isOwner && <span className="ml-1.5 rounded-full bg-surface px-1.5 py-0.5 text-[10px] font-bold text-ink-2">{t("grp.organiser")}</span>}
                </span>
                <span className="block text-xs text-ink-2">
                  {t("grp.memberLine", { count: m.items, kg: digits(m.weightKg.toFixed(1)) })}
                </span>
              </span>
              {m.savingBdt > 0 && <span className="text-xs font-bold tabular-nums text-good">{t("grp.saves", { amount: bdt(m.savingBdt) })}</span>}
            </li>
          ))}
        </ul>
      </section>

      {g.you?.orderId && (
        <Link href={`/orders/${g.you.orderId}?pay=1`} className="btn btn-primary min-h-12">
          {t("grp.payOrder", { id: g.you.orderId })}
        </Link>
      )}
      {g.you?.error && <p className="text-sm text-bad">{t("grp.orderFailed", { error: g.you.error })}</p>}

      {open && (
        <section className="card p-4">
          <h2 className="font-extrabold">{joined ? t("grp.yourItems") : t("grp.joinWithCart")}</h2>
          <p className="mt-1 text-sm text-ink-2">
            {mine.length
              ? t("grp.mineLine", { count: mine.length, store: store.name, amount: bdt(mine.reduce((s, i) => s + i.unitBdt * i.qty, 0)) })
: t("grp.addItems", { store: store.name })}
            {joined && g.you && g.you.savingBdt > 0 ? t("grp.youSave", { amount: bdt(g.you.savingBdt) }) : ""}
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            <button type="button" className="btn btn-primary min-h-11" disabled={!!busy || !mine.length} onClick={() => act("join")}>
              {busy === "join" ? t("common.saving") : joined ? t("grp.updateCart") : t("grp.join")}
            </button>
            {!mine.length && (
              <Link href={`/store/${g.market}`} className="btn btn-ghost min-h-11">
{t("hero.shop", { store: store.name })}
</Link>
            )}
            {joined && !g.isOwner && (
              <button type="button" className="btn btn-ghost min-h-11" disabled={!!busy} onClick={() => act("leave")}>
{t("grp.leave")}
</button>
            )}
          </div>
          {g.isOwner && (
            <div className="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
              <button type="button" className="btn btn-ghost min-h-11" disabled={!!busy} onClick={() => act("close")}>
                {t("grp.closeNow")}
              </button>
              <button type="button" className="btn btn-ghost min-h-11 text-bad" disabled={!!busy} onClick={() => act("cancel")}>
{t("grp.cancel")}
</button>
            </div>
          )}
          <p className="mt-3 text-xs text-ink-2">
            {t("grp.howWorks")}
          </p>
        </section>
      )}
      {g.demo && <p className="text-center text-xs text-ink-3">{t("grp.demo")}</p>}
    </div>
  );
}
