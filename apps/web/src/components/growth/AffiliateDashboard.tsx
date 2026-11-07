"use client";

import "./growth.css";
import Link from "next/link";
import { useMemo, useState } from "react";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { CopyButton } from "@/components/share/CopyButton";
import { ShareButton } from "@/components/share/ShareButton";
import { LoadingBlock, PageHead, Spinner, useShake } from "@/components/account/ui";
import { SignedOut } from "@/components/account/SignedOut";
import { checkPhone, toE164 } from "@/components/account/phone";
import { ApiError } from "@/lib/apiClient";
import { sitePath, type AffiliateMe } from "@/lib/growth";
import { useAffiliate } from "./useGrowth";
import { useFeature } from "@/components/features/FeaturesProvider";
import { NotAvailable } from "./NotAvailable";
import { useT } from "@/i18n/client";

type Busy = "idle" | "busy" | "done";
const pad = (n: number) => String(n).padStart(2, "0");
const STATUS_TONE = { pending: "warn", approved: undefined, reversed: "bad", paid: "good", requested: "warn", rejected: "muted" } as const;
const METHODS = [
  { id: "bkash", label: "bKash" },
  { id: "nagad", label: "Nagad" },
] as const;

export function AffiliateDashboard() {
  const tr = useT();
  const { t } = tr;
  const aff = useAffiliate();
  const enabled = useFeature("affiliate");

  if (!enabled)
    return (
      <Shell>
        <NotAvailable title={t("af2.program")} />
      </Shell>
    );
  if (!aff.ready)
    return (
      <Shell>
        <LoadingBlock rows={4} />
      </Shell>
    );
  if (!aff.user)
    return (
      <Shell>
        <SignedOut next="/account/affiliate" title={t("af2.signIn")} body={t("af2.signInB")} />
      </Shell>
    );
  if (aff.error)
    return (
      <Shell>
        <div className="card p-6 text-center">
          <p className="font-bold">{aff.error}</p>
          <button type="button" className="btn btn-primary mt-4" onClick={aff.reload}>
            <Icon name="refresh" size={16} /> {t("common.tryAgain")}
          </button>
        </div>
      </Shell>
    );
  if (!aff.data)
    return (
      <Shell>
        <LoadingBlock rows={4} />
      </Shell>
    );

  const a = aff.data.affiliate;
  return (
    <Shell sub={a ? t("af2.code", { code: a.code }) : t("af2.earnEvery")}>
      {!aff.live && (
        <p className="mb-4 inline-flex items-center gap-2 rounded-xl bg-surface-2 px-3 py-2 text-xs font-semibold text-ink-2">
          <Icon name="info" size={15} className="text-accent" /> {t("af2.demo")}
        </p>
      )}
      {!a ? (
        <ApplyForm onApply={aff.apply} program={aff.data.program} defaultNumber={aff.user.phone} />
      ) : a.status === "approved" ? (
        <Approved me={aff.data} onUpdate={aff.update} onPayout={aff.requestPayout} />
      ) : (
        <Pending status={a.status} code={a.code} onDemoApprove={aff.live ? undefined : aff.demoApprove} />
      )}
    </Shell>
  );
}

function Shell({ children, sub }: { children: React.ReactNode; sub?: string }) {
  const tr = useT();
  const { t } = tr;
  return (
    <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
      <PageHead title={t("af2.title")} sub={sub} icon="tag" back={{ href: "/account", label: t("acc.account") }} />
      {children}
    </div>
  );
}

/* ---------------- Apply ---------------- */

