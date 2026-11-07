"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import type HlsType from "hls.js";
import { Icon } from "@/components/Icon";
import { useT } from "@/i18n/client";

type Phase = "idle" | "loading" | "playing" | "error";

// Plays an HLS stream. hls.js is only imported when the shopper presses play,
// and skipped entirely when the browser can play HLS natively (Safari, iOS).
export function VideoPlayer({
  hls,
  thumb,
  active,
  autoPlay,
  source,
  title,
}: {
  hls: string;
  thumb: string | null;
  active: boolean;
  autoPlay?: boolean;
  source: string;
  title: string;
}) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const { t } = useT();
  const hlsRef = useRef<HlsType | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const tried = useRef(false);
  const [phase, setPhase] = useState<Phase>("idle");

  const fail = useCallback(() => {
    if (timer.current) clearTimeout(timer.current);
    hlsRef.current?.destroy();
    hlsRef.current = null;
    setPhase("error");
  }, []);

  const playNow = useCallback(async () => {
    const v = videoRef.current;
    if (!v) return;
    try {
      await v.play();
    } catch (e) {
      // Autoplay with sound can be blocked; let the shopper press play again.
      if ((e as Error)?.name === "NotAllowedError") setPhase("idle");
      else if ((e as Error)?.name !== "AbortError") fail();
    }
  }, [fail]);

  const start = useCallback(async () => {
    const v = videoRef.current;
    if (!v) return;
    tried.current = true;
    if (v.src || hlsRef.current) {
      setPhase("loading");
      void playNow();
      return;
    }
    setPhase("loading");
    timer.current = setTimeout(fail, 15000);
    if (v.canPlayType("application/vnd.apple.mpegurl")) {
      v.src = hls;
      void playNow();
      return;
    }
    try {
      const { default: Hls } = await import("hls.js");
      if (!Hls.isSupported()) return fail();
      const h = new Hls({ maxBufferLength: 20 });
      hlsRef.current = h;
      h.on(Hls.Events.ERROR, (_e, data) => {
        if (data.fatal) fail();
      });
      h.on(Hls.Events.MANIFEST_PARSED, () => void playNow());
      h.loadSource(hls);
      h.attachMedia(v);
    } catch {
      fail();
    }
  }, [hls, fail, playNow]);

  // Autoplay once when the viewer was opened from this video's thumbnail.
  useEffect(() => {
    if (active && autoPlay && !tried.current) {
      const id = setTimeout(() => void start(), 60);
      return () => clearTimeout(id);
    }
  }, [active, autoPlay, start]);

  // Pause when swiped away.
  useEffect(() => {
    if (!active) videoRef.current?.pause();
  }, [active]);

  useEffect(
    () => () => {
      if (timer.current) clearTimeout(timer.current);
      hlsRef.current?.destroy();
    },
    [],
  );

  return (
    <div className="relative grid h-full w-full place-items-center">
      <video
        ref={videoRef}
        poster={thumb ?? undefined}
        controls={phase === "playing"}
        playsInline
        preload="none"
        aria-label={t("vid.aria", { title })}
        className={"max-h-full max-w-full rounded-2xl bg-black transition-opacity duration-200 " + (phase === "error" ? "opacity-30" : "opacity-100")}
        onPlaying={() => {
          if (timer.current) clearTimeout(timer.current);
          setPhase("playing");
        }}
        onWaiting={() => setPhase((p) => (p === "playing" ? "playing" : p))}
        onError={() => {
          if (tried.current) fail();
        }}
      />
      {phase !== "playing" && (
        <div className="absolute inset-0 grid place-items-center p-6" aria-live="polite">
          {phase === "idle" && (
            <button
              type="button"
              onClick={() => void start()}
              className="pd-play group h-20 w-20 hover:bg-surface active:scale-[0.98]"
              aria-label={t("gal.play")}
            >
              <Icon name="play" size={30} fill="currentColor" stroke={1} className="translate-x-0.5" />
            </button>
          )}
          {phase === "loading" && (
            <span className="pd-play h-20 w-20" role="status" aria-label={t("vid.loading")}>
              <svg width="34" height="34" viewBox="0 0 24 24" className="pd-spin" aria-hidden>
                <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" strokeOpacity=".25" strokeWidth="2.4" />
                <path d="M21 12a9 9 0 0 0-9-9" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" />
              </svg>
            </span>
          )}
          {phase === "error" && (
            <div className="pop max-w-xs rounded-2xl bg-surface p-5 text-center text-ink shadow-[var(--shadow-2)]">
              <span className="chip-icon mx-auto mb-3">
                <Icon name="info" size={18} />
              </span>
              <p className="text-sm font-bold">{t("vid.cantPlay")}</p>
              <p className="mt-1 text-xs text-ink-2">
                {t("vid.cantStream", { source })}
              </p>
              <button
                type="button"
                onClick={() => {
                  hlsRef.current?.destroy();
                  hlsRef.current = null;
                  videoRef.current?.removeAttribute("src");
                  setPhase("idle");
                }}
                className="btn btn-ghost mt-3 text-sm"
              >
                <Icon name="refresh" size={16} /> {t("common.tryAgain")}
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
