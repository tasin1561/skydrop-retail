"use client";

import "./cms.css";
import Link from "next/link";
import { useCallback, useEffect, useId, useState } from "react";
import { API_URL, apiEnabled } from "@/lib/apiClient";
import { useStore } from "@/state/store";
import { Icon } from "@/components/Icon";
import { Stars } from "@/components/ProductBits";
import { Sheet, Spinner } from "@/components/account/ui";
import { StarBars } from "@/components/product/Reviews";

type ApiReview = {
  id: string;
  author: string;
  rating: number;
  title: string;
  content: string;
  date: string | null;
  verifiedPurchase: boolean;
  status: "pending" | "approved" | "rejected";
  photoUrl?: string | null;
  helpful?: number;
};
type ApiReviews = { items: ApiReview[]; count: number; average: number | null; canReview: boolean; breakdown?: { rating: number; count: number }[] };

/**
 * Reviews from Skydrop shoppers (GET|POST /products/{id}/reviews). Kept visibly separate from the source store's
 * rating, which is shown as "4.3 on amazon.in". Writing needs a signed-in shopper who received the item.
 */
export function ShopperReviews({ productId, source, sourceRating, sourceCount }: { productId: string; source: string; sourceRating: number; sourceCount: number }) {
  const { token, user, ready } = useStore();
  const [data, setData] = useState<ApiReviews | null>(null);
  const [failed, setFailed] = useState(false);
  const [writing, setWriting] = useState(false);

  const load = useCallback(async () => {
    if (!apiEnabled) {
      setData({ items: [], count: 0, average: null, canReview: false });
      return;
    }
    try {
      const res = await fetch(`${API_URL}/products/${encodeURIComponent(productId)}/reviews`, {
        headers: { Accept: "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
        cache: "no-store",
      });
      if (!res.ok) throw new Error(String(res.status));
      setData((await res.json()) as ApiReviews);
      setFailed(false);
    } catch {
      setFailed(true);
    }
  }, [productId, token]);

  useEffect(() => {
    if (!ready) return;
    const t = setTimeout(load, 0);
    return () => clearTimeout(t);
  }, [ready, load]);

  const approved = data?.items.filter((r) => r.status === "approved") ?? [];
  const mine = data?.items.find((r) => r.status === "pending");
  const rows = (data?.breakdown ?? [5, 4, 3, 2, 1].map((n) => ({ rating: n, count: approved.filter((r) => r.rating === n).length }))).map((b) => ({
    rating: b.rating,
    percentage: data?.count ? Math.round((b.count / data.count) * 100) : 0,
  }));

  return (
    <section id="reviews" className="scroll-mt-24" aria-labelledby="sr-title">
      <div className="card p-4 sm:p-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <h2 id="sr-title" className="flex items-center gap-3 text-lg font-extrabold tracking-tight">
            <span className="chip-icon">
              <Icon name="verified" size={18} />
            </span>
            Skydrop shopper reviews
          </h2>
          {sourceRating > 0 && (
            <a
              href="#source-reviews"
              className="inline-flex min-h-11 items-center gap-1.5 rounded-full bg-surface-2 px-3 text-xs font-semibold text-ink-2 hover:text-accent"
              title={`${sourceCount.toLocaleString("en-IN")} ratings on ${source}`}
            >
              <Icon name="star" size={13} fill="currentColor" stroke={1} className="text-amber-500" />
              {sourceRating.toFixed(1)} on {source}
            </a>
          )}
        </div>

        {!data && !failed && (
          <div className="mt-4 flex items-center gap-2 text-sm text-ink-2" aria-busy="true">
            <Spinner /> Loading reviews…
          </div>
        )}
        {failed && (
          <p className="mt-4 text-sm text-ink-2">
            Reviews could not be loaded.{" "}
            <button type="button" onClick={load} className="min-h-11 font-semibold text-accent">
              Try again
            </button>
          </p>
        )}

        {data && (
          <>
            <div className="mt-4 flex flex-col gap-5 sm:flex-row sm:items-center">
              <div className="flex shrink-0 items-center gap-3 sm:flex-col sm:items-start sm:gap-1">
                <span className="text-4xl font-extrabold tracking-tight">{data.average ? data.average.toFixed(1) : "–"}</span>
                <div>
                  <Stars rating={data.average ?? 0} />
                  <p className="text-xs text-ink-2">
                    {data.count ? `${data.count} review${data.count === 1 ? "" : "s"} from Bangladesh` : "No reviews from Bangladesh yet"}
                  </p>
                </div>
              </div>
              <div className="min-w-0 flex-1">
                <StarBars rows={rows} />
              </div>
            </div>

            <WriteCta canReview={data.canReview} signedIn={!!user} pending={!!mine} onWrite={() => setWriting(true)} />
          </>
        )}
      </div>

      {data && (mine || approved.length > 0) && (
        <ul className="mt-3 flex flex-col gap-3">
          {[...(mine ? [mine] : []), ...approved].map((r, i) => (
            <li key={r.id}>
              <ReviewItem r={r} i={i} />
            </li>
          ))}
        </ul>
      )}

      {writing && (
        <WriteSheet
          productId={productId}
          token={token}
          initial={mine}
          onClose={() => setWriting(false)}
          onDone={() => {
            setWriting(false);
            load();
          }}
        />
      )}
    </section>
  );
}

function WriteCta({ canReview, signedIn, pending, onWrite }: { canReview: boolean; signedIn: boolean; pending: boolean; onWrite: () => void }) {
  if (canReview)
    return (
      <button type="button" onClick={onWrite} className="btn btn-primary mt-4 w-full sm:w-auto">
        <Icon name="star" size={17} /> {pending ? "Edit your review" : "Write a review"}
      </button>
    );
  return (
    <p className="mt-4 flex items-start gap-2 rounded-xl bg-surface-2 px-3 py-2 text-xs text-ink-2">
      <Icon name="info" size={15} className="mt-px shrink-0 text-accent" />
      {!apiEnabled ? (
        "Reviews from Skydrop shoppers appear here once the store is live."
      ) : signedIn ? (
        "You can review this item after it has been delivered to you."
      ) : (
        <span>
          Bought this through Skydrop?{" "}
          <Link href="/login" className="font-semibold text-accent">
            Sign in
          </Link>{" "}
          to review it after delivery.
        </span>
      )}
    </p>
  );
}

function ReviewItem({ r, i }: { r: ApiReview; i: number }) {
  const [open, setOpen] = useState(false);
  const long = r.content.length > 260;
  const date = r.date ? new Date(r.date).toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" }) : "";
  return (
    <article className="rise rounded-2xl bg-surface p-4 shadow-[var(--shadow-1)]" style={{ ["--i" as string]: i }}>
      <header className="flex items-center gap-3">
        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-[14px] bg-[var(--accent-soft)] text-sm font-bold text-accent">
          {r.author.slice(0, 1).toUpperCase() || "?"}
        </span>
        <div className="min-w-0 flex-1">
          <p className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm font-semibold">
            <span className="truncate">{r.author}</span>
            {r.verifiedPurchase && (
              <span className="inline-flex items-center gap-1 rounded-full bg-[color-mix(in_srgb,var(--good)_14%,transparent)] px-2 py-0.5 text-[10px] font-bold text-good">
                <Icon name="check" size={10} stroke={3} /> Verified purchase
              </span>
            )}
            {r.status === "pending" && (
              <span className="rounded-full bg-[color-mix(in_srgb,var(--warn)_16%,transparent)] px-2 py-0.5 text-[10px] font-bold text-warn">
                Waiting for approval · only you can see it
              </span>
            )}
          </p>
          <p className="text-[11px] text-ink-3">{date} · Skydrop shopper in Bangladesh</p>
        </div>
        <Stars rating={r.rating} size={12} />
      </header>
      {r.title && <h3 className="mt-3 text-sm font-bold">{r.title}</h3>}
      <p className={"mt-1 whitespace-pre-line text-sm leading-relaxed text-ink-2 " + (long && !open ? "line-clamp-4" : "")}>{r.content}</p>
      {long && (
        <button type="button" onClick={() => setOpen((o) => !o)} aria-expanded={open} className="mt-1 min-h-11 text-xs font-bold text-accent">
          {open ? "Show less" : "Read more"}
        </button>
      )}
      {r.photoUrl && (
        <a href={r.photoUrl} target="_blank" rel="noopener noreferrer nofollow" className="mt-3 block w-fit overflow-hidden rounded-xl ring-1 ring-line">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={r.photoUrl} alt={`Photo from ${r.author}`} loading="lazy" className="h-28 w-28 object-cover transition-transform duration-300 hover:scale-105" />
        </a>
      )}
      {typeof r.helpful === "number" && r.helpful > 0 && <p className="mt-2 text-[11px] text-ink-3">{r.helpful} people found this helpful</p>}
    </article>
  );
}

const STAR_WORDS = ["", "Poor", "Not great", "Okay", "Good", "Excellent"];

function WriteSheet({
  productId,
  token,
  initial,
  onClose,
  onDone,
}: {
  productId: string;
  token: string | null;
  initial?: ApiReview;
  onClose: () => void;
  onDone: () => void;
}) {
  const [rating, setRating] = useState(initial?.rating ?? 0);
  const [hover, setHover] = useState(0);
  const [title, setTitle] = useState(initial?.title ?? "");
  const [body, setBody] = useState(initial?.content ?? "");
  const [photo, setPhoto] = useState(initial?.photoUrl ?? "");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const id = useId();
  const shown = hover || rating;
  const photoOk = !photo || /^https:\/\/\S+$/i.test(photo);
  const valid = rating > 0 && body.trim().length >= 5 && photoOk;

  async function submit(close: () => void) {
    if (!valid || busy) return;
    setBusy(true);
    setError(null);
    try {
      const res = await fetch(`${API_URL}/products/${encodeURIComponent(productId)}/reviews`, {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
        body: JSON.stringify({ rating, title: title.trim() || undefined, body: body.trim(), photoUrl: photo.trim() || undefined }),
      });
      const data = (await res.json().catch(() => ({}))) as { message?: string; errors?: Record<string, string[]> };
      if (!res.ok) throw new Error(Object.values(data.errors ?? {})[0]?.[0] ?? data.message ?? "Could not send your review.");
      close();
      onDone();
    } catch (e) {
      setError(e instanceof Error ? e.message : "Could not send your review.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <Sheet title={initial ? "Edit your review" : "Write a review"} description="Reviews are checked by our team before they appear." icon="star" onClose={onClose}>
      {(close) => (
        <form
          onSubmit={(e) => {
            e.preventDefault();
            submit(close);
          }}
          className="flex flex-col gap-4"
          noValidate
        >
          <fieldset>
            <legend className="text-sm font-semibold">Your rating</legend>
            <div className="mt-1 flex items-center gap-1" onMouseLeave={() => setHover(0)}>
              {[1, 2, 3, 4, 5].map((n) => (
                <button
                  key={n}
                  type="button"
                  aria-label={`${n} star${n > 1 ? "s" : ""}: ${STAR_WORDS[n]}`}
                  aria-pressed={rating === n}
                  onMouseEnter={() => setHover(n)}
                  onClick={() => setRating(n)}
                  className="grid h-11 w-11 place-items-center rounded-full transition-transform active:scale-90"
                >
                  <Icon
                    key={n <= shown ? `on-${rating}` : "off"}
                    name="star"
                    size={28}
                    stroke={1.4}
                    fill={n <= shown ? "currentColor" : "none"}
                    className={(n <= shown ? "pop text-amber-500" : "text-ink-3") + ""}
                  />
                </button>
              ))}
              <span className="ml-2 text-sm font-semibold text-ink-2" aria-live="polite">
                {STAR_WORDS[shown]}
              </span>
            </div>
          </fieldset>

          <div className="field">
            <input id={id + "-t"} value={title} onChange={(e) => setTitle(e.target.value.slice(0, 120))} placeholder=" " autoComplete="off" />
            <label htmlFor={id + "-t"}>Headline (optional)</label>
          </div>
          <div className="field">
            <textarea id={id + "-b"} value={body} onChange={(e) => setBody(e.target.value.slice(0, 4000))} placeholder=" " rows={5} required />
            <label htmlFor={id + "-b"}>What did you like or dislike?</label>
          </div>
          <div className={"field " + (photoOk ? "" : "is-invalid")}>
            <input id={id + "-p"} value={photo} onChange={(e) => setPhoto(e.target.value.trim())} placeholder=" " inputMode="url" autoComplete="off" aria-invalid={!photoOk} />
            <label htmlFor={id + "-p"}>Photo link (optional, https://…)</label>
          </div>
          {!photoOk && <p className="-mt-2 pl-1 text-xs text-bad">Use a link that starts with https://</p>}
          {error && (
            <p role="alert" className="rounded-xl bg-[color-mix(in_srgb,var(--bad)_10%,transparent)] px-3 py-2 text-sm text-bad">
              {error}
            </p>
          )}
          <button type="submit" disabled={!valid || busy} className="btn btn-primary w-full">
            {busy ? <Spinner /> : <Icon name="check" size={17} />} {busy ? "Sending…" : "Submit review"}
          </button>
        </form>
      )}
    </Sheet>
  );
}
