"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useId, useState } from "react";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { useFeature } from "@/components/features/FeaturesProvider";
import { ApiError, apiEnabled } from "@/lib/apiClient";
import { DEMO_GIFT_CONFIG, money, type GiftCard, type GiftCardConfig } from "@/lib/money";
import { useStore } from "@/state/store";
import { RedeemGiftCard } from "./RedeemGiftCard";
import "./money.css";
import { useT } from "@/i18n/client";
import type { MsgKey } from "@/i18n/core";

const PAY = [
  { id: "bkash", label: "bKash", icon: "wallet" },
  { id: "nagad", label: "Nagad", icon: "phone" },
  { id: "card", label: "pay.card", icon: "card" },
] as const;
type Pay = (typeof PAY)[number]["id"];

export function GiftCardVisual({ design, amount, to, message, code }: { design: string; amount: number; to?: string; message?: string; code?: string }) {
  const { t, bdt } = useT();
  return (
    <div className="gc-card p-5" data-design={design} aria-label={t("gc.preview", { amount: bdt(amount) })}>
      <div className="flex h-full flex-col justify-between">
        <div className="flex items-start justify-between gap-3">
          <span className="text-sm font-extrabold tracking-tight">Skydrop Retail</span>
          <span className="rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider">{t("gc.card")}</span>
        </div>
        <div>
          <p key={amount} className="gc-amount text-4xl font-extrabold tabular-nums tracking-tight">
            {bdt(amount || 0)}
          </p>
          {message ? <p className="mt-1 line-clamp-2 text-xs text-white/85">&ldquo;{message}&rdquo;</p> : null}
        </div>
        <div className="flex items-end justify-between gap-3 text-xs">
          <span className="truncate font-semibold text-white/90">{to ? t("gc.for", { name: to }) : t("gc.special")}</span>
          <span className="shrink-0 font-mono tracking-wider text-white/80">{code ?? "XXXX-XXXX-XXXX-XXXX"}</span>
        </div>
      </div>
    </div>
  );
}