function ApplyForm({
  onApply,
  program,
  defaultNumber,
}: {
  onApply: (b: { code?: string; payoutMethod: "bkash" | "nagad"; payoutNumber: string; about?: string }) => Promise<void>;
  program: AffiliateMe["program"];
  defaultNumber: string;
}) {
  const tr = useT();
  const { t, bdt } = tr;
  const [code, setCode] = useState("");
  const [method, setMethod] = useState<"bkash" | "nagad">("bkash");
  const [digits, setDigits] = useState(defaultNumber.replace(/^\+?880/, ""));
  const [about, setAbout] = useState("");
  const [state, setState] = useState<Busy>("idle");
  const [error, setError] = useState("");
  const shake = useShake();
  const codeOk = code === "" || /^[A-Z0-9]{4,16}$/.test(code);
  const phoneOk = checkPhone(digits).valid;

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!codeOk || !phoneOk) {
      shake.shake();
      return;
    }
    setState("busy");
    setError("");
    try {
      await onApply({ code: code || undefined, payoutMethod: method, payoutNumber: toE164(digits), about: about || undefined });
      setState("done");
    } catch (err) {
      setState("idle");
      shake.shake();
      setError(err instanceof ApiError ? err.message : t("af2.applyFail"));
    }
  }

  return (
    <form className="card rise p-5" onSubmit={submit} noValidate>
      <h2 className="text-lg font-extrabold tracking-tight">{t("af2.apply")}</h2>
      <p className="mt-1 text-sm text-ink-2">
        {t("af2.applyB", { rate: program.defaultRate, max: Math.max(program.defaultRate, ...Object.values(program.rates)), min: bdt(program.minPayoutBdt) })}
      </p>

      <div className={"mt-5 space-y-3 " + shake.shakeClass} onAnimationEnd={shake.onAnimationEnd}>
        <div className={"field " + (code && codeOk ? "is-valid" : !codeOk ? "is-invalid" : "")}>
          <input id="aff-code" placeholder=" " value={code} maxLength={16} autoCapitalize="characters" onChange={(e) => setCode(e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ""))} aria-describedby="aff-code-help" />
          <label htmlFor="aff-code">{t("af2.yourCode")}</label>
          <span className="field-icon">
            <Icon name="tag" size={20} />
          </span>
        </div>
        <p id="aff-code-help" className="ac-help" data-tone={!codeOk ? "bad" : code ? "good" : undefined}>
          {!codeOk ? t("af2.codeRule") : code ? t("af2.endIn", { code }) : t("af2.autoCode")}
          <span className="ml-auto tabular-nums text-ink-3">{code.length}/16</span>
        </p>

        <div role="radiogroup" aria-label={t("af2.payoutMethod")} className="ac-tabs" style={{ ["--n" as string]: 2 }}>
          <span aria-hidden className="ac-tab-pill" style={{ ["--idx" as string]: method === "bkash" ? 0 : 1 }} />
          {METHODS.map((m) => (
            <button key={m.id} type="button" role="radio" aria-checked={method === m.id} className="ac-tab min-h-11" onClick={() => setMethod(m.id)}>
              <Icon name="wallet" size={16} /> {m.label}
            </button>
          ))}
        </div>

        <div className={"field " + (phoneOk ? "is-valid" : "is-invalid")}>
          <input id="aff-number" inputMode="numeric" placeholder=" " value={digits} maxLength={11} onChange={(e) => setDigits(e.target.value.replace(/\D/g, ""))} />
          <label htmlFor="aff-number">{t("af2.number", { method: method === "bkash" ? "bKash" : "Nagad" })}</label>
          <span className="field-icon">
            <Icon name="phone" size={20} />
          </span>
        </div>
        {!phoneOk && (
          <p className="ac-help" data-tone="bad">
            {t("af2.need11")}
          </p>
        )}

        <div className="field">
          <textarea id="aff-about" placeholder=" " value={about} maxLength={500} onChange={(e) => setAbout(e.target.value)} />
          <label htmlFor="aff-about">{t("af2.where")}</label>
          <span className="field-icon">
            <Icon name="globe" size={20} />
          </span>
        </div>
      </div>

      <button type="submit" className="btn btn-primary mt-4 w-full" disabled={state !== "idle"} aria-busy={state === "busy"}>
        {state === "busy" ? (
          <>
            <Spinner /> {t("af2.sending")}
          </>
        ) : state === "done" ? (
          <>
            <Icon name="check" size={18} stroke={2.6} /> {t("af2.sent")}
          </>
        ) : (
          <>
            {t("af2.applyNow")} <Icon name="arrow" size={18} className="arrow" />
          </>
        )}
      </button>
      <p role="alert" className="mt-2 min-h-5 text-center text-sm font-semibold text-bad">
        {error}
      </p>
    </form>
  );
}

