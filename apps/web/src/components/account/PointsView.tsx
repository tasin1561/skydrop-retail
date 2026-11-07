"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Icon } from "@/components/Icon";
import { api, apiEnabled } from "@/lib/apiClient";
import { POINTS, demoLedger, pointsValueBdt, type PointsEntry } from "@/lib/points";
import { formatBdt } from "@/lib/pricing";
import { useStore } from "@/state/store";
import { SignedOut } from "./SignedOut";

const TONE: Record<string, { icon: string; color: string; label: string }> = {
  earn: { icon: "plus", color: "var(--good)", label: "Earned" },
  bonus: { icon: "spark", color: "var(--good)", label: "Bonus" },
  refund: { icon: "refresh", color: "var(--good)", label: "Returned" },
  pending: { icon: "clock", color: "var(--warn)", label: "Pending" },
  redeem: { icon: "tag", color: "var(--accent)", label: "Used" },
  expire: { icon: "x", color: "var(--bad)", label: "Expired" },
  adjust: { icon: "settings", color: "var(--ink-2)", label: "Adjusted" },
};

export function PointsView() {
  const { user, orders, ready } = useStore();
  const [remote, setRemote] = useState<{ balance: number; pending: number; history: PointsEntry[] } | null>(null);

  useEffect(() => {
    if (!apiEnabled || !user) return;
    api
      .points()
      .then((p) => setRemote({ balance: p.balance, pending: p.pending, history: p.history as PointsEntry[] }))
      .catch(() => {});
  }, [user]);

  if (ready && !user)
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-10 md:pb-12">
        <SignedOut next="/account/points" title="Sign in to see your points" body="Earn Skydrop Points on every order and save at checkout." />
      </div>
    );

  const data = remote ?? demoLedger(orders, "1970-01-01T00:00:00.000Z");
  const nextBlock = POINTS.blockPoints - (data.balance % POINTS.blockPoints);

  return (
    <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
      <Link href="/account" className="inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-ink-2 hover:text-accent">
        <Icon name="left" size={16} /> Account
      </Link>

      <section
        className="rise relative mt-2 overflow-hidden rounded-[24px] p-6 text-white shadow-[var(--glow)]"
        style={{ background: "linear-gradient(135deg, var(--hero-a), var(--hero-b))" }}
      >
        <span aria-hidden className="float absolute -right-6 -top-6 h-32 w-32 rounded-full bg-white/15" />
        <p className="text-sm font-semibold text-white/85">Skydrop Points</p>
        <p className="mt-1 text-5xl font-black tracking-tight tabular-nums">{data.balance.toLocaleString("en-IN")}</p>
        <p className="mt-1 text-sm text-white/90">Worth {formatBdt(pointsValueBdt(data.balance))} off your next order</p>
        <div className="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
          <span className="rounded-full bg-white/20 px-3 py-1">{data.pending} pending</span>
          <span className="rounded-full bg-white/20 px-3 py-1">{nextBlock} more to the next {formatBdt(POINTS.blockValueBdt)}</span>
        </div>
        <div className="mt-4 h-2 overflow-hidden rounded-full bg-white/25" aria-hidden>
          <div
            className="h-full origin-left rounded-full bg-white transition-transform duration-700"
            style={{ transform: `scaleX(${(data.balance % POINTS.blockPoints) / POINTS.blockPoints})` }}
          />
        </div>
      </section>

      <section className="mt-4 grid gap-3 sm:grid-cols-3">
        {[
          ["receipt", "Earn", `1 point for every ${formatBdt(POINTS.earnPerBdt)} of items, added when your order is delivered.`],
          ["tag", "Save", `${POINTS.blockPoints} points = ${formatBdt(POINTS.blockValueBdt)} off, up to ${POINTS.maxRedeemPct * 100}% of your items.`],
          ["clock", "Keep", `Points last ${POINTS.expiryMonths} months. Cancelled orders return the points you used.`],
        ].map(([icon, title, text], i) => (
          <div key={title} className="card rise flex gap-3 p-4" style={{ ["--i" as string]: i }}>
            <span className="chip-icon shrink-0">
              <Icon name={icon} size={18} />
            </span>
            <div>
              <p className="text-sm font-bold">{title}</p>
              <p className="text-xs leading-relaxed text-ink-2">{text}</p>
            </div>
          </div>
        ))}
      </section>

      <section className="card mt-4 p-2">
        <h2 className="px-3 pb-1 pt-3 text-base font-extrabold">History</h2>
        {data.history.length === 0 ? (
          <p className="px-3 pb-4 text-sm text-ink-2">No points yet. Your first order earns points once it is delivered.</p>
        ) : (
          <ul className="divide-y divide-line/60">
            {data.history.map((h, i) => {
              const tone = TONE[h.type] ?? TONE.adjust;
              return (
                <li key={i} className="flex items-center gap-3 px-3 py-3">
                  <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl text-white" style={{ background: tone.color }}>
                    <Icon name={tone.icon} size={16} stroke={2.2} />
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold">{h.note}</p>
                    <p className="text-xs text-ink-3">
                      {tone.label}
                      {h.at && !h.at.startsWith("1970") ? ` · ${new Date(h.at).toLocaleDateString("en-GB", { day: "numeric", month: "short" })}` : ""}
                    </p>
                  </div>
                  <span className="font-extrabold tabular-nums" style={{ color: tone.color }}>
                    {h.points > 0 ? "+" : ""}
                    {h.points}
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