export function GiftCardShop({ redeem }: { redeem?: boolean }) {
  const { user } = useStore();
  const on = useFeature("gift_cards");
  const walletOn = useFeature("wallet");
  const toast = useToast();
  const { t, bdt, date } = useT();
  const router = useRouter();
  const uid = useId();
  const [cfg, setCfg] = useState<GiftCardConfig>(DEMO_GIFT_CONFIG);
  const [amount, setAmount] = useState(1000);
  const [custom, setCustom] = useState("");
  const [design, setDesign] = useState("classic");
  const [to, setTo] = useState("");
  const [phone, setPhone] = useState("");
  const [email, setEmail] = useState("");
  const [message, setMessage] = useState("");
  const [pay, setPay] = useState<Pay>("bkash");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [mine, setMine] = useState<GiftCard[]>([]);

  useEffect(() => {
    if (!on) return;
    money
      .giftCardConfig()
      .then(setCfg)
      .catch(() => {});
  }, [on]);

  useEffect(() => {
    if (!on || !apiEnabled || !user) return;
    money
      .myGiftCards()
      .then((r) => setMine(r.items))
      .catch(() => {});
  }, [on, user]);

  if (!on)
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-10 text-center md:pb-12">
        <h1 className="text-2xl font-extrabold">{t("meta.giftCards")}</h1>
        <p className="mt-2 text-sm text-ink-2">{t("gc.off")}</p>
      </div>
    );

  const value = custom ? Number(custom) : amount;
  const inRange = value >= cfg.minBdt && value <= cfg.maxBdt;
  const contactOk = /^(?:\+?880|0)?1[3-9]\d{8}$/.test(phone.replace(/[\s-]/g, "")) || /^\S+@\S+\.\S+$/.test(email);
  const canBuy = inRange && to.trim().length > 0 && contactOk;

  const buy = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!canBuy || busy) return;
    if (!apiEnabled) {
      toast({ kind: "info", title: t("gc.previewStore"), body: t("gc.previewB") });
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const r = await money.buyGiftCard({
        amountBdt: value,
        design,
        recipientName: to.trim(),
        recipientPhone: phone.trim() || undefined,
        recipientEmail: email.trim() || undefined,
        message: message.trim() || undefined,
        payment: pay,
        returnUrl: `${window.location.origin}/gift-cards/success`,
      });
      if (r.payment.redirectUrl) window.location.assign(r.payment.redirectUrl);
      else router.push(`/gift-cards/success?order=${r.order.id}&status=${r.payment.status === "paid" ? "paid" : "failed"}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("gc.payFail"));
      setBusy(false);
    }
  };

  return (
    <div className="mx-auto max-w-6xl px-4 pb-24 pt-6 sm:px-6 md:pb-12">
      <header className="mb-6">
        <p className="text-sm font-bold text-accent">{t("gc.kicker")}</p>
        <h1 className="text-3xl font-extrabold tracking-tight sm:text-4xl">{t("gc.title")}</h1>
        <p className="mt-2 max-w-xl text-sm text-ink-2">
          {t("gc.sub")}
        </p>
      </header>

      <div className="grid grid-cols-1 gap-6 md:grid-cols-[minmax(0,1fr)_380px]">
        <form onSubmit={buy} className="order-2 space-y-5 md:order-1" noValidate>
          <fieldset className="card min-w-0 p-4">
            <legend className="sr-only">{t("gc.amount")}</legend>
            <p className="mb-3 text-sm font-extrabold">{t("gc.step1")}</p>
            <div role="radiogroup" aria-label={t("gc.amount")} className="flex flex-wrap gap-2">
              {cfg.presets.map((p) => (
                <button
                  key={p}
                  type="button"
                  role="radio"
                  aria-checked={!custom && amount === p}
                  className="money-chip"
                  onClick={() => {
                    setAmount(p);
                    setCustom("");
                  }}
                >
                  {bdt(p)}
                </button>
              ))}
            </div>
            <label htmlFor={uid + "-custom"} className="mt-3 block text-xs font-semibold text-ink-2">
              {t("gc.orEnter", { min: bdt(cfg.minBdt), max: bdt(cfg.maxBdt) })}
            </label>
            <input
              id={uid + "-custom"}
              inputMode="numeric"
              value={custom}
              onChange={(e) => setCustom(e.target.value.replace(/\D/g, "").slice(0, 6))}
              placeholder={t("gc.custom")}
              aria-invalid={!!custom && !inRange}
              className="mt-1 min-h-11 w-full rounded-2xl border border-line bg-surface px-3 tabular-nums outline-none focus:border-accent sm:w-56"
            />
            {custom && !inRange && <p className="mt-1 text-xs font-semibold text-bad">{t("gc.between", { min: bdt(cfg.minBdt), max: bdt(cfg.maxBdt) })}</p>}
          </fieldset>

          <fieldset className="card min-w-0 p-4">
            <legend className="sr-only">{t("gc.design")}</legend>
            <p className="mb-3 text-sm font-extrabold">{t("gc.step2")}</p>
            <div role="radiogroup" aria-label={t("gc.design")} className="grid grid-cols-5 gap-2">
              {cfg.designs.map((d) => (
                <button
                  key={d.key}
                  type="button"
                  role="radio"
                  aria-checked={design === d.key}
                  aria-label={t(d.label as MsgKey)}
                  title={t(d.label as MsgKey)}
                  onClick={() => setDesign(d.key)}
                  className="gc-swatch gc-card aspect-[1.586] !rounded-xl !shadow-none"
                  data-design={d.key}
                />
              ))}
            </div>
          </fieldset>

          <fieldset className="card min-w-0 space-y-3 p-4">
            <legend className="sr-only">{t("gc.recipient")}</legend>
            <p className="text-sm font-extrabold">{t("gc.step3")}</p>
            {[
              { id: "to", label: t("gc.recipientName"), value: to, set: setTo, type: "text", auto: "name", max: 100 },
              { id: "phone", label: t("gc.theirMobile"), value: phone, set: setPhone, type: "tel", auto: "tel", max: 20 },
              { id: "email", label: t("gc.theirEmail"), value: email, set: setEmail, type: "email", auto: "email", max: 190 },
            ].map((f) => (
              <div key={f.id}>
                <label htmlFor={uid + f.id} className="block text-xs font-semibold text-ink-2">
                  {f.label}
                </label>
                <input
                  id={uid + f.id}
                  type={f.type}
                  autoComplete={f.auto}
                  maxLength={f.max}
                  value={f.value}
                  onChange={(e) => f.set(e.target.value)}
                  className="mt-1 min-h-11 w-full rounded-2xl border border-line bg-surface px-3 outline-none focus:border-accent"
                />
              </div>
            ))}
            <div>
              <label htmlFor={uid + "msg"} className="block text-xs font-semibold text-ink-2">
                {t("gc.message")}
              </label>
              <textarea
                id={uid + "msg"}
                rows={2}
                maxLength={300}
                value={message}
                onChange={(e) => setMessage(e.target.value)}
                className="mt-1 w-full rounded-2xl border border-line bg-surface px-3 py-2 outline-none focus:border-accent"
              />
              <p className="text-right text-[11px] text-ink-3">{message.length}/300</p>
            </div>
          </fieldset>

          <fieldset className="card min-w-0 p-4">
            <legend className="sr-only">{t("gc.payment")}</legend>
            <p className="mb-3 text-sm font-extrabold">{t("gc.step4")}</p>
            <div role="radiogroup" aria-label={t("pay.method")} className="flex flex-wrap gap-2">
              {PAY.map((p) => (
                <button key={p.id} type="button" role="radio" aria-checked={pay === p.id} className="money-chip gap-1.5" onClick={() => setPay(p.id)}>
                  <Icon name={p.icon} size={16} /> {p.id === "card" ? t("pay.card") : p.label}
                </button>
              ))}
            </div>
          </fieldset>

          {error && (
            <p role="alert" className="shake rounded-2xl bg-bad/10 p-3 text-sm font-semibold text-bad">
              {error}
            </p>
          )}

          {user ? (
            <button type="submit" disabled={!canBuy || busy} className="btn btn-primary min-h-12 w-full sm:w-auto">
              {busy ? <Icon name="refresh" size={17} className="animate-spin" /> : <Icon name="shield" size={17} />} {t("gc.pay", { amount: bdt(inRange ? value : 0) })}
            </button>
          ) : (
            <Link href="/login?next=%2Fgift-cards" className="btn btn-primary min-h-12 w-full sm:w-auto">
              <Icon name="user" size={17} /> {t("gc.signInBuy")}
            </Link>
          )}
          {!canBuy && user && <p className="text-xs text-ink-3">{t("gc.need")}</p>}
        </form>

        <aside className="order-1 md:order-2">
          <div className="space-y-4 md:sticky md:top-24">
            <GiftCardVisual design={design} amount={inRange ? value : 0} to={to.trim() || undefined} message={message.trim() || undefined} />
            <ul className="grid gap-2 text-xs text-ink-2">
              {[
                ["clock", cfg.expiryMonths ? t("gc.valid", { count: cfg.expiryMonths }) : t("gc.never")],
                ["phone", t("gc.delivered")],
                ["wallet", t("gc.redeemed")],
              ].map(([i, t]) => (
                <li key={t} className="flex items-center gap-2">
                  <Icon name={i} size={15} className="text-accent" /> {t}
                </li>
              ))}
            </ul>
            {user && walletOn && (
              <div id="redeem" className="card p-4">
                <RedeemGiftCard autoFocus={redeem} />
              </div>
            )}
          </div>
        </aside>
      </div>

      {mine.length > 0 && (
        <section className="mt-10" aria-labelledby="mine-h">
          <h2 id="mine-h" className="mb-3 text-lg font-extrabold">
            {t("gc.sent")}
          </h2>
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {mine.map((c, i) => (
              <li key={c.id} className="rise space-y-2" style={{ ["--i" as string]: i }}>
                <GiftCardVisual design={c.design} amount={c.initialBdt} to={c.recipientName} code={c.code} />
                <p className="flex justify-between text-xs text-ink-2">
                  <span className="font-semibold capitalize">{c.status}</span>
                  {c.expiresAt && <span>{t("gc.expires", { date: date(c.expiresAt, { day: "numeric", month: "short", year: "numeric" }) })}</span>}
                </p>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}
