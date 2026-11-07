"use client";

import Link from "next/link";
import { useEffect, useId, useRef, useState } from "react";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { useFeature } from "@/components/features/FeaturesProvider";
import { EmptyState, LoadingBlock, PageHead } from "@/components/account/ui";
import { ApiError } from "@/lib/apiClient";
import {
  MAX_UPLOAD_BYTES,
  REASON_LABELS,
  RESOLUTION_LABELS,
  RETURN_WINDOW_DAYS,
  apiEnabled,
  fulfilmentApi,
  type Eligibility,
  type Resolution,
  type ReturnCase,
  type ReturnReason,
} from "@/lib/fulfilment";
import { useStore } from "@/state/store";
import { ReturnTimeline } from "./OrderCases";
import "./fulfilment.css";
import { useT } from "@/i18n/client";
import type { MsgKey } from "@/i18n/core";

type Photo = { key: string; preview: string; id?: number; state: "uploading" | "done" | "error"; error?: string };
const STEPS: MsgKey[] = ["rw.items", "rw.reason", "rw.resolution"];

/** /orders/[id]/return: item select → reason chips + photos → refund / replacement / store credit. */
export function ReturnWizard({ id }: { id: string }) {
  const on = useFeature("returns");
  const walletOn = useFeature("wallet");
  const { ready, user, orders } = useStore();
  const toast = useToast();
  const { t, bdt } = useT();
  const fileId = useId();
  const fileRef = useRef<HTMLInputElement>(null);
  const [elig, setElig] = useState<Eligibility | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [step, setStep] = useState(0);
  const [qty, setQty] = useState<Record<number, number>>({});
  const [reason, setReason] = useState<ReturnReason | null>(null);
  const [note, setNote] = useState("");
  const [photos, setPhotos] = useState<Photo[]>([]);
  const [resolution, setResolution] = useState<Resolution | null>(null);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState<ReturnCase | null>(null);

  const order = orders.find((o) => o.id === id);

  useEffect(() => {
    if (!ready || !on) return;
    if (!apiEnabled) {
      // Demo: everything on a delivered order is returnable.
      if (order)
        setElig({
          eligible: order.status === "delivered",
          reason: order.status === "delivered" ? null : "not_delivered",
          windowDays: RETURN_WINDOW_DAYS,
          windowEndsAt: null,
          reasons: ["damaged", "wrong_item", "not_as_described", "size_issue"],
          resolutions: ["refund", "replacement", "store_credit"],
          items: order.items.map((it, i) => ({
            id: i + 1, productId: it.productId, title: it.title, variant: it.variant ?? null, image: it.image, market: it.market,
            qty: it.qty, returnableQty: it.qty, unitBdt: it.unitBdt,
          })),
        });
      return;
    }
    if (!user) return;
    fulfilmentApi
      .returns(id)
      .then((r) => setElig(r.eligibility))
      .catch((e: Error) => setError(e.message));
  }, [ready, user, id, on, order]);

  useEffect(() => () => photos.forEach((p) => URL.revokeObjectURL(p.preview)), [photos]);

  const back = { href: `/orders/${id}`, label: t("rw.order") };
  const shell = (children: React.ReactNode) => <div className="mx-auto max-w-2xl px-4 pb-24 pt-6 md:pb-12">{children}</div>;

  if (!on) return shell(<><PageHead title={t("rw.title")} icon="refresh" back={back} /><EmptyState title={t("rw.paused")} body={t("rw.pausedB")} action={{ label: t("rw.backOrder"), href: `/orders/${id}` }} /></>);
  if (error) return shell(<><PageHead title={t("rw.title")} icon="refresh" back={back} /><EmptyState title={t("rw.loadFail")} body={error} action={{ label: t("rw.backOrder"), href: `/orders/${id}` }} /></>);
  if (!elig) return shell(<LoadingBlock rows={3} />);
  if (done)
    return shell(
      <>
        <PageHead title={t("rw.requested")} icon="refresh" back={back} />
        <div className="card rise p-5 text-center">
          <span className="chip-icon is-active mx-auto mb-3 ff-pop">
            <Icon name="check" size={20} />
          </span>
          <p className="text-lg font-extrabold">{t("rw.got")}</p>
          <p className="mt-1 text-sm text-ink-2">{t("rw.gotB")}</p>
        </div>
        <div className="mt-4">
          <ReturnTimeline c={done} />
        </div>
        <Link href={`/orders/${id}`} className="btn btn-primary mt-4 h-12 w-full">
          {t("rw.backOrder")}
        </Link>
      </>,
    );
  if (!elig.eligible) {
    const why = {
      not_delivered: t("rw.notDelivered"),
      window_closed: t("rw.closed", { count: elig.windowDays }),
      already_requested: t("rw.already"),
      disabled: t("rw.disabled"),
    }[elig.reason ?? "disabled"];
    return shell(<><PageHead title={t("rw.title")} icon="refresh" back={back} /><EmptyState title={t("rw.cant")} body={why} action={{ label: t("rw.backOrder"), href: `/orders/${id}` }} /></>);
  }

  const chosen = elig.items.filter((i) => (qty[i.id] ?? 0) > 0);
  const value = chosen.reduce((s, i) => s + i.unitBdt * (qty[i.id] ?? 0), 0);
  const needPhoto = reason === "damaged" || reason === "wrong_item";
  const uploading = photos.some((p) => p.state === "uploading");
  const okPhotos = photos.filter((p) => p.state === "done");
  const resolutions = elig.resolutions.filter((r) => r !== "store_credit" || walletOn);
  const canNext = step === 0 ? chosen.length > 0 : step === 1 ? !!reason && !uploading && (!needPhoto || okPhotos.length > 0) : !!resolution;

  async function addFiles(files: FileList | null) {
    if (!files) return;
    for (const f of Array.from(files).slice(0, 6 - photos.length)) {
      const key = `${f.name}-${f.size}-${Math.random().toString(36).slice(2)}`;
      const preview = URL.createObjectURL(f);
      if (!f.type.startsWith("image/")) {
        toast({ kind: "error", title: t("rw.photosOnly"), body: t("rw.notImage", { name: f.name }) });
        URL.revokeObjectURL(preview);
        continue;
      }
      if (f.size > MAX_UPLOAD_BYTES) {
        toast({ kind: "error", title: t("rw.tooLarge"), body: t("rw.over5", { name: f.name }) });
        URL.revokeObjectURL(preview);
        continue;
      }
      setPhotos((p) => [...p, { key, preview, state: apiEnabled ? "uploading" : "done" }]);
      if (!apiEnabled) continue;
      try {
        const up = await fulfilmentApi.upload(f);
        setPhotos((p) => p.map((x) => (x.key === key ? { ...x, id: up.id, state: "done" } : x)));
      } catch (e) {
        setPhotos((p) => p.map((x) => (x.key === key ? { ...x, state: "error", error: e instanceof ApiError ? e.message : t("rw.uploadFailed") } : x)));
      }
    }
    if (fileRef.current) fileRef.current.value = "";
  }

  async function submit() {
    if (!reason || !resolution) return;
    setBusy(true);
    try {
      const body = {
        items: chosen.map((i) => ({ orderItemId: i.id, qty: qty[i.id] })),
        reason,
        note: note.trim() || undefined,
        photos: okPhotos.map((p) => p.id).filter((x): x is number => typeof x === "number"),
        resolution,
      };
      const r = apiEnabled
        ? await fulfilmentApi.createReturn(id, body)
        : ({
            id: 1, orderId: id, stage: "delivered", items: body.items, reason, reasonLabel: t(REASON_LABELS[reason]), note: body.note ?? null,
            photos: [], resolution, status: "requested", statusLabel: t("rst.requested"), refundBdt: value, replacementOrderId: null, pickupAt: null,
            createdAt: new Date().toISOString(), timeline: [{ status: "requested", at: new Date().toISOString(), note: t("rw.demoNote") }],
          } satisfies ReturnCase);
      setDone(r);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (e) {
      toast({ kind: "error", title: t("rw.sendFailed"), body: e instanceof ApiError ? e.message : t("common.pleaseRetry") });
    } finally {
      setBusy(false);
    }
  }

  return shell(
    <>
      <PageHead title={t("rw.title")} sub={t("rw.sub", { id, step: t(STEPS[step]) })} icon="refresh" back={back} />
      <div className="ff-steps mb-4" aria-hidden>
        {STEPS.map((s, i) => (
          <span key={s} data-on={i <= step} />
        ))}
      </div>
      <p className="sr-only" aria-live="polite">
        {t("rw.stepOf", { n: step + 1, total: STEPS.length, step: t(STEPS[step]) })}
      </p>

      {step === 0 && (
        <fieldset className="space-y-2 ff-fade" key="s0">
          <legend className="mb-2 text-base font-bold">{t("rw.which")}</legend>
          {elig.items.map((it) => {
            const n = qty[it.id] ?? 0;
            const disabled = it.returnableQty === 0;
            return (
              <label key={it.id} className={"ff-option " + (disabled ? "opacity-50" : "")}>
                <input
                  type="checkbox"
                  className="h-5 w-5 accent-[var(--accent)]"
                  disabled={disabled}
                  checked={n > 0}
                  onChange={(e) => setQty((q) => ({ ...q, [it.id]: e.target.checked ? it.returnableQty : 0 }))}
                />
                {it.image ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={it.image} alt="" className="h-12 w-12 shrink-0 rounded-xl bg-white object-contain" loading="lazy" />
                ) : null}
                <span className="min-w-0 flex-1">
                  <span className="line-clamp-2 block text-sm font-semibold">{it.title}</span>
                  <span className="block text-xs text-ink-2">
                    {it.variant ? it.variant + " · " : ""}
                    {disabled ? t("rw.inReturn") : t("cart.each", { amount: bdt(it.unitBdt) })}
                  </span>
                </span>
                {n > 0 && it.returnableQty > 1 && (
                  <select
                    aria-label={t("rw.qtyOf", { title: it.title })}
                    className="h-11 rounded-xl bg-surface-2 px-2 text-sm"
                    value={n}
                    onChange={(e) => setQty((q) => ({ ...q, [it.id]: Number(e.target.value) }))}
                    onClick={(e) => e.preventDefault()}
                  >
                    {Array.from({ length: it.returnableQty }, (_, k) => k + 1).map((k) => (
                      <option key={k} value={k}>
                        {k}
                      </option>
                    ))}
                  </select>
                )}
              </label>
            );
          })}
        </fieldset>
      )}

      {step === 1 && (
        <div className="space-y-4 ff-fade" key="s1">
          <div>
            <p className="mb-2 text-base font-bold" id="why-h">
              {t("rw.problem")}
            </p>
            <div className="flex flex-wrap gap-2" role="radiogroup" aria-labelledby="why-h">
              {elig.reasons.map((r) => (
                <button key={r} type="button" role="radio" aria-checked={reason === r} className="ff-chip" onClick={() => setReason(r)}>
                  {t(REASON_LABELS[r])}
                </button>
              ))}
            </div>
          </div>
          <div>
            <label htmlFor="rt-note" className="text-sm font-semibold">
              {t("rw.details")} <span className="font-normal text-ink-3">{t("rw.optional")}</span>
            </label>
            <textarea
              id="rt-note"
              rows={3}
              maxLength={1000}
              value={note}
              onChange={(e) => setNote(e.target.value)}
              className="mt-1 w-full rounded-2xl bg-surface-2 p-3 text-sm outline-none focus:ring-2 focus:ring-accent"
              placeholder={t("rw.notePh")}
            />
          </div>
          <div>
            <p className="text-sm font-semibold">
              {t("rw.photosLabel")} {needPhoto ? <span className="text-bad">{t("rw.atLeast")}</span> : <span className="font-normal text-ink-3">{t("rw.optional")}</span>}
            </p>
            <div className="ff-gallery mt-2">
              {photos.map((p) => (
                <div key={p.key} className="ff-thumb ff-pop">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={p.preview} alt={t("rw.yourPhoto")} style={{ opacity: p.state === "uploading" ? 0.5 : 1 }} />
                  {p.state === "uploading" && <span className="absolute inset-0 grid place-items-center text-xs font-bold">{t("rw.uploading")}</span>}
                  {p.state === "error" && (
                    <span className="absolute inset-x-0 bottom-0 bg-bad px-1 py-0.5 text-[10px] font-bold text-white" title={p.error}>
                      {t("rw.failed")}
                    </span>
                  )}
                  <button
                    type="button"
                    className="absolute right-1 top-1 grid h-8 w-8 place-items-center rounded-full bg-black/60 text-white"
                    onClick={() => setPhotos((x) => x.filter((y) => y.key !== p.key))}
                    aria-label={t("rw.removePhoto")}
                  >
                    <Icon name="x" size={14} />
                  </button>
                </div>
              ))}
              {photos.length < 6 && (
                <label htmlFor={fileId} className="ff-drop cursor-pointer">
                  <span className="flex flex-col items-center gap-1 text-xs font-semibold">
                    <Icon name="plus" size={20} /> {t("rw.addPhoto")}
                  </span>
                  <input
                    ref={fileRef}
                    id={fileId}
                    type="file"
                    accept="image/*"
                    capture="environment"
                    multiple
                    className="sr-only"
                    onChange={(e) => addFiles(e.target.files)}
                  />
                </label>
              )}
            </div>
            <p className="mt-1 text-[11px] text-ink-3">{t("rw.photoHint")}</p>
          </div>
        </div>
      )}

      {step === 2 && (
        <fieldset className="space-y-2 ff-fade" key="s2">
          <legend className="mb-2 text-base font-bold">{t("rw.what")}</legend>
          {resolutions.map((r) => (
            <label key={r} className="ff-option">
              <input type="radio" name="resolution" className="h-5 w-5 accent-[var(--accent)]" checked={resolution === r} onChange={() => setResolution(r)} />
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold">{t(RESOLUTION_LABELS[r].label)}</span>
                <span className="block text-xs text-ink-2">{t(RESOLUTION_LABELS[r].detail)}</span>
              </span>
              {r !== "replacement" && <span className="text-sm font-bold tabular-nums">{bdt(value)}</span>}
            </label>
          ))}
          <div className="rounded-2xl bg-surface-2 p-3 text-xs text-ink-2">
            {t("rw.summary", { items: t("cart.items", { count: chosen.length }), reason: reason ? t(REASON_LABELS[reason]) : "", photos: t("rw.photos", { count: okPhotos.length }) })}
          </div>
        </fieldset>
      )}

      <div className="mt-5 flex gap-2">
        {step > 0 && (
          <button type="button" className="btn btn-ghost h-12 px-5" onClick={() => setStep(step - 1)} disabled={busy}>
            <Icon name="left" size={16} /> {t("common.back")}
          </button>
        )}
        {step < 2 ? (
          <button type="button" className="btn btn-primary h-12 flex-1" onClick={() => setStep(step + 1)} disabled={!canNext}>
            {t("common.continue")} <Icon name="arrow" size={16} className="arrow" />
          </button>
        ) : (
          <button type="button" className="btn btn-primary h-12 flex-1" onClick={submit} disabled={!canNext || busy}>
            {busy ? t("common.sending") : t("rw.request")}
          </button>
        )}
      </div>
    </>,
  );
}
