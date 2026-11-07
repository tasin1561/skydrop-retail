"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Icon } from "@/components/Icon";
import { useFeature } from "@/components/features/FeaturesProvider";
import { apiEnabled } from "@/lib/apiClient";
import { money, type PlusMembership, type PlusSavings } from "@/lib/money";
import { formatBdt } from "@/lib/pricing";
import { useStore } from "@/state/store";
import { SavingsTracker } from "./PlusLanding";
import "./money.css";

/** "PLUS" badge for the account header (renders nothing for non-members). */
export function PlusBadge() {
  const m = usePlus();
  if (!m?.membership || m.membership.status === "expired") return null;
  return (
    <span className="plus-badge" title={m.membership.plan?.name ?? "Skydrop Plus"}>
      <Icon name="star" size={11} /> PLUS
    </span>
  );
}

let plusCache: { key: string; p: Promise<{ membership: PlusMembership | null; savings: PlusSavings } | null> } | null = null;

function usePlus() {
  const { user } = useStore();
  const on = useFeature("membership");
  const [m, setM] = useState<{ membership: PlusMembership | null; savings: PlusSavings } | null>(null);
  useEffect(() => {
    if (!on || !apiEnabled || !user) return;
    const key = user.phone;
    if (!plusCache || plusCache.key !== key) plusCache = { key, p: money.membership().catch(() => null) };
    let live = true;
    plusCache.p.then((r) => live && setM(r));
    return () => {
      live = false;
    };
  }, [on, user]);
  return m;
}

/** Wallet balance + Plus status/savings card for the account dashboard. */
export function AccountMoney() {
  const { user } = useStore();
  const walletOn = useFeature("wallet");
  const plusOn = useFeature("membership");
  const giftOn = useFeature("gift_cards");
  const plus = usePlus();
  const [balance, setBalance] = useState<number | null>(null);

  useEffect(() => {
    if (!walletOn || !apiEnabled || !user) return;
    money
      .wallet()
      .then((w) => setBalance(w.balanceBdt))
      .catch(() => {});
  }, [walletOn, user]);

  if (!walletOn && !plusOn && !giftOn) return null;
  const member = plus?.membership && plus.membership.status !== "expired" ? plus.membership : null;

  return (
    <section aria-label="Wallet and Plus" className="mt-4 grid gap-3 sm:grid-cols-2">
      {walletOn && (
        <Link href="/account/wallet" className="card rise group flex items-center gap-3 p-4 transition-transform hover:-translate-y-0.5" style={{ ["--i" as string]: 4 }}>
          <span className="chip-icon transition group-hover:bg-accent group-hover:text-white">
            <Icon name="wallet" size={18} />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block text-xs font-semibold text-ink-2">Skydrop Wallet</span>
            <span className="block text-xl font-extrabold tabular-nums">{balance === null ? "—" : formatBdt(balance)}</span>
          </span>
          {giftOn && <span className="text-xs font-bold text-accent">Redeem a card</span>}
          <Icon name="right" size={18} className="text-ink-3" />
        </Link>
      )}
      {plusOn &&
        (member && plus ? (
          <Link href="/plus" className="block" style={{ ["--i" as string]: 5 }}>
            <SavingsTracker savings={plus.savings} compact />
          </Link>
        ) : (
          <Link href="/plus" className="plus-hero rise flex items-center gap-3 p-4" style={{ ["--i" as string]: 5 }}>
            <span className="relative z-10 min-w-0 flex-1">
              <span className="plus-badge">
                <Icon name="star" size={11} /> PLUS
              </span>
              <span className="mt-1 block text-sm font-bold">Free shipping on every order</span>
            </span>
            <Icon name="right" size={18} className="relative z-10" />
          </Link>
        ))}
    </section>
  );
}
