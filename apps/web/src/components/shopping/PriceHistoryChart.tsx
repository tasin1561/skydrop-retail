"use client";

import "./shopping.css";
import { useEffect, useId, useMemo, useRef, useState } from "react";
import { Icon } from "@/components/Icon";
import { fetchPriceHistory, type HistoryInput } from "@/lib/shopping/client";
import { PERIODS, type Period } from "@/lib/shopping/history";
import type { PriceHistory } from "@/lib/shopping/types";
import { useT } from "@/i18n/client";
import type { MsgKey } from "@/i18n/core";

const W = 600;
const H = 180;
const PAD_T = 14;
const PAD_B = 10;

const LABEL: Record<Period, MsgKey> = { 30: "ph.30", 90: "ph.90", 365: "ph.365" };
const IN_PERIOD: Record<Period, MsgKey> = { 30: "ph.in30", 90: "ph.in90", 365: "ph.in365" };
const VERDICT: Record<PriceHistory["positionLabel"], { text: MsgKey; tone: string }> = {
  lowest: { text: "ph.lowest", tone: "text-good" },
  low: { text: "ph.low", tone: "text-good" },
  typical: { text: "ph.typical", tone: "text-ink-2" },
  high: { text: "ph.high", tone: "text-warn" },
  steady: { text: "ph.steady", tone: "text-ink-2" },
};


