"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Icon } from "@/components/Icon";
import { SignedOut } from "@/components/account/SignedOut";
import { LoadingBlock } from "@/components/account/ui";
import { useFeature } from "@/components/features/FeaturesProvider";
import { apiEnabled } from "@/lib/apiClient";
import { money, type Wallet, type WalletEntry } from "@/lib/money";
import { formatBdt } from "@/lib/pricing";
import { useStore } from "@/state/store";
import { RedeemGiftCard } from "./RedeemGiftCard";
import "./money.css";

const REASON: Record<WalletEntry["reason"], { label: string; icon: string }> = {
  refund: { label: "Refund", icon: "refresh" },
  gift_card: { label: "Gift card", icon: "bag" },
  admin: { label: "Adjustment", icon: "settings" },
  promo: { label: "Promotion", icon: "spark" },
  order_payment: { label: "Paid for order", icon: "receipt" },
  expire: { label: "Expired", icon: "clock" },
};

const DEMO: Wallet = { balanceBdt: 0, expiringBdt: 0, expiringAt: null, history: [] };

export function WalletView({ redeem }: { redeem?: boolean }) {
  const { user, ready } = useStore();
  const on = useFeature("wallet");
  const giftCards = useFeature("gift_cards");
  const [w, setW] = useState<Wallet | null>(apiEnabled ? null : DEMO);
  const [failed, setFailed] = useState(false);

  const load = useCallback(() => {
    if (!apiEnabled) return;
    money
      .wallet()
      .then((x) => {
        setW(x);
        setFailed(false);
      })
      .catch(() => setFailed(true));
  }, []);

  useEffect(() => {
    if (user && on) load();
  }, [user, on, load]);

  if (ready && !user)
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-10 md:pb-12">
        <SignedOut next="/account/wallet" title="Sign in to see your wallet" body="Refunds and gift cards land in your Skydrop Wallet. Spend it at checkout." />
      </div>
    );

  if (!on)
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-10 text-center md:pb-12">
        <p className="text-sm text-ink-2">The Skydrop Wallet is not available right now.</p>
      </div>
    );

  return (
    <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
      <Link href="/account" className="mb-3 inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-accent">
        <Icon name="left" size={16} /> Account
      </Link>

      <section
        className="rise relative overflow-hidden rounded-[20px] p-5 text-white shadow-[var(--glow)]"
        style={{ background: "linear-gradient(135deg, var(--hero-a), var(--hero-b))" }}
        aria-labelledby="wallet-h"
      >
        <div aria-hidden className="absolute -right-10 -top-12 h-40 w-40 rounded-full bg-white/10" />
        <p id="wallet-h" className="relative flex items-center gap-2 text-sm font-semibold text-white/85">
          <Icon name="wallet" size={16} /> Skydrop Wallet
        </p>
        {w ? (
          <p key={w.balanceBdt} className="money-count relative mt-1 text-4xl font-extrabold tabular-nums tracking-tight">
            {formatBdt(w.balanceBdt)}
          </p>
        ) : (
          <div className="skeleton relative mt-2 h-10 w-40 rounded-xl opacity-40" />
        )}
        <p className="relative mt-1 text-xs text-white/80">Use it at checkout for part or all of an order. Never expires unless noted.</p>
        {w && w.expiringBdt > 0 && w.expiringAt && (
          <p className="relative mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
            <Icon name="clock" size={13} /> {formatBdt(w.expiringBdt)} expires on{" "}
            {new Date(w.expiringAt).toLocaleDateString("en-GB", { day: "numeric", month: "short" })}
          </p>
        )}
      </section>

      {giftCards && (
        <section className="card rise mt-4 p-4" style={{ ["--i" as string]: 1 }}>
          <RedeemGiftCard autoFocus={redeem} onRedeemed={() => load()} />
          <Link href="/gift-cards" className="mt-1 inline-flex min-h-11 items-center gap-1 text-sm font-bold text-accent">
            <Icon name="bag" size={15} /> Send a gift card
          </Link>
        </section>
      )}

      <section className="card rise mt-4 overflow-hidden" style={{ ["--i" as string]: 2 }} aria-labelledby="wh-h">
        <h2 id="wh-h" className="px-4 pb-2 pt-4 text-sm font-extrabold">
          History
        </h2>
        {failed ? (
          <p className="px-4 pb-4 text-sm text-ink-2">
            Could not load your wallet.{" "}
            <button type="button" onClick={load} className="min-h-11 font-bold text-accent">
              Try again
            </button>
          </p>
        ) : !w ? (
          <div className="px-4 pb-4">
            <LoadingBlock rows={3} />
          </div>
        ) : w.history.length === 0 ? (
          <p className="px-4 pb-5 text-sm text-ink-2">No wallet activity yet. Refunds you choose to take as store credit and redeemed gift cards show up here.</p>
        ) : (
          <ul className="divide-y divide-line/60">
            {w.history.map((h, i) => {
              const r = REASON[h.reason] ?? REASON.admin;
              const credit = h.type === "credit";
              return (
                <li key={h.id} className="money-in flex items-center gap-3 px-4 py-3" style={{ animationDelay: `${Math.min(i, 8) * 30}ms` }}>
                  <span className="chip-icon shrink-0" style={credit ? { color: "var(--good)" } : undefined}>
                    <Icon name={r.icon} size={17} />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-semibold">{h.note || r.label}</span>
                    <span className="block text-xs text-ink-3">
                      {new Date(h.at).toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" })}
                      {h.orderId ? ` · ${h.orderId}` : ""}
                      {h.expiresAt ? ` · expires ${new Date(h.expiresAt).toLocaleDateString("en-GB", { day: "numeric", month: "short" })}` : ""}
                    </span>
                  </span>
                  <span className={"shrink-0 text-sm font-extrabold tabular-nums " + (credit ? "text-good" : "")}>
                    {credit ? "+" : "−"}
                    {formatBdt(h.amountBdt)}
                  </span>
                </li>
              );
            })}
          </ul>
        )}
      </section>
    </div>
  );
}
