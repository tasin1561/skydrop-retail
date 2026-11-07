"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Icon } from "@/components/Icon";
import { api, apiEnabled } from "@/lib/apiClient";
import { POINTS, demoLedger, pointsValueBdt, type PointsEntry } from "@/lib/points";
import { useStore } from "@/state/store";
import { SignedOut } from "./SignedOut";
import { useT } from "@/i18n/client";
import type { MsgKey } from "@/i18n/core";

const TONE: Record<string, { icon: string; color: string; label: MsgKey }> = {
  earn: { icon: "plus", color: "var(--good)", label: "pv.earned" },
  bonus: { icon: "spark", color: "var(--good)", label: "pv.bonus" },
  refund: { icon: "refresh", color: "var(--good)", label: "pv.returned" },
  pending: { icon: "clock", color: "var(--warn)", label: "pv.pending" },
  redeem: { icon: "tag", color: "var(--accent)", label: "pv.used" },
  expire: { icon: "x", color: "var(--bad)", label: "pv.expired" },
  adjust: { icon: "settings", color: "var(--ink-2)", label: "pv.adjusted" },
};

export function PointsView() {
  const { user, orders, ready } = useStore();
  const { t, bdt, num, date, digits } = useT();
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
        <SignedOut next="/account/points" title={t("pv.signIn")} body={t("pv.signInB")} />
      </div>
    );

  const data = remote ?? demoLedger(orders, "1970-01-01T00:00:00.000Z", t);
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
        <p className="text-sm font-semibold text-white/85">{t("acc.points")}</p>
        <p className="mt-1 text-5xl font-black tracking-tight tabular-nums">{num(data.balance)}</p>
        <p className="mt-1 text-sm text-white/90">{t("pv.worth", { amount: bdt(pointsValueBdt(data.balance)) })}</p>
        <div className="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
          <span className="rounded-full bg-white/20 px-3 py-1">{data.pending} pending</span>
          <span className="rounded-full bg-white/20 px-3 py-1">{t("pv.next", { count: nextBlock, amount: bdt(POINTS.blockValueBdt) })}</span>
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
          ["receipt", t("pv.earn"), t("pv.earnT", { amount: bdt(POINTS.earnPerBdt) })],
          ["tag", t("pv.save"), t("pv.saveT", { count: POINTS.blockPoints, amount: bdt(POINTS.blockValueBdt), pct: POINTS.maxRedeemPct * 100 })],
          ["clock", t("pv.keep"), t("pv.keepT", { count: POINTS.expiryMonths })],
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
        <h2 className="px-3 pb-1 pt-3 text-base font-extrabold">{t("pv.history")}</h2>
        {data.history.length === 0 ? (
          <p className="px-3 pb-4 text-sm text-ink-2">{t("pv.none")}</p>
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
                      {t(tone.label)}
                      {h.at && !h.at.startsWith("1970") ? ` · ${date(h.at)}` : ""}
                    </p>
                  </div>
                  <span className="font-extrabold tabular-nums" style={{ color: tone.color }}>
                    {h.points > 0 ? "+" : ""}
                    {digits(h.points)}
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
