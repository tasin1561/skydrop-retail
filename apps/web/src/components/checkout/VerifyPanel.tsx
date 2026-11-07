"use client";

import { Icon } from "@/components/Icon";
import { formatBdt } from "@/lib/pricing";
import type { CartItem } from "@/lib/types";

export type CheckState = "wait" | "run" | "ok" | "warn" | "fail";
export type VerifyResult = { productId: string; ok: boolean; freshBdt?: number; changed?: boolean; deliverable?: boolean; reason?: string };

export const CHECKS = [
  { id: "price", run: "Checking live price…", ok: "Live price confirmed", warn: "Some prices changed", fail: "Couldn't confirm price" },
  { id: "stock", run: "Checking stock…", ok: "In stock in India", warn: "In stock in India", fail: "Some items unavailable" },
  { id: "hub", run: "Checking delivery to Kolkata hub…", ok: "Delivers to Skydrop's Kolkata hub", warn: "Delivers to Skydrop's Kolkata hub", fail: "Can't deliver to the Kolkata hub" },
] as const;

export function VerifyPanel({
  states,
  error,
  changed,
  failed,
  onRetry,
}: {
  states: CheckState[];
  error: string | null;
  changed: { item: CartItem; fresh: number }[];
  failed: CartItem[];
  onRetry: () => void;
}) {
  const word = (s: CheckState) => ({ wait: "Waiting", run: "Checking", ok: "Done", warn: "Changed", fail: "Failed" })[s];
  return (
    <section className="co-card fade-in p-4" aria-labelledby="vp-h">
      <h3 id="vp-h" className="flex items-center gap-2 text-sm font-extrabold">
        <Icon name="shield" size={17} className="text-accent" /> Live verification with the Indian stores
      </h3>
      <ul className="mt-3 space-y-2.5" aria-live="polite" aria-relevant="text">
        {CHECKS.map((c, i) => {
          const s = states[i];
          return (
            <li key={c.id} className="co-check flex items-center gap-3 text-sm" data-state={s}>
              <span className="co-check-dot">
                {s === "run" && <span className="co-spin" />}
                {s === "ok" && (
                  <svg viewBox="0 0 24 24" width="15" height="15" className="draw-check" aria-hidden>
                    <path d="M5 12l5 5 9-11" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                )}
                {s === "warn" && <Icon name="info" size={15} stroke={2.4} />}
                {s === "fail" && <Icon name="x" size={15} stroke={2.6} />}
                {s === "wait" && <span className="h-1.5 w-1.5 rounded-full bg-current" />}
              </span>
              <span className={"flex-1 " + (s === "wait" ? "text-ink-3" : s === "fail" ? "text-bad" : "text-ink")}>
                {s === "wait" || s === "run" ? c.run : s === "ok" ? c.ok : s === "warn" ? c.warn : c.fail}
              </span>
              <span className="sr-only">{word(s)}</span>
            </li>
          );
        })}
      </ul>

      {error && (
        <div role="alert" className="mt-3 flex items-center gap-3 rounded-2xl bg-bad/10 p-3 text-sm text-bad">
          <Icon name="info" size={18} className="shrink-0" />
          <span className="flex-1">{error}</span>
          <button type="button" onClick={onRetry} className="btn btn-ghost min-h-11 px-3 text-xs">
            <Icon name="refresh" size={15} /> Retry
          </button>
        </div>
      )}

      {failed.length > 0 && (
        <div role="alert" className="mt-3 rounded-2xl bg-bad/10 p-3 text-sm">
          <p className="font-bold text-bad">These items can&apos;t be ordered right now:</p>
          <ul className="mt-1 list-disc pl-5 text-xs text-ink-2">
            {failed.map((f) => (
              <li key={f.productId + (f.variant ?? "")}>{f.title}</li>
            ))}
          </ul>
          <a href="/cart" className="mt-2 inline-block text-xs font-bold text-accent">
            Edit cart
          </a>
        </div>
      )}

      {changed.length > 0 && (
        <div className="pop mt-3 rounded-2xl bg-warn/10 p-3" role="alert">
          <p className="flex items-center gap-2 text-sm font-bold text-warn">
            <Icon name="tag" size={16} /> The store price changed for {changed.length} item{changed.length === 1 ? "" : "s"}
          </p>
          <ul className="mt-2 space-y-1.5">
            {changed.map(({ item, fresh }) => (
              <li key={item.productId + (item.variant ?? "")} className="flex items-center gap-2 text-xs">
                <span className="line-clamp-1 flex-1 text-ink-2">{item.title}</span>
                <span className="text-ink-3 line-through tabular-nums">{formatBdt(item.unitBdt)}</span>
                <Icon name="arrow" size={12} className="text-ink-3" />
                <span className={"font-bold tabular-nums " + (fresh > item.unitBdt ? "text-bad" : "text-good")}>
                  {formatBdt(fresh)} {fresh > item.unitBdt ? "(up)" : "(down)"}
                </span>
              </li>
            ))}
          </ul>
          <p className="mt-2 text-xs text-ink-2">Your total below now uses the new prices. Confirm to place the order at these prices.</p>
        </div>
      )}
    </section>
  );
}
