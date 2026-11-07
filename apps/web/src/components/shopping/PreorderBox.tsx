"use client";

import "./shopping.css";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { useFeature } from "@/components/features/FeaturesProvider";
import { apiEnabled, ApiError, goToPayment } from "@/lib/apiClient";
import { placePreorder, preorderInfo, type HistoryInput } from "@/lib/shopping/client";
import type { PreorderInfo } from "@/lib/shopping/types";
import { useStore } from "@/state/store";
import { useT } from "@/i18n/client";
import { rich } from "@/i18n/rich";
import type { MsgKey } from "@/i18n/core";
import { bdName } from "@/lib/bd";

const PAY = [
  { id: "bkash", label: "bKash" },
  { id: "nagad", label: "Nagad" },
  { id: "card", label: "pay.card" },
] as const;

/**
 * Out-of-stock helper: how many shoppers wait for the restock (the "Tell me when it's back" alert above adds you),
 * and, when the admin allows it, a pre-order with a deposit now and the balance when stock returns.
 */
export function PreorderBox({ p, inStock }: { p: HistoryInput; inStock: boolean }) {
  const preordersOn = useFeature("preorders");
  const waitlistOn = useFeature("waitlist");
  const { user, addresses } = useStore();
  const router = useRouter();
  const toast = useToast();
  const { t, bdt, digits, lang } = useT();
  const [info, setInfo] = useState<PreorderInfo | null>(null);
  const [open, setOpen] = useState(false);
  const [qty, setQty] = useState(1);
  const [pay, setPay] = useState<(typeof PAY)[number]["id"]>("bkash");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (inStock) return;
    let live = true;
    preorderInfo(p, inStock)
      .then((i) => live && setInfo(i))
      .catch(() => live && setInfo(null));
    return () => {
      live = false;
    };
  }, [p, inStock]);

  if (inStock || !info) return null;
  const canPreorder = preordersOn && info.available && info.depositBdt;
  const waiting = waitlistOn && info.waitlist ? info.waitlist : 0;
  if (!canPreorder && !waiting) return null;

  const deposit = (info.depositBdt ?? 0) * qty;
  const address = addresses.find((a) => a.isDefault) ?? addresses[0];

  async function submit() {
    if (!apiEnabled) {
      toast({ kind: "info", title: t("po.demo"), body: t("po.demoBody") });
      return;
    }
    if (!user) {
      router.push(`/login?next=/p/${encodeURIComponent(p.id)}`);
      return;
    }
    if (!address) {
      toast({ kind: "info", title: t("po.addAddress"), body: t("po.addAddressBody") });
      router.push("/account/addresses");
      return;
    }
    setBusy(true);
    try {
      const r = await placePreorder({
        productId: p.id,
        qty,
        ...(/^\d+$/.test(address.id) ? { addressId: Number(address.id) } : { address }),
        payment: pay,
        returnUrl: `${window.location.origin}/checkout/success`,
      });
      if (r.payment.redirectUrl) goToPayment(r.payment.redirectUrl);
      else router.push(`/orders/${r.order.id}`);
    } catch (e) {
      toast({ kind: "error", title: t("po.failed"), body: e instanceof ApiError ? e.message : t("common.pleaseRetry") });
    } finally {
      setBusy(false);
    }
  }

  return (
    <section className="mt-4 overflow-hidden rounded-2xl border border-line bg-surface" aria-labelledby="sh-po-h">
      <div className="flex items-start gap-3 p-4">
        <span className="chip-icon shrink-0">
          <Icon name="clock" size={18} />
        </span>
        <div className="min-w-0 flex-1">
          <h2 id="sh-po-h" className="font-extrabold tracking-tight">
            {canPreorder ? t("po.now") : t("po.waitingRestock")}
          </h2>
          {waiting > 0 && (
            <p className="mt-0.5 text-xs text-ink-2">
              {rich(t("po.waiting", { count: waiting }), { count: <b className="text-ink">{digits(waiting)}</b> })}
{info.watching ? t("po.includingYou") : ""}{lang === "bn" ? "।" : "."}
            </p>
          )}
          {canPreorder && (
            <p className="mt-1 text-sm text-ink-2">
              {rich(t("po.deposit", { pct: info.depositPct }), { amount: <b className="text-ink">{bdt(info.depositBdt!)}</b> })}
{info.etaDays ? t("po.eta", { days: info.etaDays }) : ""}{t("po.cancel")}
            </p>
          )}
          {info.note && <p className="mt-1 text-xs text-ink-2">{info.note}</p>}
        </div>
      </div>

      {canPreorder && !open && (
        <div className="px-4 pb-4">
          <button type="button" className="btn btn-primary min-h-11 w-full" onClick={() => setOpen(true)}>
            {t("po.with", { amount: bdt(info.depositBdt!) })}
          </button>
        </div>
      )}

      {canPreorder && open && (
        <div className="rise border-t border-line bg-surface-2/60 p-4">
          <div className="flex items-center justify-between gap-3">
            <span className="text-sm font-semibold">{t("qty.label")}</span>
            <div className="flex items-center gap-1 rounded-full bg-surface p-1 shadow-[var(--shadow-1)]">
              <button type="button" aria-label={t("common.fewer")} className="grid h-11 w-11 place-items-center rounded-full" onClick={() => setQty((q) => Math.max(1, q - 1))}>
                <Icon name="minus" size={16} />
              </button>
              <span className="w-6 text-center font-bold tabular-nums" aria-live="polite">
                {digits(qty)}
</span>
              <button type="button" aria-label={t("common.more")} className="grid h-11 w-11 place-items-center rounded-full" onClick={() => setQty((q) => Math.min(10, q + 1))}>
                <Icon name="plus" size={16} />
              </button>
            </div>
          </div>
          <fieldset className="mt-3">
            <legend className="text-sm font-semibold">{t("po.payWith")}</legend>
            <div className="mt-2 grid grid-cols-3 gap-2">
              {PAY.map((o) => (
                <label
                  key={o.id}
                  className={
                    "grid min-h-11 cursor-pointer place-items-center rounded-xl border text-sm font-bold transition " +
                    (pay === o.id ? "border-accent bg-[var(--accent-soft)] text-accent" : "border-line bg-surface text-ink-2")
                  }
                >
                  <input type="radio" name="sh-po-pay" className="sr-only" checked={pay === o.id} onChange={() => setPay(o.id)} />
                  {o.id === "card" ? t(o.label as MsgKey) : o.label}
</label>
              ))}
            </div>
          </fieldset>
          {address && (
            <p className="mt-3 flex items-start gap-2 text-xs text-ink-2">
              <Icon name="pin" size={14} className="mt-px shrink-0" /> {t("po.deliverTo", { name: address.name, area: address.area, district: bdName(address.district, lang) })}
            </p>
          )}
          <button type="button" className="btn btn-primary mt-4 min-h-11 w-full" disabled={busy} onClick={submit}>
            {busy ? t("po.placing") : t("po.pay", { amount: bdt(deposit) })}
          </button>
        </div>
      )}
    </section>
  );
}
