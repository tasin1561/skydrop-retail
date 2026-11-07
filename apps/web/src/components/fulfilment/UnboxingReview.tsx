"use client";

import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { useFeature } from "@/components/features/FeaturesProvider";
import { EmptyState, LoadingBlock, PageHead } from "@/components/account/ui";
import { SignedOut } from "@/components/account/SignedOut";
import { ApiError } from "@/lib/apiClient";
import { REASON_LABELS, apiEnabled, fulfilmentApi, type ProblemReason, type Unboxing } from "@/lib/fulfilment";
import { useStore } from "@/state/store";
import "./fulfilment.css";
import { useT } from "@/i18n/client";

type Media = { src: string; kind: "image" | "video"; title: string; itemId: number };

export function UnboxingReview({ id }: { id: string }) {
  const on = useFeature("unboxing");
  const { ready, user } = useStore();
  const toast = useToast();
  const { t, dateTime } = useT();
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

  const back = { href: `/orders/${id}`, label: t("rw.order") };
  if (!on)
    return (
      <Shell>
        <PageHead title={t("ur.title")} icon="package" back={back} />
        <EmptyState title={t("ur.paused")} body={t("ur.pausedB")} action={{ label: t("rw.backOrder"), href: `/orders/${id}` }} />
      </Shell>
    );
  if (!apiEnabled)
    return (
      <Shell>
        <PageHead title={t("ur.title")} icon="package" back={back} />
        <EmptyState
          title={t("ur.demoT")}
          body={t("ur.demoB")}
          action={{ label: t("rw.backOrder"), href: `/orders/${id}` }}
        />
      </Shell>
    );
  if (ready && !user)
    return (
      <Shell>
        <SignedOut next={`/orders/${id}/unboxing`} title={t("od.signIn")} body={t("od.signInB")} />
      </Shell>
    );
  if (error)
    return (
      <Shell>
        <PageHead title={t("ur.title")} icon="package" back={back} />
        <EmptyState title={t("ur.loadFail")} body={error} action={{ label: t("rw.backOrder"), href: `/orders/${id}` }} />
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
      toast({ kind: "success", title: t("ur.approved"), body: t("ur.approvedB") });
    } catch (e) {
      toast({ kind: "error", title: t("ur.approveFail"), body: e instanceof ApiError ? e.message : t("common.pleaseRetry") });
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
      toast({ kind: "info", title: t("ur.reported"), body: t("ur.reportedB") });
    } catch (e) {
      toast({ kind: "error", title: t("ur.sendFail"), body: e instanceof ApiError ? e.message : t("common.pleaseRetry") });
    } finally {
      setBusy(false);
    }
  }

  return (
    <Shell>
      <PageHead title={t("ur.title")} sub={t("ur.sub", { id })} icon="package" back={back} />

      <section className="card rise p-4 sm:p-5" aria-labelledby="ub-h">
        <h2 id="ub-h" className="text-lg font-extrabold tracking-tight">
          {awaiting ? t("ur.opened") : data.status === "problem" ? t("ur.reported") : data.status ? t("ur.approved") : t("ur.notYet")}
        </h2>
        <p className="mt-1 text-sm text-ink-2">
          {awaiting
            ? t("ur.check") + (deadline ? t("ur.auto", { when: dateTime(deadline) }) : "")
            : data.status === "problem"
              ? t("ur.onIt")
              : data.status
                ? t("ur.onWay")
                : t("ur.whenArrive")}
        </p>

        {data.items.map((it) => (
          <div key={it.id} className="mt-4">
            <p className="text-sm font-semibold">
              {it.title}
              {it.variant ? <span className="font-normal text-ink-2"> · {it.variant}</span> : null}
              {it.condition && it.condition !== "ok" && (
                <span className="ac-chip ml-2" data-tone="bad">
                  {it.condition === "damaged" ? t("rr.damaged") : t("rr.wrong_item")}
                </span>
              )}
            </p>
            {it.note && <p className="text-xs text-ink-2">{t("ur.hubNote", { note: it.note })}</p>}
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
                      aria-label={t("ur.openMedia", { kind: t(m.kind === "video" ? "ur.video" : "ur.photo"), n: k + 1, title: it.title })}
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
            <Icon name="check" size={18} /> {t("ur.good")}
          </button>
          <button type="button" className="btn btn-ghost h-12" onClick={() => setMode("problem")} disabled={busy}>
            <Icon name="info" size={18} /> {t("ur.report")}
          </button>
        </div>
      )}

      {awaiting && mode === "problem" && (
        <section className="card rise mt-4 p-4 sm:p-5" aria-labelledby="pr-h">
          <h2 id="pr-h" className="text-base font-bold">
            {t("ur.wrong")}
          </h2>
          <div className="mt-3 flex flex-wrap gap-2" role="radiogroup" aria-label={t("ur.problem")}>
            {data.reasons.map((r) => (
              <button key={r} type="button" role="radio" aria-checked={reason === r} className="ff-chip" onClick={() => setReason(r)}>
                {t(REASON_LABELS[r])}
              </button>
            ))}
          </div>
          <label className="mt-3 block text-sm font-semibold" htmlFor="pr-note">
            {t("ur.more")} <span className="font-normal text-ink-3">{t("rw.optional")}</span>
          </label>
          <textarea
            id="pr-note"
            className="mt-1 w-full rounded-2xl bg-surface-2 p-3 text-sm outline-none focus:ring-2 focus:ring-accent"
            rows={3}
            maxLength={1000}
            value={note}
            onChange={(e) => setNote(e.target.value)}
            placeholder={t("ur.notePh")}
          />
          <div className="mt-3 flex flex-wrap gap-2">
            <button type="button" className="btn btn-primary h-12 px-5" disabled={!reason || busy} onClick={report}>
              {t("ur.send")}
            </button>
            <button type="button" className="btn btn-ghost h-12 px-5" onClick={() => setMode("view")} disabled={busy}>
              {t("common.back")}
            </button>
          </div>
        </section>
      )}

      {!awaiting && (
        <Link href={`/orders/${id}`} className="btn btn-ghost mt-4 h-12 w-full">
          {t("rw.backOrder")}
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
  const { t } = useT();
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
    <div className="ff-lightbox" role="dialog" aria-modal="true" aria-label={t("ur.lbAria", { title: m.title, n: index + 1, total: media.length })} onClick={onClose}>
      <div onClick={(e) => e.stopPropagation()} key={m.src}>
        {m.kind === "image" ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={m.src} alt={t("ur.photoAlt", { title: m.title, n: index + 1 })} />
        ) : (
          <video src={m.src} controls playsInline autoPlay muted />
        )}
        <p className="mt-2 text-center text-sm text-white/80">
          {index + 1} / {media.length}
        </p>
      </div>
      <button ref={closeRef} type="button" className="ff-lb-btn" style={{ top: 16, right: 16 }} onClick={onClose} aria-label={t("common.close")}>
        <Icon name="x" size={22} />
      </button>
      {media.length > 1 && (
        <>
          <button type="button" className="ff-lb-btn" style={{ left: 12, top: "50%" }} onClick={(e) => (e.stopPropagation(), go(-1))} aria-label={t("common.previous")}>
            <Icon name="left" size={22} />
          </button>
          <button type="button" className="ff-lb-btn" style={{ right: 12, top: "50%" }} onClick={(e) => (e.stopPropagation(), go(1))} aria-label={t("common.next")}>
            <Icon name="right" size={22} />
          </button>
        </>
      )}
    </div>
  );
}
