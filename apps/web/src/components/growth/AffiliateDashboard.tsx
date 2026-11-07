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
import { formatBdt } from "@/lib/pricing";
import { sitePath, type AffiliateMe } from "@/lib/growth";
import { useAffiliate } from "./useGrowth";
import { useFeature } from "@/components/features/FeaturesProvider";
import { NotAvailable } from "./NotAvailable";

type Busy = "idle" | "busy" | "done";
const pad = (n: number) => String(n).padStart(2, "0");
const fmtDate = (iso?: string | null) => (iso ? new Date(iso).toLocaleDateString("en-GB", { day: "numeric", month: "short" }) : "");
const STATUS_TONE = { pending: "warn", approved: undefined, reversed: "bad", paid: "good", requested: "warn", rejected: "muted" } as const;
const METHODS = [
  { id: "bkash", label: "bKash" },
  { id: "nagad", label: "Nagad" },
] as const;

export function AffiliateDashboard() {
  const aff = useAffiliate();
  const enabled = useFeature("affiliate");

  if (!enabled)
    return (
      <Shell>
        <NotAvailable title="The affiliate program" />
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
        <SignedOut next="/account/affiliate" title="Sign in to join the affiliate program" body="Earn commission when people order through your links." />
      </Shell>
    );
  if (aff.error)
    return (
      <Shell>
        <div className="card p-6 text-center">
          <p className="font-bold">{aff.error}</p>
          <button type="button" className="btn btn-primary mt-4" onClick={aff.reload}>
            <Icon name="refresh" size={16} /> Try again
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
    <Shell sub={a ? `Code ${a.code}` : "Earn on every order you bring"}>
      {!aff.live && (
        <p className="mb-4 inline-flex items-center gap-2 rounded-xl bg-surface-2 px-3 py-2 text-xs font-semibold text-ink-2">
          <Icon name="info" size={15} className="text-accent" /> Demo data: nothing here is real money.
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
  return (
    <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
      <PageHead title="Affiliate" sub={sub} icon="tag" back={{ href: "/account", label: "Account" }} />
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
      setError(err instanceof ApiError ? err.message : "Couldn't send your application. Try again.");
    }
  }

  return (
    <form className="card rise p-5" onSubmit={submit} noValidate>
      <h2 className="text-lg font-extrabold tracking-tight">Apply to the affiliate program</h2>
      <p className="mt-1 text-sm text-ink-2">
        Earn {program.defaultRate}% (up to {Math.max(program.defaultRate, ...Object.values(program.rates))}% by category) on orders from your links. Paid to bKash or
        Nagad from {formatBdt(program.minPayoutBdt)}.
      </p>

      <div className={"mt-5 space-y-3 " + shake.shakeClass} onAnimationEnd={shake.onAnimationEnd}>
        <div className={"field " + (code && codeOk ? "is-valid" : !codeOk ? "is-invalid" : "")}>
          <input id="aff-code" placeholder=" " value={code} maxLength={16} autoCapitalize="characters" onChange={(e) => setCode(e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ""))} aria-describedby="aff-code-help" />
          <label htmlFor="aff-code">Your code (optional)</label>
          <span className="field-icon">
            <Icon name="tag" size={20} />
          </span>
        </div>
        <p id="aff-code-help" className="ac-help" data-tone={!codeOk ? "bad" : code ? "good" : undefined}>
          {!codeOk ? "4-16 letters or digits" : code ? `Links will end in ?ref=${code}` : "Leave empty and we pick one from your name. You can change it once."}
          <span className="ml-auto tabular-nums text-ink-3">{code.length}/16</span>
        </p>

        <div role="radiogroup" aria-label="Payout method" className="ac-tabs" style={{ ["--n" as string]: 2 }}>
          <span aria-hidden className="ac-tab-pill" style={{ ["--idx" as string]: method === "bkash" ? 0 : 1 }} />
          {METHODS.map((m) => (
            <button key={m.id} type="button" role="radio" aria-checked={method === m.id} className="ac-tab min-h-11" onClick={() => setMethod(m.id)}>
              <Icon name="wallet" size={16} /> {m.label}
            </button>
          ))}
        </div>

        <div className={"field " + (phoneOk ? "is-valid" : "is-invalid")}>
          <input id="aff-number" inputMode="numeric" placeholder=" " value={digits} maxLength={11} onChange={(e) => setDigits(e.target.value.replace(/\D/g, ""))} />
          <label htmlFor="aff-number">{method === "bkash" ? "bKash" : "Nagad"} number</label>
          <span className="field-icon">
            <Icon name="phone" size={20} />
          </span>
        </div>
        {!phoneOk && (
          <p className="ac-help" data-tone="bad">
            Enter an 11 digit mobile number (01XXXXXXXXX)
          </p>
        )}

        <div className="field">
          <textarea id="aff-about" placeholder=" " value={about} maxLength={500} onChange={(e) => setAbout(e.target.value)} />
          <label htmlFor="aff-about">Where will you share? (optional)</label>
          <span className="field-icon">
            <Icon name="globe" size={20} />
          </span>
        </div>
      </div>

      <button type="submit" className="btn btn-primary mt-4 w-full" disabled={state !== "idle"} aria-busy={state === "busy"}>
        {state === "busy" ? (
          <>
            <Spinner /> Sending application...
          </>
        ) : state === "done" ? (
          <>
            <Icon name="check" size={18} stroke={2.6} /> Application sent
          </>
        ) : (
          <>
            Apply now <Icon name="arrow" size={18} className="arrow" />
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
  if (status === "suspended")
    return (
      <div className="card rise p-5">
        <span className="ac-chip" data-tone="bad">
          Suspended
        </span>
        <h2 className="mt-2 text-lg font-extrabold">Your affiliate links are paused</h2>
        <p className="mt-1 text-sm text-ink-2">Orders from your links do not earn commission right now. Contact support if you think this is a mistake.</p>
      </div>
    );
  const steps = [
    { label: "Application received", sub: `Your code ${code} is reserved`, icon: "check", state: "done" },
    { label: "Our team reviews it", sub: "Usually within 1 working day", icon: "shield", state: "current" },
    { label: "Start earning", sub: "Share links and track every order here", icon: "spark", state: "next" },
  ];
  return (
    <div className="card rise p-5">
      <span className="ac-chip" data-tone="warn">
        Under review
      </span>
      <h2 className="mt-2 text-lg font-extrabold">Thanks for applying!</h2>
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
                <span className="sr-only">{s.state === "done" ? " (done)" : s.state === "current" ? " (in progress)" : ""}</span>
              </span>
              <span className="block text-xs text-ink-2">{s.sub}</span>
            </span>
          </li>
        ))}
      </ol>
      {onDemoApprove && (
        <button type="button" className="btn btn-ghost mt-5" onClick={onDemoApprove}>
          <Icon name="bolt" size={16} /> Approve now (demo)
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
  const a = me.affiliate!;
  const s = me.stats!;
  const p = me.program;
  const homeLink = useMemo(() => (typeof window === "undefined" ? a.link : `${window.location.origin}/?ref=${a.code}`), [a.code, a.link]);
  const total = s.pendingBdt + s.approvedBdt + s.paidBdt + s.requestedBdt;
  const segs = [
    { label: "Pending", value: s.pendingBdt, color: "var(--warn, #f59e0b)" },
    { label: "Approved", value: s.approvedBdt, color: "var(--accent)" },
    { label: "Paid", value: s.paidBdt, color: "var(--good)" },
  ];
  const stats = [
    { label: "Clicks (30 days)", value: pad(s.clicks30d), icon: "bolt" },
    { label: "Orders", value: pad(s.orders), icon: "receipt" },
    { label: "Conversion", value: `${s.conversion}%`, icon: "spark" },
    { label: "Paid to you", value: formatBdt(s.paidBdt), icon: "wallet" },
  ];

  return (
    <div className="space-y-4">
      {/* code + main link */}
      <section className="rise relative overflow-hidden rounded-[20px] p-5 text-white shadow-[var(--glow)]" style={{ background: "linear-gradient(135deg, var(--hero-a), var(--hero-b))" }}>
        <div aria-hidden className="absolute -right-10 -top-12 h-40 w-40 rounded-full bg-white/10" />
        <div className="relative">
          <div className="flex flex-wrap items-center gap-2">
            <span className="rounded-full bg-white/20 px-3 py-1 text-xs font-bold tracking-wide backdrop-blur-sm">ACTIVE</span>
            <span className="text-sm text-white/85">{a.rate !== null ? `Your rate: ${a.rate}% on everything` : `${p.defaultRate}% standard, more in some categories`}</span>
          </div>
          <p className="mt-3 font-mono text-3xl font-extrabold tracking-widest">{a.code}</p>
          <p className="mt-1 truncate text-sm text-white/85">{homeLink.replace(/^https?:\/\//, "")}</p>
          <div className="mt-4 flex flex-wrap gap-2">
            <CopyButton text={homeLink} label="Copy link" className="btn min-h-11 bg-white px-4 text-sm font-bold text-slate-900" />
            <ShareButton variant="ghost" label="Share" path="/" title="Skydrop Retail" className="!border-white/40 !bg-white/10 !text-white" qr />
          </div>
        </div>
      </section>

      <section aria-label="Stats" className="grid grid-cols-2 gap-3 sm:grid-cols-4">
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
            Earnings
          </h2>
          <span className="text-sm font-bold tabular-nums">{formatBdt(total)}</span>
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
              <b className="ml-auto tabular-nums">{formatBdt(g.value)}</b>
            </li>
          ))}
        </ul>
        <p className="mt-2 text-xs text-ink-3">
          Commission is approved {p.holdDays} days after delivery (return window). Cancelled or refunded orders are reversed.
        </p>
      </section>

      <LinkBuilder code={a.code} />

      <PayoutCard me={me} onUpdate={onUpdate} onPayout={onPayout} />

      <section className="card rise overflow-hidden" aria-labelledby="com-h" style={{ ["--i" as string]: 8 }}>
        <h2 id="com-h" className="px-4 pt-4 text-sm font-extrabold">
          Latest commissions
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
                    {fmtDate(c.at)} · {c.rate}% of {formatBdt(c.baseBdt)}
                    {c.status === "pending" && (c.approveAfter ? ` · approves ${fmtDate(c.approveAfter)}` : " · awaiting delivery")}
                  </span>
                </span>
                <span className="text-right">
                  <span className={"block text-sm font-bold tabular-nums " + (c.status === "reversed" ? "text-ink-3 line-through" : "")}>{formatBdt(c.amountBdt)}</span>
                  <span className="ac-chip mt-0.5 capitalize" data-tone={STATUS_TONE[c.status]}>
                    {c.status}
                  </span>
                </span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="px-4 pb-4 pt-2 text-sm text-ink-2">No orders yet. Share your first link above: orders appear here as soon as they are placed.</p>
        )}
      </section>

      <EditCode code={a.code} editable={a.codeEditable} onUpdate={onUpdate} />
    </div>
  );
}

function LinkBuilder({ code }: { code: string }) {
  const [input, setInput] = useState("");
  const origin = typeof window === "undefined" ? "" : window.location.origin;
  const path = input ? sitePath(input, origin) : null;
  const link = path ? `${origin}${path}${path.includes("?") ? "&" : "?"}ref=${code}` : null;
  const invalid = input.trim().length > 3 && !path;

  return (
    <section className="card rise p-4" aria-labelledby="lb-h" style={{ ["--i" as string]: 6 }}>
      <h2 id="lb-h" className="text-sm font-extrabold">
        Link builder
      </h2>
      <p className="text-xs text-ink-2">Paste any product or search link from Skydrop Retail to get your own link.</p>
      <div className={"field mt-3 " + (link ? "is-valid" : invalid ? "is-invalid" : "")}>
        <input id="lb-url" placeholder=" " value={input} onChange={(e) => setInput(e.target.value)} inputMode="url" autoComplete="off" aria-describedby="lb-help" />
        <label htmlFor="lb-url">Product link</label>
        <span className="field-icon">
          <Icon name="globe" size={20} />
        </span>
      </div>
      <p id="lb-help" className="ac-help" data-tone={invalid ? "bad" : link ? "good" : undefined}>
        {invalid ? "That isn't a Skydrop Retail link" : link ? "Your link is ready" : `e.g. ${origin.replace(/^https?:\/\//, "")}/p/B0EXAMPLE`}
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
          ["Home", "/"],
          ["Deals", "/deals"],
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
      toast({ kind: "success", title: "Payout requested", body: `We'll send it to your ${method === "bkash" ? "bKash" : "Nagad"} within 3 working days.` });
    } catch (e) {
      setState("idle");
      setError(e instanceof ApiError ? e.message : "Couldn't request the payout. Try again.");
    }
  }

  async function save() {
    if (!checkPhone(digits).valid) {
      setError("Enter an 11 digit mobile number (01XXXXXXXXX)");
      return;
    }
    try {
      await onUpdate({ payoutMethod: method, payoutNumber: toE164(digits) });
      setEditing(false);
      setError("");
    } catch (e) {
      setError(e instanceof ApiError ? e.message : "Couldn't save. Try again.");
    }
  }

  return (
    <section className="card rise p-4" aria-labelledby="pay-h" style={{ ["--i" as string]: 7 }}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 id="pay-h" className="text-sm font-extrabold">
            Payout
          </h2>
          <p className="text-2xl font-extrabold tabular-nums">{formatBdt(s.availableBdt)}</p>
          <p className="text-xs text-ink-2">available{s.requestedBdt ? ` · ${formatBdt(s.requestedBdt)} on its way` : ""}</p>
        </div>
        <span className="chip-icon is-active">
          <Icon name="wallet" size={18} />
        </span>
      </div>
      <div className="ac-bar mt-3" data-tone={ratio >= 1 ? "good" : undefined} role="progressbar" aria-valuemin={0} aria-valuemax={min} aria-valuenow={Math.min(min, s.availableBdt)} aria-label="Progress to minimum payout">
        <span style={{ ["--p" as string]: ratio }} />
      </div>
      <p className="mt-1 text-xs text-ink-2">{ratio >= 1 ? "You can request a payout" : `${formatBdt(min - s.availableBdt)} more to reach the ${formatBdt(min)} minimum`}</p>

      {editing ? (
        <div className="fade-in mt-3 space-y-2">
          <div role="radiogroup" aria-label="Payout method" className="ac-tabs" style={{ ["--n" as string]: 2 }}>
            <span aria-hidden className="ac-tab-pill" style={{ ["--idx" as string]: method === "bkash" ? 0 : 1 }} />
            {METHODS.map((m) => (
              <button key={m.id} type="button" role="radio" aria-checked={method === m.id} className="ac-tab min-h-11" onClick={() => setMethod(m.id)}>
                {m.label}
              </button>
            ))}
          </div>
          <div className="field">
            <input id="pay-num" inputMode="numeric" placeholder=" " value={digits} maxLength={11} onChange={(e) => setDigits(e.target.value.replace(/\D/g, ""))} />
            <label htmlFor="pay-num">Wallet number</label>
            <span className="field-icon">
              <Icon name="phone" size={20} />
            </span>
          </div>
          <div className="flex gap-2">
            <button type="button" className="btn btn-primary flex-1" onClick={save}>
              Save
            </button>
            <button type="button" className="btn btn-ghost" onClick={() => setEditing(false)}>
              Cancel
            </button>
          </div>
        </div>
      ) : (
        <div className="mt-3 flex items-center gap-2 rounded-xl bg-surface-2 px-3 py-2 text-sm">
          <Icon name="phone" size={16} className="text-ink-3" />
          <span className="flex-1">
            {a.payoutMethod === "nagad" ? "Nagad" : "bKash"} <span className="tabular-nums">{a.payoutNumber ?? "not set"}</span>
          </span>
          <button type="button" className="min-h-11 px-2 text-sm font-semibold text-accent" onClick={() => setEditing(true)}>
            Change
          </button>
        </div>
      )}

      <button type="button" className="btn btn-primary mt-3 w-full" onClick={request} disabled={!canRequest || state !== "idle"} aria-busy={state === "busy"}>
        {state === "busy" ? (
          <>
            <Spinner /> Requesting...
          </>
        ) : state === "done" || s.requestedBdt > 0 ? (
          <>
            <Icon name="check" size={18} stroke={2.6} /> Payout requested
          </>
        ) : (
          <>
            Request payout <Icon name="arrow" size={18} className="arrow" />
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
              <span className="tabular-nums">{formatBdt(x.amountBdt)}</span>
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
  const [open, setOpen] = useState(false);
  const [value, setValue] = useState(code);
  const [error, setError] = useState("");
  const [state, setState] = useState<Busy>("idle");
  if (!editable) return <p className="text-center text-xs text-ink-3">Your code {code} is final.</p>;
  return (
    <section className="card p-4">
      <button type="button" className="flex min-h-11 w-full items-center justify-between text-sm font-semibold" aria-expanded={open} onClick={() => setOpen((o) => !o)}>
        Change your code (once)
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
              setError(err instanceof ApiError ? err.message : "Couldn't change the code.");
            }
          }}
        >
          <div className="field flex-1">
            <input id="code-new" placeholder=" " value={value} maxLength={16} onChange={(e) => setValue(e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, ""))} />
            <label htmlFor="code-new">New code</label>
            <span className="field-icon">
              <Icon name="tag" size={20} />
            </span>
          </div>
          <button type="submit" className="btn btn-primary" disabled={state !== "idle" || value === code || value.length < 4} aria-busy={state === "busy"}>
            {state === "busy" ? <Spinner /> : "Save"}
          </button>
        </form>
      )}
      {open && <p className="ac-help" data-tone={error ? "bad" : "warn"}>{error || "Links you already shared with the old code stop earning."}</p>}
    </section>
  );
}

export function AffiliateLinkHint() {
  return (
    <Link href="/affiliate" className="text-sm font-semibold text-accent">
      How the program works
    </Link>
  );
}