/* ---------------- Pending / suspended ---------------- */

function Pending({ status, code, onDemoApprove }: { status: "pending" | "suspended"; code: string; onDemoApprove?: () => void }) {
  const tr = useT();
  const { t } = tr;
  if (status === "suspended")
    return (
      <div className="card rise p-5">
        <span className="ac-chip" data-tone="bad">
          {t("af2.suspended")}
        </span>
        <h2 className="mt-2 text-lg font-extrabold">{t("af2.paused")}</h2>
        <p className="mt-1 text-sm text-ink-2">{t("af2.pausedB")}</p>
      </div>
    );
  const steps = [
    { label: t("af2.received"), sub: t("af2.reserved", { code }), icon: "check", state: "done" },
    { label: t("af2.review"), sub: t("af2.day"), icon: "shield", state: "current" },
    { label: t("af2.start"), sub: t("af2.startB"), icon: "spark", state: "next" },
  ];
  return (
    <div className="card rise p-5">
      <span className="ac-chip" data-tone="warn">
        {t("af2.underReview")}
      </span>
      <h2 className="mt-2 text-lg font-extrabold">{t("af2.thanks")}</h2>
      <ol className="gr-steps mt-4">
        {steps.map((s) => (
          <li key={s.label} className="gr-step" data-state={s.state}>
            <span className="relative">
              <span className={"chip-icon h-10 w-10 " + (s.state !== "next" ? "is-active" : "")} style={s.state === "done" ? { background: "var(--good)", color: "#fff" } : undefined}>
                <Icon name={s.icon} size={18} />
              </span>
              <span className="gr-ring" aria-hidden />
            </span>
            <span>
              <span className="block text-sm font-bold">
                {s.label}
                <span className="sr-only">{s.state === "done" ? t("lg.done") : s.state === "current" ? t("af2.inProgress") : ""}</span>
              </span>
              <span className="block text-xs text-ink-2">{s.sub}</span>
            </span>
          </li>
        ))}
      </ol>
      {onDemoApprove && (
        <button type="button" className="btn btn-ghost mt-5" onClick={onDemoApprove}>
          <Icon name="bolt" size={16} /> {t("af2.demoApprove")}
        </button>
      )}
    </div>
  );
}

/* ---------------- Approved: stats, link builder, payouts ---------------- */

