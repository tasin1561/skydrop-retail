"use client";

import Link from "next/link";
import { useEffect, useId, useRef, useState } from "react";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { useFeature } from "@/components/features/FeaturesProvider";
import { EmptyState, LoadingBlock, PageHead } from "@/components/account/ui";
import { ApiError } from "@/lib/apiClient";
import { formatBdt } from "@/lib/pricing";
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

type Photo = { key: string; preview: string; id?: number; state: "uploading" | "done" | "error"; error?: string };
const STEPS = ["Items", "Reason", "Resolution"];

/** /orders/[id]/return: item select → reason chips + photos → refund / replacement / store credit. */
export function ReturnWizard({ id }: { id: string }) {
  const on = useFeature("returns");
  const walletOn = useFeature("wallet");
  const { ready, user, orders } = useStore();
  const toast = useToast();
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

  const back = { href: `/orders/${id}`, label: "Order" };
  const shell = (children: React.ReactNode) => <div className="mx-auto max-w-2xl px-4 pb-24 pt-6 md:pb-12">{children}</div>;

  if (!on) return shell(<><PageHead title="Return an item" icon="refresh" back={back} /><EmptyState title="Returns are paused" body="Contact support if something is wrong with your order." action={{ label: "Back to your order", href: `/orders/${id}` }} /></>);
  if (error) return shell(<><PageHead title="Return an item" icon="refresh" back={back} /><EmptyState title="We couldn't load this order" body={error} action={{ label: "Back to your order", href: `/orders/${id}` }} /></>);
  if (!elig) return shell(<LoadingBlock rows={3} />);
  if (done)
    return shell(
      <>
        <PageHead title="Return requested" icon="refresh" back={back} />
        <div className="card rise p-5 text-center">
          <span className="chip-icon is-active mx-auto mb-3 ff-pop">
            <Icon name="check" size={20} />
          </span>
          <p className="text-lg font-extrabold">We&apos;ve got your request</p>
          <p className="mt-1 text-sm text-ink-2">Our team replies within one working day. We&apos;ll text you when the pickup is booked.</p>
        </div>
        <div className="mt-4">
          <ReturnTimeline c={done} />
        </div>
        <Link href={`/orders/${id}`} className="btn btn-primary mt-4 h-12 w-full">
          Back to your order
        </Link>
      </>,
    );
  if (!elig.eligible) {
    const why = {
      not_delivered: "Returns open once your order is delivered.",
      window_closed: `The ${elig.windowDays}-day return window for this order has closed.`,
      already_requested: "Every item of this order is already in a return.",
      disabled: "Returns are paused right now.",
    }[elig.reason ?? "disabled"];
    return shell(<><PageHead title="Return an item" icon="refresh" back={back} /><EmptyState title="This order can't be returned" body={why} action={{ label: "Back to your order", href: `/orders/${id}` }} /></>);
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
        toast({ kind: "error", title: "Photos only", body: `${f.name} is not an image.` });
        URL.revokeObjectURL(preview);
        continue;
      }
      if (f.size > MAX_UPLOAD_BYTES) {
        toast({ kind: "error", title: "Photo too large", body: `${f.name} is over 5 MB.` });
        URL.revokeObjectURL(preview);
        continue;
      }
      setPhotos((p) => [...p, { key, preview, state: apiEnabled ? "uploading" : "done" }]);
      if (!apiEnabled) continue;
      try {
        const up = await fulfilmentApi.upload(f);
        setPhotos((p) => p.map((x) => (x.key === key ? { ...x, id: up.id, state: "done" } : x)));
      } catch (e) {
        setPhotos((p) => p.map((x) => (x.key === key ? { ...x, state: "error", error: e instanceof ApiError ? e.message : "Upload failed" } : x)));
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
            id: 1, orderId: id, stage: "delivered", items: body.items, reason, reasonLabel: REASON_LABELS[reason], note: body.note ?? null,
            photos: [], resolution, status: "requested", statusLabel: "Requested", refundBdt: value, replacementOrderId: null, pickupAt: null,
            createdAt: new Date().toISOString(), timeline: [{ status: "requested", at: new Date().toISOString(), note: "Demo: nothing was sent" }],
          } satisfies ReturnCase);
      setDone(r);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (e) {
      toast({ kind: "error", title: "Couldn't send your request", body: e instanceof ApiError ? e.message : "Please try again." });
    } finally {
      setBusy(false);
    }
  }

  return shell(
    <>
      <PageHead title="Return an item" sub={`Order ${id} · ${STEPS[step]}`} icon="refresh" back={back} />
      <div className="ff-steps mb-4" aria-hidden>
        {STEPS.map((s, i) => (
          <span key={s} data-on={i <= step} />
        ))}
      </div>
      <p className="sr-only" aria-live="polite">
        Step {step + 1} of {STEPS.length}: {STEPS[step]}
      </p>

      {step === 0 && (
        <fieldset className="space-y-2 ff-fade" key="s0">
          <legend className="mb-2 text-base font-bold">Which items?</legend>
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
                    {disabled ? "Already in a return" : `${formatBdt(it.unitBdt)} each`}
                  </span>
                </span>
                {n > 0 && it.returnableQty > 1 && (
                  <select
                    aria-label={`Quantity of ${it.title} to return`}
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
              What&apos;s the problem?
            </p>
            <div className="flex flex-wrap gap-2" role="radiogroup" aria-labelledby="why-h">
              {elig.reasons.map((r) => (
                <button key={r} type="button" role="radio" aria-checked={reason === r} className="ff-chip" onClick={() => setReason(r)}>
                  {REASON_LABELS[r]}
                </button>
              ))}
            </div>
          </div>
          <div>
            <label htmlFor="rt-note" className="text-sm font-semibold">
              Details <span className="font-normal text-ink-3">(optional)</span>
            </label>
            <textarea
              id="rt-note"
              rows={3}
              maxLength={1000}
              value={note}
              onChange={(e) => setNote(e.target.value)}
              className="mt-1 w-full rounded-2xl bg-surface-2 p-3 text-sm outline-none focus:ring-2 focus:ring-accent"
              placeholder="What happened? e.g. the box was crushed and the screen is cracked"
            />
          </div>
          <div>
            <p className="text-sm font-semibold">
              Photos {needPhoto ? <span className="text-bad">(at least one)</span> : <span className="font-normal text-ink-3">(optional)</span>}
            </p>
            <div className="ff-gallery mt-2">
              {photos.map((p) => (
                <div key={p.key} className="ff-thumb ff-pop">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={p.preview} alt="Your photo" style={{ opacity: p.state === "uploading" ? 0.5 : 1 }} />
                  {p.state === "uploading" && <span className="absolute inset-0 grid place-items-center text-xs font-bold">Uploading…</span>}
                  {p.state === "error" && (
                    <span className="absolute inset-x-0 bottom-0 bg-bad px-1 py-0.5 text-[10px] font-bold text-white" title={p.error}>
                      Failed
                    </span>
                  )}
                  <button
                    type="button"
                    className="absolute right-1 top-1 grid h-8 w-8 place-items-center rounded-full bg-black/60 text-white"
                    onClick={() => setPhotos((x) => x.filter((y) => y.key !== p.key))}
                    aria-label="Remove photo"
                  >
                    <Icon name="x" size={14} />
                  </button>
                </div>
              ))}
              {photos.length < 6 && (
                <label htmlFor={fileId} className="ff-drop cursor-pointer">
                  <span className="flex flex-col items-center gap-1 text-xs font-semibold">
                    <Icon name="plus" size={20} /> Add photo
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
            <p className="mt-1 text-[11px] text-ink-3">JPG, PNG or HEIC, up to 5 MB each. Only our support team can see them.</p>
          </div>
        </div>
      )}

      {step === 2 && (
        <fieldset className="space-y-2 ff-fade" key="s2">
          <legend className="mb-2 text-base font-bold">What would you like?</legend>
          {resolutions.map((r) => (
            <label key={r} className="ff-option">
              <input type="radio" name="resolution" className="h-5 w-5 accent-[var(--accent)]" checked={resolution === r} onChange={() => setResolution(r)} />
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold">{RESOLUTION_LABELS[r].label}</span>
                <span className="block text-xs text-ink-2">{RESOLUTION_LABELS[r].detail}</span>
              </span>
              {r !== "replacement" && <span className="text-sm font-bold tabular-nums">{formatBdt(value)}</span>}
            </label>
          ))}
          <div className="rounded-2xl bg-surface-2 p-3 text-xs text-ink-2">
            {chosen.length} item{chosen.length === 1 ? "" : "s"} · {reason ? REASON_LABELS[reason] : ""} · {okPhotos.length} photo{okPhotos.length === 1 ? "" : "s"}. A Skydrop rider
            picks it up from your address; keep the item and its box.
          </div>
        </fieldset>
      )}

      <div className="mt-5 flex gap-2">
        {step > 0 && (
          <button type="button" className="btn btn-ghost h-12 px-5" onClick={() => setStep(step - 1)} disabled={busy}>
            <Icon name="left" size={16} /> Back
          </button>
        )}
        {step < 2 ? (
          <button type="button" className="btn btn-primary h-12 flex-1" onClick={() => setStep(step + 1)} disabled={!canNext}>
            Continue <Icon name="arrow" size={16} className="arrow" />
          </button>
        ) : (
          <button type="button" className="btn btn-primary h-12 flex-1" onClick={submit} disabled={!canNext || busy}>
            {busy ? "Sending…" : "Request return"}
          </button>
        )}
      </div>
    </>,
  );
}
