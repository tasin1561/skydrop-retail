"use client";

import { useState } from "react";
import type { MarketId } from "@/lib/types";
import { useStore } from "@/state/store";
import { useToast } from "@/components/Toast";
import { Icon } from "@/components/Icon";
import { MARKET_IDS, STORES } from "@/lib/markets";
import { LoadingBlock, PageHead, useShake } from "./ui";
import { StoreName } from "@/components/store/StoreName";

export function MarketplacesAdmin() {
  const { ready, enabled, setEnabled } = useStore();
  const toast = useToast();
  const [blocked, setBlocked] = useState<MarketId | null>(null);
  const [live, setLive] = useState("");
  const { shakeClass, shake, onAnimationEnd } = useShake();
  const onCount = MARKET_IDS.filter((m) => enabled[m]).length;

  function toggle(m: MarketId) {
    const next = !enabled[m];
    if (!next && onCount <= 1) {
      setBlocked(m);
      shake();
      setLive(`${STORES[m].name} is the last live store. At least one store must stay on.`);
      return;
    }
    setBlocked(null);
    setEnabled(m, next);
    setLive(`${STORES[m].name} is now ${next ? "on, live for shoppers" : "off, hidden from shoppers"}`);
    toast({
      kind: next ? "success" : "info",
      title: `${STORES[m].name} ${next ? "is live" : "is hidden"}`,
      body: next ? `Products from ${STORES[m].source} appear again.` : `Hidden from the store switcher and search.`,
    });
  }

  return (
    <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
      <PageHead title="Marketplaces" sub="Turn Indian source stores on or off" icon="settings" />

      <div className="mb-4 flex items-start gap-3 rounded-2xl p-3 text-sm" style={{ background: "color-mix(in srgb, var(--warn) 10%, var(--surface))" }}>
        <span className="chip-icon shrink-0" style={{ background: "color-mix(in srgb, var(--warn) 18%, transparent)", color: "var(--warn)" }}>
          <Icon name="info" size={18} />
        </span>
        <p className="text-ink-2">
          <span className="font-semibold text-ink">Demo admin.</span> In production this lives in the Filament admin and applies to all shoppers.
          Here it only changes this browser.
        </p>
      </div>

      <div className="mb-3 flex items-center justify-between gap-2 px-1">
        <p className="text-sm font-semibold text-ink-2">Stores</p>
        <span className="ac-chip tabular-nums" data-tone={onCount === MARKET_IDS.length ? "good" : undefined}>
          {String(onCount).padStart(2, "0")}/{String(MARKET_IDS.length).padStart(2, "0")} live
        </span>
      </div>

      <p className="sr-only" aria-live="polite">
        {live}
      </p>

      {!ready ? (
        <LoadingBlock rows={3} />
      ) : (
        <ul className="space-y-3">
          {MARKET_IDS.map((m, i) => {
            const on = enabled[m];
            const s = STORES[m];
            const isBlocked = blocked === m;
            return (
              <li
                key={m}
                data-store={m}
                className={"card rise p-4 transition-shadow " + (on ? "is-active " : "") + (isBlocked ? shakeClass : "")}
                onAnimationEnd={onAnimationEnd}
                style={{ ["--i" as string]: i, ...(on ? { boxShadow: "var(--shadow-1), 0 0 0 2px var(--accent-soft)" } : {}) }}
              >
                <div className="flex items-start gap-3">
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 id={`mk-${m}`}>
                        <StoreName mode={m} size="text-xl" className="tracking-tight" />
                      </h2>
                      <span className="ac-chip" data-tone={on ? "good" : "muted"}>
                        <Icon name={on ? "check" : "x"} size={12} stroke={2.6} /> {on ? "Live" : "Hidden"}
                      </span>
                    </div>
                    <p className="text-xs font-semibold text-ink-2">Source: {s.source}</p>
                    <p className="mt-1 text-sm text-ink-2">{s.tagline}</p>
                  </div>
                  <button
                    type="button"
                    role="switch"
                    aria-checked={on}
                    aria-labelledby={`mk-${m}`}
                    aria-describedby={isBlocked ? `mk-${m}-err` : undefined}
                    className="ac-switch mt-1"
                    onClick={() => toggle(m)}
                  >
                    <span className="ac-switch-text on" aria-hidden>
                      ON
                    </span>
                    <span className="ac-switch-text off" aria-hidden>
                      OFF
                    </span>
                    <span className="ac-switch-knob" aria-hidden>
                      <Icon name={on ? "check" : "x"} size={14} stroke={2.8} />
                    </span>
                  </button>
                </div>
                {isBlocked && (
                  <p id={`mk-${m}-err`} className="ac-help fade-in mt-3 rounded-xl bg-surface-2 px-3 py-2" data-tone="warn" role="alert">
                    <Icon name="info" size={14} /> At least one store must stay on. Turn another store on first.
                  </p>
                )}
              </li>
            );
          })}
        </ul>
      )}
      <p className="mt-4 px-1 text-xs text-ink-3">Hidden stores disappear from the store switcher and the search store selector.</p>
    </div>
  );
}
