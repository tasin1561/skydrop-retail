"use client";

import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { useFeature } from "@/components/features/FeaturesProvider";
import { EmptyState, LoadingBlock, PageHead } from "@/components/account/ui";
import { ApiError } from "@/lib/apiClient";
import { REASON_LABELS, apiEnabled, fulfilmentApi, type ProblemReason, type Unboxing } from "@/lib/fulfilment";
import { useStore } from "@/state/store";
import "./fulfilment.css";

type Media = { src: string; kind: "image" | "video"; title: string; itemId: number };

export function UnboxingReview({ id }: { id: string }) {
  const on = useFeature("unboxing");
  const { ready, user } = useStore();
  const toast = useToast();
  const [data, setData] = useState<Unboxing | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [open, setOpen] = useState<number | null>(null);
  const [mode, setMode] = useState<"view" | "problem">("view");
  const [reason, setReason] = useState<ProblemReason | null>(null);
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!ready || !apiEnabled || !user || !on) return;
    fulfilmentApi
      .unboxing(id)
      .then(setData)
      .catch((e: Error) => setError(e.message));
  }, [ready, user, id, on]);

  const media: Media[] = (data?.items ?? []).flatMap((it) => [
    ...it.photos.map((src) => ({ src, kind: "image" as const, title: it.title, itemId: it.id })),
    ...(it.video ? [{ src: it.video, kind: "video" as const, title: it.title, itemId: it.id }] : []),
  ]);

  const back = { href: `/orders/${id}`, label: "Order" };
  if (!on)
    return (
      <Shell>
        <PageHead title="Unboxing check" icon="package" back={back} />
        <EmptyState title="Unboxing checks are paused" body="Your order ships as usual." action={{ label: "Back to your order", href: `/orders/${id}` }} />
      </Shell>
    );
  if (!apiEnabled)
    return (
      <Shell>
        <PageHead title="Unboxing check" icon="package" back={back} />
        <EmptyState
          title="Photos appear here after the hub opens your parcel"
          body="In this demo there is no Kolkata hub. With a live account you would see the photos, then approve or report a problem."
          action={{ label: "Back to your order", href: `/orders/${id}` }}
        />
      </Shell>
    );
  if (error)
    return (
      <Shell>
        <PageHead title="Unboxing check" icon="package" back={back} />
        <EmptyState title="We couldn't load the unboxing" body={error} action={{ label: "Back to your order", href: `/orders/${id}` }} />
      </Shell>
    );
  if (!data)
    return (
      <Shell>
        <LoadingBlock rows={3} />
      </Shell>
    );

  const awaiting = data.status === "awaiting_customer_approval";
  const deadline = data.autoApproveAt ? new Date(data.autoApproveAt) : null;

  async function approve() {
    setBusy(true);
    try {
      setData(await fulfilmentApi.approveUnboxing(id));
      toast({ kind: "success", title: "Approved", body: "Your parcel leaves Kolkata for Bangladesh next." });
    } catch (e) {
      toast({ kind: "error", title: "Couldn't approve", body: e instanceof ApiError ? e.message : "Please try again." });
    } finally {
      setBusy(false);
    }
  }
  async function report() {
    if (!reason) return;
    setBusy(true);
    try {
      const r = await fulfilmentApi.reportUnboxing(id, reason, note.trim());
      setData(r.unboxing);
      setMode("view");
      toast({ kind: "info", title: "Problem reported", body: "We'll contact you about a replacement or refund. The parcel stays in Kolkata." });
    } catch (e) {
      toast({ kind: "error", title: "Couldn't send", body: e instanceof ApiError ? e.message : "Please try again." });
    } finally {
      setBusy(false);
    }
  }

  return (
    <Shell>
      <PageHead title="Unboxing check" sub={`Order ${id}`} icon="package" back={back} />

      <section className="card rise p-4 sm:p-5" aria-labelledby="ub-h">
        <h2 id="ub-h" className="text-lg font-extrabold tracking-tight">
          {awaiting ? "We opened your parcel at our Kolkata hub" : data.status === "problem" ? "Problem reported" : data.status ? "Approved" : "Not unboxed yet"}
        </h2>
        <p className="mt-1 text-sm text-ink-2">
          {awaiting
            ? `Check every photo. Approve to ship it to Bangladesh, or report a problem and we fix it before it leaves India.${deadline ? ` If we don't hear from you, it ships automatically on ${deadline.toLocaleString("en-GB", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" })}.` : ""}`
            : data.status === "problem"
              ? "Our team is on it. Follow the case on your order page."
              : data.status
                ? "Your parcel is on its way to Bangladesh."
                : "Photos appear here once every item arrives at the hub."}
        </p>

        {data.items.map((it) => (
          <div key={it.id} className="mt-4">
            <p className="text-sm font-semibold">
              {it.title}
              {it.variant ? <span className="font-normal text-ink-2"> · {it.variant}</span> : null}
              {it.condition && it.condition !== "ok" && (
                <span className="ac-chip ml-2" data-tone="bad">
                  {it.condition === "damaged" ? "Damaged" : "Wrong item"}
                </span>
              )}
            </p>
            {it.note && <p className="text-xs text-ink-2">Hub note: {it.note}</p>}
            {(it.photos.length > 0 || it.video) && (
              <div className="ff-gallery mt-2">
                {media
                  .map((m, idx) => ({ m, idx }))
                  .filter(({ m }) => m.itemId === it.id)
                  .map(({ m, idx }, k) => (
                    <button
                      key={m.src}
                      type="button"
                      className="ff-thumb ff-pop"
                      style={{ ["--d" as string]: `${k * 0.05}s` }}
                      onClick={() => setOpen(idx)}
                      aria-label={`Open ${m.kind === "video" ? "video" : "photo"} ${k + 1} of ${it.title}`}
                    >
                      {m.kind === "image" ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={m.src} alt="" loading="lazy" />
                      ) : (
                        <span className="grid h-full w-full place-items-center bg-ink text-white">
                          <Icon name="play" size={24} />
                        </span>
                      )}
                    </button>
                  ))}
              </div>
            )}
          </div>
        ))}
      </section>

      {awaiting && mode === "view" && (
        <div className="mt-4 grid gap-2 sm:grid-cols-2">
          <button type="button" className="btn btn-primary h-12" onClick={approve} disabled={busy}>
            <Icon name="check" size={18} /> Looks good, ship it
          </button>
          <button type="button" className="btn btn-ghost h-12" onClick={() => setMode("problem")} disabled={busy}>
            <Icon name="info" size={18} /> Report a problem
          </button>
        </div>
      )}

      {awaiting && mode === "problem" && (
        <section className="card rise mt-4 p-4 sm:p-5" aria-labelledby="pr-h">
          <h2 id="pr-h" className="text-base font-bold">
            What&apos;s wrong?
          </h2>
          <div className="mt-3 flex flex-wrap gap-2" role="radiogroup" aria-label="Problem">
            {data.reasons.map((r) => (
              <button key={r} type="button" role="radio" aria-checked={reason === r} className="ff-chip" onClick={() => setReason(r)}>
                {REASON_LABELS[r]}
              </button>
            ))}
          </div>
          <label className="mt-3 block text-sm font-semibold" htmlFor="pr-note">
            Tell us more <span className="font-normal text-ink-3">(optional)</span>
          </label>
          <textarea
            id="pr-note"
            className="mt-1 w-full rounded-2xl bg-surface-2 p-3 text-sm outline-none focus:ring-2 focus:ring-accent"
            rows={3}
            maxLength={1000}
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder="e.g. The colour is blue, I ordered black"
          />
          <div className="mt-3 flex flex-wrap gap-2">
            <button type="button" className="btn btn-primary h-12 px-5" disabled={!reason || busy} onClick={report}>
              Send to our team
            </button>
            <button type="button" className="btn btn-ghost h-12 px-5" onClick={() => setMode("view")} disabled={busy}>
              Back
            </button>
          </div>
        </section>
      )}

      {!awaiting && (
        <Link href={`/orders/${id}`} className="btn btn-ghost mt-4 h-12 w-full">
          Back to your order
        </Link>
      )}

      {open !== null && media[open] && <Lightbox media={media} index={open} onIndex={setOpen} onClose={() => setOpen(null)} />}
    </Shell>
  );
}