function Approved({
  me,
  onUpdate,
  onPayout,
}: {
  me: AffiliateMe;
  onUpdate: (b: { code?: string; payoutMethod?: "bkash" | "nagad"; payoutNumber?: string }) => Promise<void>;
  onPayout: () => Promise<void>;
}) {
  const tr = useT();
  const { t, bdt, digits } = tr;
  const fmtDate = (iso?: string | null) => (iso ? tr.date(iso) : "");
  const a = me.affiliate!;
  const s = me.stats!;
  const p = me.program;
  const homeLink = useMemo(() => (typeof window === "undefined" ? a.link : `${window.location.origin}/?ref=${a.code}`), [a.code, a.link]);
  const total = s.pendingBdt + s.approvedBdt + s.paidBdt + s.requestedBdt;
  const segs = [
    { label: t("af2.pending"), value: s.pendingBdt, color: "var(--warn, #f59e0b)" },
    { label: t("af2.approved"), value: s.approvedBdt, color: "var(--accent)" },
    { label: t("af2.paid"), value: s.paidBdt, color: "var(--good)" },
  ];
  const stats = [
    { label: t("af2.clicks"), value: digits(pad(s.clicks30d)), icon: "bolt" },
    { label: t("af2.orders"), value: digits(pad(s.orders)), icon: "receipt" },
    { label: t("af2.conversion"), value: `${digits(s.conversion)}%`, icon: "spark" },
    { label: t("af2.paidYou"), value: bdt(s.paidBdt), icon: "wallet" },
  ];

  return (
    <div className="space-y-4">
      {/* code + main link */}
      <section className="rise relative overflow-hidden rounded-[12px] p-5 text-white shadow-[var(--glow)]" style={{ background: "linear-gradient(135deg, var(--hero-a) 0%, var(--hero-a) 55%, color-mix(in srgb, var(--hero-a) 72%, var(--hero-b)) 100%)" }}>
        <div aria-hidden className="absolute -right-10 -top-12 h-40 w-40 rounded-full bg-white/10" />
        <div className="relative">
          <div className="flex flex-wrap items-center gap-2">
            <span className="rounded-full bg-white/20 px-3 py-1 text-xs font-bold tracking-wide backdrop-blur-sm">{t("af2.active")}</span>
            <span className="text-sm text-white/85">{a.rate !== null ? t("af2.rate", { rate: a.rate }) : t("af2.std", { rate: p.defaultRate })}</span>
          </div>
          <p className="mt-3 font-mono text-3xl font-extrabold tracking-widest">{a.code}</p>
          <p className="mt-1 truncate text-sm text-white/85">{homeLink.replace(/^https?:\/\//, "")}</p>
          <div className="mt-4 flex flex-wrap gap-2">
            <CopyButton text={homeLink} label={t("common.copyLink")} className="btn min-h-11 bg-white px-4 text-sm font-bold text-slate-900" />
            <ShareButton variant="ghost" label={t("sh.share")} path="/" title="Skydrop Retail" className="!border-white/40 !bg-white/10 !text-white" qr />
          </div>
        </div>
      </section>

      <section aria-label={t("af2.stats")} className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {stats.map((x, i) => (
          <div key={x.label} className="card rise flex flex-col gap-2 p-3 sm:p-4" style={{ ["--i" as string]: i + 1 }}>
            <span className="chip-icon">
              <Icon name={x.icon} size={18} />
            </span>
            <span className="text-xl font-extrabold tabular-nums tracking-tight">{x.value}</span>
            <span className="text-xs font-semibold text-ink-2">{x.label}</span>
          </div>
        ))}
      </section>

      <section className="card rise p-4" style={{ ["--i" as string]: 5 }} aria-labelledby="earn-h">
        <div className="flex items-baseline justify-between gap-2">
          <h2 id="earn-h" className="text-sm font-extrabold">
            {t("af2.earnings")}
</h2>
          <span className="text-sm font-bold tabular-nums">{bdt(total)}</span>
        </div>
        <div className="gr-earn mt-3" aria-hidden>
          {segs.map((g, i) => (
            <span key={g.label} style={{ ["--w" as string]: total ? g.value : i === 0 ? 1 : 0, ["--i" as string]: i, background: total ? g.color : "transparent" }} />
          ))}
        </div>
        <ul className="mt-3 grid grid-cols-3 gap-2 text-xs">
          {segs.map((g) => (
            <li key={g.label} className="flex items-center gap-1.5">
              <span className="gr-dot" style={{ background: g.color }} />
              <span className="text-ink-2">{g.label}</span>
              <b className="ml-auto tabular-nums">{bdt(g.value)}</b>
            </li>
          ))}
        </ul>
        <p className="mt-2 text-xs text-ink-3">
          {t("af2.hold", { days: p.holdDays })}
        </p>
      </section>

      <LinkBuilder code={a.code} />

      <PayoutCard me={me} onUpdate={onUpdate} onPayout={onPayout} />

      <section className="card rise overflow-hidden" aria-labelledby="com-h" style={{ ["--i" as string]: 8 }}>
        <h2 id="com-h" className="px-4 pt-4 text-sm font-extrabold">
          {t("af2.latest")}
</h2>
        {me.commissions?.length ? (
          <ul className="mt-2">
            {me.commissions.map((c) => (
              <li key={c.id} className="ac-row">
                <span className="chip-icon shrink-0">
                  <Icon name="receipt" size={17} />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block font-mono text-sm font-semibold">{c.order}</span>
                  <span className="block text-xs text-ink-2">
                    {t("af2.line", { date: fmtDate(c.at), rate: c.rate, base: bdt(c.baseBdt) })}
{c.status === "pending" && (c.approveAfter ? t("af2.approves", { date: fmtDate(c.approveAfter) }) : t("af2.awaiting"))}
                  </span>
                </span>
                <span className="text-right">
                  <span className={"block text-sm font-bold tabular-nums " + (c.status === "reversed" ? "text-ink-3 line-through" : "")}>{bdt(c.amountBdt)}</span>
                  <span className="ac-chip mt-0.5 capitalize" data-tone={STATUS_TONE[c.status]}>
                    {c.status}
                  </span>
                </span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="px-4 pb-4 pt-2 text-sm text-ink-2">{t("af2.noOrders")}</p>
        )}
      </section>

      <EditCode code={a.code} editable={a.codeEditable} onUpdate={onUpdate} />
    </div>
  );
}

function LinkBuilder({ code }: { code: string }) {
  const tr = useT();
  const { t } = tr;
  const [input, setInput] = useState("");
  const origin = typeof window === "undefined" ? "" : window.location.origin;
  const path = input ? sitePath(input, origin) : null;
  const link = path ? `${origin}${path}${path.includes("?") ? "&" : "?"}ref=${code}` : null;
  const invalid = input.trim().length > 3 && !path;

  return (
    <section className="card rise p-4" aria-labelledby="lb-h" style={{ ["--i" as string]: 6 }}>
      <h2 id="lb-h" className="text-sm font-extrabold">
        {t("af2.builder")}
      </h2>
      <p className="text-xs text-ink-2">{t("af2.builderB")}</p>
      <div className={"field mt-3 " + (link ? "is-valid" : invalid ? "is-invalid" : "")}>
        <input id="lb-url" placeholder=" " value={input} onChange={(e) => setInput(e.target.value)} inputMode="url" autoComplete="off" aria-describedby="lb-help" />
        <label htmlFor="lb-url">{t("rf.link")}</label>
        <span className="field-icon">
          <Icon name="globe" size={20} />
        </span>
      </div>
      <p id="lb-help" className="ac-help" data-tone={invalid ? "bad" : link ? "good" : undefined}>
        {invalid ? t("af2.notOurs") : link ? t("af2.ready") : t("af2.eg", { url: `${origin.replace(/^https?:\/\//, "")}/p/B0EXAMPLE` })}
      </p>
      {link && (
        <div key={link} className="sh-result sh-link mt-2">
          <Icon name="arrow" size={16} className="gr-arrow-in shrink-0 text-accent" />
          <span className="min-w-0 flex-1 truncate text-sm font-medium" title={link}>
            {link.replace(/^https?:\/\//, "")}
          </span>
          <CopyButton text={link} />
        </div>
      )}
      <div className="mt-3 flex flex-wrap gap-2 text-xs">
        {[
          [t("nav.home"), "/"],
          [t("nav.deals"), "/deals"],
          ["Mega Store", "/store/amazon"],
        ].map(([label, p]) => (
          <button key={p} type="button" className="ac-chip min-h-11 px-3" onClick={() => setInput(origin + p)}>
            + {label}
          </button>
        ))}
      </div>
    </section>
  );
}

function PayoutCard({
  me,
  onUpdate,
  onPayout,
}: {
  me: AffiliateMe;
  onUpdate: (b: { payoutMethod?: "bkash" | "nagad"; payoutNumber?: string }) => Promise<void>;
  onPayout: () => Promise<void>;
}) {
  const tr = useT();
  const { t, bdt } = tr;
  const fmtDate = (iso?: string | null) => (iso ? tr.date(iso) : "");
  const toast = useToast();
  const a = me.affiliate!;
  const s = me.stats!;
  const min = me.program.minPayoutBdt;
  const [state, setState] = useState<Busy>("idle");
  const [error, setError] = useState("");
  const [editing, setEditing] = useState(false);
  const [digits, setDigits] = useState((a.payoutNumber ?? "").replace(/^\+?880/, ""));
  const [method, setMethod] = useState<"bkash" | "nagad">(a.payoutMethod ?? "bkash");
  const ratio = Math.min(1, s.availableBdt / Math.max(1, min));
  const canRequest = s.availableBdt >= min && s.requestedBdt === 0;

  async function request() {
    setState("busy");
    setError("");
    try {
      await onPayout();
      setState("done");
      toast({ kind: "success", title: t("af2.payoutReq"), body: t("af2.payoutReqB", { method: method === "bkash" ? "bKash" : "Nagad" }) });
    } catch (e) {
      setState("idle");
      setError(e instanceof ApiError ? e.message : t("af2.payoutFail"));
    }
  }

  async function save() {
    if (!checkPhone(digits).valid) {
      setError(t("af2.need11"));
      return;
    }
    try {
      await onUpdate({ payoutMethod: method, payoutNumber: toE164(digits) });
      setEditing(false);
      setError("");
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("af2.saveFail"));
    }
  }

  return (
    <section className="card rise p-4" aria-labelledby="pay-h" style={{ ["--i" as string]: 7 }}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 id="pay-h" className="text-sm font-extrabold">
            {t("af2.payout")}
</h2>
          <p className="text-2xl font-extrabold tabular-nums">{bdt(s.availableBdt)}</p>
          <p className="text-xs text-ink-2">{t("af2.available")}{s.requestedBdt ? t("af2.onWay", { amount: bdt(s.requestedBdt) }) : ""}</p>
        </div>
        <span className="chip-icon is-active">
          <Icon name="wallet" size={18} />
        </span>
      </div>
      <div className="ac-bar mt-3" data-tone={ratio >= 1 ? "good" : undefined} role="progressbar" aria-valuemin={0} aria-valuemax={min} aria-valuenow={Math.min(min, s.availableBdt)} aria-label={t("af2.progress")}>
        <span style={{ ["--p" as string]: ratio }} />
      </div>
      <p className="mt-1 text-xs text-ink-2">{ratio >= 1 ? t("af2.canRequest") : t("af2.moreToMin", { amount: bdt(min - s.availableBdt), min: bdt(min) })}</p>

      {editing ? (
        <div className="fade-in mt-3 space-y-2">
          <div role="radiogroup" aria-label={t("af2.payoutMethod")} className="ac-tabs" style={{ ["--n" as string]: 2 }}>
            <span aria-hidden className="ac-tab-pill" style={{ ["--idx" as string]: method === "bkash" ? 0 : 1 }} />
            {METHODS.map((m) => (
              <button key={m.id} type="button" role="radio" aria-checked={method === m.id} className="ac-tab min-h-11" onClick={() => setMethod(m.id)}>
                {m.label}
              </button>
            ))}
          </div>
          <div className="field">
            <input id="pay-num" inputMode="numeric" placeholder=" " value={digits} maxLength={11} onChange={(e) => setDigits(e.target.value.replace(/\D/g, ""))} />
            <label htmlFor="pay-num">{t("af2.wallet")}</label>
            <span className="field-icon">
              <Icon name="phone" size={20} />
            </span>
          </div>
          <div className="flex gap-2">
            <button type="button" className="btn btn-primary flex-1" onClick={save}>
{t("common.save")}
</button>
            <button type="button" className="btn btn-ghost" onClick={() => setEditing(false)}>
{t("common.cancel")}
</button>
          </div>
        </div>
      ) : (
        <div className="mt-3 flex items-center gap-2 rounded-xl bg-surface-2 px-3 py-2 text-sm">
          <Icon name="phone" size={16} className="text-ink-3" />
          <span className="flex-1">
            {a.payoutMethod === "nagad" ? "Nagad" : "bKash"} <span className="tabular-nums">{a.payoutNumber ?? t("af2.notSet")}</span>
          </span>
          <button type="button" className="min-h-11 px-2 text-sm font-semibold text-accent" onClick={() => setEditing(true)}>
{t("af2.change")}
</button>
        </div>
      )}

      <button type="button" className="btn btn-primary mt-3 w-full" onClick={request} disabled={!canRequest || state !== "idle"} aria-busy={state === "busy"}>
        {state === "busy" ? (
          <>
            <Spinner /> {t("af2.requesting")}
          </>
        ) : state === "done" || s.requestedBdt > 0 ? (
          <>
            <Icon name="check" size={18} stroke={2.6} /> {t("af2.payoutReq")}
          </>
        ) : (
          <>
            {t("af2.requestPayout")} <Icon name="arrow" size={18} className="arrow" />
          </>
        )}
      </button>
      <p role="alert" className="mt-2 min-h-5 text-center text-sm font-semibold text-bad">
        {error}
      </p>

      {!!me.payouts?.length && (
        <ul className="mt-1 space-y-1 text-xs text-ink-2">
          {me.payouts.map((x) => (
            <li key={x.id} className="flex items-center gap-2">
              <span className="ac-chip capitalize" data-tone={STATUS_TONE[x.status]}>
                {x.status}
              </span>
              <span className="tabular-nums">{bdt(x.amountBdt)}</span>
              <span className="ml-auto">{fmtDate(x.paidAt ?? x.requestedAt)}</span>
              {x.reference && <span className="font-mono">{x.reference}</span>}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

function EditCode({ code, editable, onUpdate }: { code: string; editable: boolean; onUpdate: (b: { code: string }) => Promise<void> }) {
  const tr = useT();
  const { t } = tr;
  const [open, setOpen] = useState(false);
  const [value, setValue] = useState(code);
  const [error, setError] = useState("");
  const [state, setState] = useState<Busy>("idle");
  if (!editable) return <p className="text-center text-xs text-ink-3">{t("af2.final", { code })}</p>;
  return (
    <section className="card p-4">
      <button type="button" className="flex min-h-11 w-full items-center justify-between text-sm font-semibold" aria-expanded={open} onClick={() => setOpen((o) => !o)}>
        {t("af2.changeOnce")}
        <Icon name="chevron" size={18} className={"transition-transform " + (open ? "rotate-180" : "")} />
      </button>
      {open && (
        <form
          className="fade-in mt-2 flex gap-2"
          onSubmit={async (e) => {
            e.preventDefault();
            setState("busy");
            try {
              await onUpdate({ code: value });
              setState("done");
            } catch (err) {
              setState("idle");
              setError(err instanceof ApiError ? err.message : t("af2.changeFail"));
            }
          }}
        >
          <div className="field flex-1">
            <input id="code-new" placeholder=" " value={value} maxLength={16} onChange={(e) => setValue(e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ""))} />
            <label htmlFor="code-new">{t("af2.newCode")}</label>
            <span className="field-icon">
              <Icon name="tag" size={20} />
            </span>
          </div>
          <button type="submit" className="btn btn-primary" disabled={state !== "idle" || value === code || value.length < 4} aria-busy={state === "busy"}>
            {state === "busy" ? <Spinner /> : t("common.save")}
          </button>
        </form>
      )}
      {open && <p className="ac-help" data-tone={error ? "bad" : "warn"}>{error || t("af2.oldStop")}</p>}
    </section>
  );
}

export function AffiliateLinkHint() {
  const tr = useT();
  const { t } = tr;
  return (
    <Link href="/affiliate" className="text-sm font-semibold text-accent">
      {t("af2.how")}
    </Link>
  );
}