/** Price history (BDT at today's rates) with period tabs, crosshair tooltip and a draw-in on view. */
export function PriceHistoryChart({ p }: { p: HistoryInput }) {
  const [days, setDays] = useState<Period>(30);
  const { t, bdt, inr, date } = useT();
  const formatBdt = bdt;
  const fmtDate = (iso: string, long = false) => date(iso + "T00:00:00Z", { day: "numeric", month: "short", ...(long ? { year: "numeric" } : {}), timeZone: "UTC" });
  const [cache, setCache] = useState<Partial<Record<Period, PriceHistory>>>({});
  const [failed, setFailed] = useState(false);
  const [hover, setHover] = useState<number | null>(null);
  const [inView, setInView] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const gid = useId().replace(/:/g, "");
  const data = cache[days];
  const loading = !data && !failed;

  useEffect(() => {
    if (cache[days]) return;
    let live = true;
    fetchPriceHistory(p, days)
      .then((h) => live && setCache((c) => ({ ...c, [days]: h })))
      .catch(() => live && setFailed(true));
    return () => {
      live = false;
    };
  }, [days, p, cache]);

  useEffect(() => {
    const el = ref.current;
    if (!el || inView) return;
    if (typeof IntersectionObserver === "undefined") {
      const id = requestAnimationFrame(() => setInView(true));
      return () => cancelAnimationFrame(id);
    }
    const io = new IntersectionObserver((e) => e.some((x) => x.isIntersecting) && setInView(true), { threshold: 0.35 });
    io.observe(el);
    return () => io.disconnect();
  }, [inView]);

  const shown = data ?? Object.values(cache)[0];
  const geo = useMemo(() => {
    if (!shown || !shown.points.length) return null;
    const pts = shown.points;
    const vals = pts.map((x) => x.bdt);
    const lo = Math.min(...vals);
    const hi = Math.max(...vals);
    const pad = Math.max(10, (hi - lo) * 0.12);
    const min = lo - pad;
    const max = hi + pad;
    const n = pts.length;
    const x = (i: number) => (n === 1 ? W : (i / (n - 1)) * W);
    const y = (v: number) => PAD_T + (1 - (v - min) / (max - min)) * (H - PAD_T - PAD_B);
    // Prices change in steps, so the line is a step path (honest about when the price moved).
    let line = `M0 ${y(vals[0]).toFixed(1)}`;
    for (let i = 1; i < n; i++) line += `H${x(i).toFixed(1)}V${y(vals[i]).toFixed(1)}`;
    if (n === 1) line += `H${W}`;
    const area = `${line}V${H}H0Z`;
    return { pts, x, y, line, area, n, avgY: y(shown.averageBdt), lowIdx: vals.lastIndexOf(lo) };
  }, [shown]);

  const idx = hover ?? (geo ? geo.n - 1 : 0);
  const pt = geo?.pts[idx];
  const pct = geo ? (geo.n === 1 ? 100 : (idx / (geo.n - 1)) * 100) : 0;
  const ypct = geo && pt ? (geo.y(pt.bdt) / H) * 100 : 0;

  function pick(clientX: number) {
    const el = ref.current;
    if (!el || !geo) return;
    const r = el.getBoundingClientRect();
    const f = Math.min(1, Math.max(0, (clientX - r.left) / r.width));
    setHover(Math.round(f * (geo.n - 1)));
  }

  function onKey(e: React.KeyboardEvent) {
    if (!geo) return;
    const step = e.shiftKey ? 7 : 1;
    const next = e.key === "ArrowLeft" ? idx - step : e.key === "ArrowRight" ? idx + step : e.key === "Home" ? 0 : e.key === "End" ? geo.n - 1 : null;
    if (next === null) return;
    e.preventDefault();
    setHover(Math.min(geo.n - 1, Math.max(0, next)));
  }

  const ti = PERIODS.indexOf(days);
  const verdict = shown ? VERDICT[shown.positionLabel] : null;
  const change = shown && shown.points.length > 1 ? shown.currentBdt - shown.points[0].bdt : 0;

  return (
    <section className="card p-4 sm:p-5" aria-labelledby={`${gid}-h`}>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 id={`${gid}-h`} className="flex items-center gap-3 text-lg font-extrabold tracking-tight">
          <span className="chip-icon">
            <Icon name="sort" size={18} />
          </span>
          {t("ph.title")}
        </h2>
        <div className="sh-tabs" role="tablist" aria-label={t("ph.period")}>
          <span className="sh-tabs__ind" aria-hidden style={{ width: `calc((100% - 8px) / ${PERIODS.length})`, transform: `translateX(${ti * 100}%)` }} />
          {PERIODS.map((d) => (
            <button
              key={d}
              type="button"
              role="tab"
              aria-selected={d === days}
              onClick={() => {
                setHover(null);
                setFailed(false);
                setDays(d);
              }}
            >
              {t(LABEL[d])}
            </button>
          ))}
        </div>
      </div>

      {shown && shown.badges.length > 0 && (
        <div className="mt-3 flex flex-wrap gap-2">
          {shown.badges.map((b) => (
            <span
              key={b.type + days}
              className={
                "sh-chip inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold " +
                (b.type === "lowest" ? "bg-[color-mix(in_srgb,var(--good)_14%,transparent)] text-good" : "bg-[var(--accent-soft)] text-accent")
              }
            >
              <Icon name={b.type === "lowest" ? "verified" : "tag"} size={14} stroke={2.2} /> {b.type === "lowest" ? t("ph.badgeLowest") : b.dropBdt ? t("ph.badgeDrop", { amount: bdt(b.dropBdt) }) : b.label}
            </span>
          ))}
        </div>
      )}

      {/* headline value: follows the crosshair */}
      <div className="mt-3 flex items-end justify-between gap-3" aria-live="polite">
        <div>
          <p className="text-2xl font-extrabold tracking-tight tabular-nums">{pt ? formatBdt(pt.bdt) : "…"}</p>
          <p className="text-xs text-ink-2">
            {pt ? (hover === null ? t("ph.today") : fmtDate(pt.date, true)) : t("ph.loading")}
            {pt && !pt.inStock && t("ph.oos")}
          </p>
        </div>
        {shown && hover === null && change !== 0 && (
          <p className={"text-sm font-bold tabular-nums " + (change < 0 ? "text-good" : "text-warn")}>
            {change < 0 ? "▼" : "▲"} {formatBdt(Math.abs(change))} {t(IN_PERIOD[days])}
          </p>
        )}
      </div>

      <div
        ref={ref}
        className={"sh-chart mt-3 " + (inView ? "sh-in " : "") + (loading ? "sh-loading" : "")}
        tabIndex={0}
        role="img"
        aria-label={
          shown
            ? t("ph.aria", { days, low: formatBdt(shown.lowestBdt), avg: formatBdt(shown.averageBdt), high: formatBdt(shown.highestBdt), now: formatBdt(shown.currentBdt) })
            : t("ph.ariaLoading")
        }
        onKeyDown={onKey}
        onPointerMove={(e) => pick(e.clientX)}
        onPointerDown={(e) => pick(e.clientX)}
        onPointerLeave={(e) => e.pointerType === "mouse" && setHover(null)}
        onBlur={() => setHover(null)}
      >
        {geo ? (
          <div className="sh-fade relative">
            <svg viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="none" height={H} aria-hidden>
              <defs>
                <linearGradient id={`${gid}-g`} x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0" className="sh-stop-a" />
                  <stop offset="1" className="sh-stop-b" />
                </linearGradient>
              </defs>
              {[0.25, 0.5, 0.75].map((f) => (
                <line key={f} x1="0" x2={W} y1={H * f} y2={H * f} stroke="var(--line)" strokeWidth="1" vectorEffect="non-scaling-stroke" />
              ))}
              <line x1="0" x2={W} y1={geo.avgY} y2={geo.avgY} stroke="var(--ink-3)" strokeDasharray="4 5" strokeWidth="1" vectorEffect="non-scaling-stroke" />
              <path key={`a${days}`} d={geo.area} fill={`url(#${gid}-g)`} />
              <path key={`l${days}`} d={geo.line} fill="none" stroke="var(--accent)" strokeWidth="2.25" strokeLinejoin="round" />
            </svg>
            <div key={`w${days}${inView}`} className="sh-wipe" aria-hidden />
            {/* lowest marker */}
            <div className="sh-x" style={{ transform: `translateX(${geo.n === 1 ? 100 : (geo.lowIdx / (geo.n - 1)) * 100}%)` }} aria-hidden>
              <div className="sh-y" style={{ transform: `translateY(${(geo.y(geo.pts[geo.lowIdx].bdt) / H) * 100}%)` }}>
                <span className="absolute left-0 top-0 -ml-1 -mt-1 h-2 w-2 rounded-full bg-good" />
              </div>
            </div>
          </div>
        ) : failed ? (
          <div className="grid h-[180px] place-items-center rounded-xl bg-surface-2 text-center text-sm text-ink-2">
            <div>
              <p>{t("ph.unavailable")}</p>
              <button type="button" className="mt-2 min-h-11 rounded-full px-4 font-bold text-accent" onClick={() => setFailed(false)}>
                {t("common.tryAgain")}
              </button>
            </div>
          </div>
        ) : (
          <div className="skeleton h-[180px] rounded-xl" aria-hidden />
        )}

        {geo && pt && (
          <div className="sh-x" style={{ transform: `translateX(${pct}%)`, opacity: hover === null ? 0.9 : 1 }} aria-hidden>
            {hover !== null && <span className="sh-x__line" />}
            <div className="sh-y" style={{ transform: `translateY(${ypct}%)` }}>
              <span className={"sh-dot " + (hover === null ? "sh-now" : "")} />
              {hover !== null && (
                <span key={idx} className="sh-tip" style={{ ["--tx" as string]: pct < 18 ? "0%" : pct > 82 ? "-100%" : "-50%", transform: `translateX(var(--tx))` }}>
                  <b className="tabular-nums">{formatBdt(pt.bdt)}</b>
                  <span className="opacity-70"> · {fmtDate(pt.date)}</span>
                  <span className="block text-[11px] opacity-70">{t("ph.onStore", { amount: inr(pt.inr) })}</span>
                </span>
              )}
            </div>
          </div>
        )}
      </div>

      {geo && (
        <div className="mt-1 flex justify-between text-[11px] text-ink-3" aria-hidden>
          <span>{fmtDate(geo.pts[0].date)}</span>
          <span>{t("ph.avg", { amount: formatBdt(shown!.averageBdt) })}</span>
          <span>{t("ph.today")}</span>
        </div>
      )}

      {shown && (
        <div className="mt-4 rounded-2xl bg-surface-2 p-3">
          <dl className="grid grid-cols-3 gap-2 text-center">
            {[
              [t("ph.statLowest"), shown.lowestBdt, "text-good"],
              [t("ph.statAverage"), shown.averageBdt, "text-ink"],
              [t("ph.statHighest"), shown.highestBdt, "text-ink"],
            ].map(([k, v, tone]) => (
              <div key={k as string}>
                <dt className="text-[11px] font-semibold uppercase tracking-wide text-ink-2">{k}</dt>
                <dd className={"text-sm font-extrabold tabular-nums " + tone}>{formatBdt(v as number)}</dd>
              </div>
            ))}
          </dl>
          {shown.positionLabel !== "steady" && (
            <div className="mt-3">
              <div className="sh-meter" role="meter" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(shown.position * 100)} aria-label={t("ph.where")}>
                <span className="sh-meter__pos" style={{ transform: `translateX(${shown.position * 100}%)` }} />
              </div>
            </div>
          )}
          {verdict && <p className={"mt-2.5 text-center text-xs font-bold " + verdict.tone}>{t(verdict.text)}</p>}
        </div>
      )}
      <p className="mt-3 text-[11px] leading-relaxed text-ink-3">
        {t("ph.note")}
        {shown?.demo ? t("ph.demo") : ""}
      </p>
    </section>
  );
}
