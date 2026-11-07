"use client";

import { useEffect, useRef, useState } from "react";

export type JobState = "queued" | "running" | "done" | "failed" | "unknown";

/**
 * Polls /api/jobs/{id} with backoff (1s → 1.5s → 2s… max 4s), pauses while the tab is hidden,
 * and stops on done/failed or after `maxMs`. Returns the state and elapsed seconds.
 */
export function useJob(jobId: string | undefined, { maxMs = 90_000, onDone }: { maxMs?: number; onDone?: (s: JobState) => void } = {}) {
  const [state, setState] = useState<JobState>("queued");
  const [elapsed, setElapsed] = useState(0);
  const done = useRef(onDone);
  useEffect(() => {
    done.current = onDone;
  }, [onDone]);

  useEffect(() => {
    if (!jobId) return;
    let alive = true;
    let delay = 1000;
    const started = performance.now();
    let timer: ReturnType<typeof setTimeout> | undefined;
    const tick = setInterval(() => alive && setElapsed(Math.floor((performance.now() - started) / 1000)), 1000);

    const poll = async () => {
      if (!alive) return;
      if (document.hidden) {
        timer = setTimeout(poll, 1500);
        return;
      }
      let s: JobState = "unknown";
      try {
        const res = await fetch(`/api/jobs/${encodeURIComponent(jobId)}`, { cache: "no-store" });
        s = ((await res.json()) as { status: JobState }).status;
      } catch {
        s = "unknown";
      }
      if (!alive) return;
      if (s !== "unknown") setState(s);
      if (s === "done" || s === "failed") {
        clearInterval(tick);
        done.current?.(s);
        return;
      }
      if (performance.now() - started > maxMs) {
        clearInterval(tick);
        setState("failed");
        done.current?.("failed");
        return;
      }
      delay = Math.min(4000, delay + 500);
      timer = setTimeout(poll, delay);
    };
    timer = setTimeout(poll, 800);
    return () => {
      alive = false;
      clearInterval(tick);
      if (timer) clearTimeout(timer);
    };
  }, [jobId, maxMs]);

  return { state, elapsed };
}
