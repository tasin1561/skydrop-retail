"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Icon } from "@/components/Icon";
import { STORES } from "@/lib/markets";
import type { MarketId } from "@/lib/types";
import { useJob } from "./useJob";
import { WaitStory } from "./WaitStory";
import type { ReactNode } from "react";

const STEPS = [
  { at: 0, label: "Connecting to the store in India" },
  { at: 4, label: "Reading the live price and offers" },
  { at: 9, label: "Loading photos, videos and variants" },
  { at: 15, label: "Converting to BDT with duty and delivery" },
];

/**
 * Shown when a product isn't in our database yet and a live fetch is running. The shopper sees a
 * product-shaped skeleton with honest progress, and the page swaps in automatically when ready.
 */
export function LiveFetch({ jobId, market, productId, browse }: { jobId: string; market?: string; productId: string; browse?: ReactNode }) {
  const router = useRouter();
  const [failed, setFailed] = useState(false);
  const { elapsed } = useJob(jobId, {
    maxMs: 75_000,
    onDone: (s) => (s === "done" ? router.refresh() : setFailed(true)),
  });
  const store = market && market in STORES ? STORES[market as MarketId] : null;
  const source = store?.source ?? "the store";
  const step = STEPS.filter((s) => elapsed >= s.at).length - 1;
  const slow = elapsed >= 25;

  if (failed) {
    return (
      <section role="alert" className="fade-in mx-auto max-w-md px-4 py-16 text-center">
        <span className="chip-icon mx-auto h-14 w-14 rounded-2xl">
          <Icon name="search" size={26} />
        </span>
        <h1 className="mt-4 text-2xl font-extrabold tracking-tight">We couldn&apos;t reach {source} right now</h1>
        <p className="mt-2 text-sm text-ink-2">The store didn&apos;t answer in time. This is usually temporary.</p>
        <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
          <button type="button" className="btn btn-primary" onClick={() => window.location.reload()}>
            <Icon name="refresh" size={18} /> Try again
          </button>
          <Link href={`/request?url=${encodeURIComponent(productId)}`} className="btn btn-ghost">
            Ask us to source it
          </Link>
        </div>
      </section>
    );
  }

  return (
    <div className="mx-auto max-w-7xl px-4 pb-24 pt-4" aria-busy="true">
      <div role="status" aria-live="polite" className="card mb-4 flex items-center gap-3 p-4">
        <span className="live-dot" aria-hidden="true" />
        <div className="min-w-0 flex-1">
          <p className="text-sm font-bold">Fetching this product live from {source}</p>
          <p className="text-xs text-ink-2">
            {STEPS[Math.max(0, step)].label}
            {slow ? " · the store is slow today, still working on it" : ""} · {elapsed}s
          </p>
        </div>
      </div>
      <ol className="mb-5 grid grid-cols-4 gap-1.5" aria-hidden="true">
        {STEPS.map((s, i) => (
          <li key={s.label} className="h-1.5 overflow-hidden rounded-full bg-surface-2">
            <span
              className="block h-full origin-left rounded-full bg-accent transition-transform duration-700"
              style={{ transform: `scaleX(${i < step ? 1 : i === step ? 0.6 : 0})` }}
            />
          </li>
        ))}
      </ol>
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className="flex flex-col gap-4">
          <WaitStory />
          <div className="skeleton hidden aspect-[4/3] w-full rounded-[20px] lg:block" />
        </div>
        <div className="flex flex-col gap-3">
          <div className="skeleton h-5 w-28 rounded-full" />
          <div className="skeleton h-8 w-full rounded-xl" />
          <div className="skeleton h-8 w-3/4 rounded-xl" />
          <div className="skeleton mt-2 h-10 w-40 rounded-xl" />
          <div className="skeleton mt-2 h-24 w-full rounded-2xl" />
          <div className="skeleton h-12 w-full rounded-2xl" />
        </div>
      </div>
      {browse && <div className="mt-8">{browse}</div>}
    </div>
  );
}