function Shell({ children }: { children: React.ReactNode }) {
  return <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">{children}</div>;
}

function Lightbox({ media, index, onIndex, onClose }: { media: Media[]; index: number; onIndex: (i: number) => void; onClose: () => void }) {
  const closeRef = useRef<HTMLButtonElement>(null);
  const m = media[index];
  const go = useCallback((d: number) => onIndex((index + d + media.length) % media.length), [index, media.length, onIndex]);

  useEffect(() => {
    const prev = document.activeElement as HTMLElement | null;
    closeRef.current?.focus();
    const key = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
      if (e.key === "ArrowRight") go(1);
      if (e.key === "ArrowLeft") go(-1);
    };
    document.addEventListener("keydown", key);
    const overflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", key);
      document.body.style.overflow = overflow;
      prev?.focus();
    };
  }, [go, onClose]);

  return (
    <div className="ff-lightbox" role="dialog" aria-modal="true" aria-label={`${m.title}, ${index + 1} of ${media.length}`} onClick={onClose}>
      <div onClick={(e) => e.stopPropagation()} key={m.src}>
        {m.kind === "image" ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={m.src} alt={`${m.title}, photo ${index + 1}`} />
        ) : (
          <video src={m.src} controls playsInline autoPlay muted />
        )}
        <p className="mt-2 text-center text-sm text-white/80">
          {index + 1} / {media.length}
        </p>
      </div>
      <button ref={closeRef} type="button" className="ff-lb-btn" style={{ top: 16, right: 16 }} onClick={onClose} aria-label="Close">
        <Icon name="x" size={22} />
      </button>
      {media.length > 1 && (
        <>
          <button type="button" className="ff-lb-btn" style={{ left: 12, top: "50%" }} onClick={(e) => (e.stopPropagation(), go(-1))} aria-label="Previous">
            <Icon name="left" size={22} />
          </button>
          <button type="button" className="ff-lb-btn" style={{ right: 12, top: "50%" }} onClick={(e) => (e.stopPropagation(), go(1))} aria-label="Next">
            <Icon name="right" size={22} />
          </button>
        </>
      )}
    </div>
  );
}
