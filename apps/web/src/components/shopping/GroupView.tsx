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
import { formatBdt } from "@/lib/pricing";
import { groups } from "@/lib/shopping/client";
import type { Group } from "@/lib/shopping/types";
import { useStore } from "@/state/store";

/** /group/[code]: live group buy view: members, deadline, shipping split and "each member saves ৳X". */
export function GroupView({ code }: { code: string }) {
  const { cart, user, addresses, ready } = useStore();
  const router = useRouter();
  const toast = useToast();
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
        <h1 className="text-lg font-extrabold">Group not found</h1>
        <p className="mt-1 text-sm text-ink-2">The link may be wrong, or the group was removed.</p>
        <Link href="/group" className="btn btn-primary mt-4 min-h-11">
          Start your own group
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
      toast({ kind: "success", title: { join: joined ? "Your items are updated" : "You joined the group", leave: "You left the group", close: "Group closed: orders placed", cancel: "Group cancelled" }[kind] });
    } catch (e) {
      toast({ kind: "error", title: "That didn't work", body: e instanceof ApiError ? e.message : "Please try again." });
    } finally {
      setBusy(null);
    }
  }

  async function share() {
    const text = `Join my ${store.name} group order on Skydrop Retail and we both save on shipping`;
    try {
      if (navigator.share) await navigator.share({ title: "Group buy", text, url: g!.shareUrl });
    } catch {
      /* dismissed */
    }
  }

  return (
    <div data-store={g.market} className="flex flex-col gap-4">
      <header className="card overflow-hidden">
        <div className="bg-[linear-gradient(135deg,var(--hero-a),var(--hero-b))] px-5 py-6 text-white">
          <p className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-bold">
            <Icon name={store.icon} size={14} /> Group buy · {store.name}
          </p>
          <h1 className="mt-3 text-2xl font-extrabold tracking-tight">{g.owner}&apos;s group order</h1>
          {open ? (
            <p className="mt-1 text-sm text-white/90">
              Closes in <Countdown endsAt={g.closesAt} compact /> · one shipment from {store.source}
            </p>
          ) : (
            <p className="mt-1 text-sm capitalize text-white/90">{g.status}{g.closeNote ? ` · ${g.closeNote}` : ""}</p>
          )}
        </div>
        <div className="grid grid-cols-3 divide-x divide-line text-center">
          <div className="p-3">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-ink-2">Each saves</p>
            <p key={g.totals.eachSavesBdt} className="sh-bump text-lg font-extrabold tabular-nums text-good">
              {formatBdt(g.totals.eachSavesBdt)}
            </p>
          </div>
          <div className="p-3">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-ink-2">Shipping</p>
            <p className="text-sm font-bold tabular-nums">
              {g.totals.grouped && g.totals.soloShippingBdt !== g.totals.groupShippingBdt && <s className="mr-1 text-ink-3">{formatBdt(g.totals.soloShippingBdt)}</s>}
              {formatBdt(g.totals.groupShippingBdt)}
            </p>
          </div>
          <div className="p-3">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-ink-2">Members</p>
            <p className="text-lg font-extrabold tabular-nums">{g.members.length}</p>
          </div>
        </div>
      </header>

      {open && need > 0 && (
        <p className="flex items-center gap-2 rounded-2xl bg-[var(--accent-soft)] px-4 py-3 text-sm font-semibold text-accent">
          <Icon name="user" size={16} /> {need} more {need === 1 ? "friend" : "friends"} needed for the group discount. Share the link!
        </p>
      )}

      {open && (
        <section className="card flex flex-wrap items-center gap-2 p-3">
          <code className="min-w-0 flex-1 truncate rounded-xl bg-surface-2 px-3 py-2.5 text-xs">{g.shareUrl}</code>
          <CopyButton text={g.shareUrl} label="Copy link" />
          <button type="button" className="btn btn-ghost min-h-11 px-3 text-sm" onClick={share} aria-label="Share">
            <Icon name="share" size={16} />
          </button>
        </section>
      )}

      <section className="card p-4">
        <h2 className="font-extrabold">Members</h2>
        <ul className="mt-3 flex flex-col gap-2">
          {g.members.map((m, i) => (
            <li key={i} className="sh-member flex min-h-[52px] items-center gap-3 rounded-2xl bg-surface-2/70 px-3 py-2" style={{ ["--i" as string]: i }}>
              <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-accent text-sm font-extrabold text-white">{m.name.slice(0, 1).toUpperCase()}</span>
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-bold">
                  {m.name}
                  {m.isYou && " (you)"}
                  {m.isOwner && <span className="ml-1.5 rounded-full bg-surface px-1.5 py-0.5 text-[10px] font-bold text-ink-2">Organiser</span>}
                </span>
                <span className="block text-xs text-ink-2">
                  {m.items} {m.items === 1 ? "item" : "items"} · {m.weightKg.toFixed(1)} kg
                </span>
              </span>
              {m.savingBdt > 0 && <span className="text-xs font-bold tabular-nums text-good">saves {formatBdt(m.savingBdt)}</span>}
            </li>
          ))}
        </ul>
      </section>

      {g.you?.orderId && (
        <Link href={`/orders/${g.you.orderId}?pay=1`} className="btn btn-primary min-h-12">
          Pay for your order {g.you.orderId}
        </Link>
      )}
      {g.you?.error && <p className="text-sm text-bad">Your order couldn&apos;t be placed: {g.you.error}</p>}

      {open && (
        <section className="card p-4">
          <h2 className="font-extrabold">{joined ? "Your items" : "Join with your cart"}</h2>
          <p className="mt-1 text-sm text-ink-2">
            {mine.length
              ? `${mine.length} ${store.name} ${mine.length === 1 ? "item" : "items"} in your cart (${formatBdt(mine.reduce((s, i) => s + i.unitBdt * i.qty, 0))}).`
              : `Add ${store.name} items to your cart, then come back to join.`}
            {joined && g.you && g.you.savingBdt > 0 ? ` You save ${formatBdt(g.you.savingBdt)} on shipping.` : ""}
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            <button type="button" className="btn btn-primary min-h-11" disabled={!!busy || !mine.length} onClick={() => act("join")}>
              {busy === "join" ? "Saving…" : joined ? "Update with my cart" : "Join group"}
            </button>
            {!mine.length && (
              <Link href={`/store/${g.market}`} className="btn btn-ghost min-h-11">
                Shop {store.name}
              </Link>
            )}
            {joined && !g.isOwner && (
              <button type="button" className="btn btn-ghost min-h-11" disabled={!!busy} onClick={() => act("leave")}>
                Leave
              </button>
            )}
          </div>
          {g.isOwner && (
            <div className="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
              <button type="button" className="btn btn-ghost min-h-11" disabled={!!busy} onClick={() => act("close")}>
                Close now & place orders
              </button>
              <button type="button" className="btn btn-ghost min-h-11 text-bad" disabled={!!busy} onClick={() => act("cancel")}>
                Cancel group
              </button>
            </div>
          )}
          <p className="mt-3 text-xs text-ink-2">
            When the group closes we place one order per member. Shipping is shared by weight, so everyone pays less. With only one member, the order goes ahead at the normal price.
          </p>
        </section>
      )}
      {g.demo && <p className="text-center text-xs text-ink-3">Demo group: stored in this browser, with a sample friend to show the shipping split.</p>}
    </div>
  );
}
