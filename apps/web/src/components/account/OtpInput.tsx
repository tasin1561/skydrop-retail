"use client";

import { useRef, useState } from "react";
import { Icon } from "@/components/Icon";
import { useShake } from "./ui";
import { useT } from "@/i18n/client";

const LEN = 6;

/**
 * Segmented one-time-code input ("link and merge"): one box per digit, auto-advance,
 * backspace goes back, paste fills all. When the code is accepted the boxes link with a bar,
 * then merge into a shield-check. Demo: any 6 digits are accepted.
 */
export function OtpInput({ onComplete, disabled }: { onComplete: (code: string) => void; disabled?: boolean }) {
  const [vals, setVals] = useState<string[]>(Array(LEN).fill(""));
  const [state, setState] = useState<"idle" | "link" | "merge">("idle");
  const [warn, setWarn] = useState("");
  const { t, digits: dg } = useT();
  const { shakeClass, shake, onAnimationEnd } = useShake();
  const refs = useRef<(HTMLInputElement | null)[]>([]);

  function focus(i: number) {
    const el = refs.current[Math.max(0, Math.min(LEN - 1, i))];
    el?.focus();
    el?.select();
  }

  function fail(msg: string) {
    setWarn(msg);
    shake();
  }

  function accept(next: string[]) {
    const code = next.join("");
    if (!/^\d{6}$/.test(code)) return;
    setWarn("");
    setState("link");
    (document.activeElement as HTMLElement | null)?.blur();
    setTimeout(() => setState("merge"), 380);
    onComplete(code);
  }

  function fill(from: number, digits: string) {
    const next = [...vals];
    let i = from;
    for (const d of digits) {
      if (i >= LEN) break;
      next[i++] = d;
    }
    setVals(next);
    if (next.every(Boolean)) accept(next);
    else focus(i);
  }

  function onChange(i: number, raw: string) {
    if (state !== "idle") return;
    const d = raw.replace(/\D/g, "");
    if (!d) {
      if (raw) fail(t("otp.numbersOnly"));
      const next = [...vals];
      next[i] = "";
      setVals(next);
      return;
    }
    setWarn("");
    // Typing over a filled box gives two characters; keep the newest. Autofill gives six.
    fill(i, d.length > 1 && d.length < LEN ? d.slice(-1) : d);
  }

  function onKeyDown(i: number, e: React.KeyboardEvent<HTMLInputElement>) {
    if (e.key === "Backspace" && !vals[i] && i > 0) {
      e.preventDefault();
      const next = [...vals];
      next[i - 1] = "";
      setVals(next);
      focus(i - 1);
    } else if (e.key === "ArrowLeft") {
      e.preventDefault();
      focus(i - 1);
    } else if (e.key === "ArrowRight") {
      e.preventDefault();
      focus(i + 1);
    } else if (e.key === "Enter") {
      e.preventDefault();
      if (!vals.every(Boolean)) fail(t("otp.enterAll", { n: vals.filter(Boolean).length }));
    }
  }

  function onPaste(i: number, e: React.ClipboardEvent<HTMLInputElement>) {
    e.preventDefault();
    if (state !== "idle") return;
    const text = e.clipboardData.getData("text");
    const d = text.replace(/\D/g, "");
    if (!d) return fail(t("otp.noDigits"));
    fill(d.length >= LEN ? 0 : i, d.slice(0, LEN));
  }

  const filled = vals.filter(Boolean).length;
  const tone = state !== "idle" ? "good" : warn ? "warn" : undefined;

  return (
    <div>
      <div
        role="group"
        aria-label={t("otp.label")}
        aria-describedby="otp-help"
        className={"ac-otp " + shakeClass}
        onAnimationEnd={(e) => e.target === e.currentTarget && onAnimationEnd()}
        data-state={state}
        data-tone={tone}
      >
        <span className="ac-otp-link" aria-hidden />
        {vals.map((v, i) => (
          <input
            key={i}
            ref={(el) => {
              refs.current[i] = el;
            }}
            className="ac-otp-box"
            style={{ ["--off" as string]: 2.5 - i }}
            data-filled={!!v}
            value={v}
            inputMode="numeric"
            pattern="[0-9]*"
            autoComplete={i === 0 ? "one-time-code" : "off"}
            maxLength={i === 0 ? LEN : 2}
            aria-label={t("otp.digit", { n: i + 1, total: LEN })}
            autoFocus={i === 0}
            disabled={disabled && state === "idle"}
            readOnly={state !== "idle"}
            onFocus={(e) => e.currentTarget.select()}
            onChange={(e) => onChange(i, e.target.value)}
            onKeyDown={(e) => onKeyDown(i, e)}
            onPaste={(e) => onPaste(i, e)}
          />
        ))}
        {state === "merge" && (
          <div className="ac-otp-shield" aria-hidden>
            <span>
              <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z" />
                <g className="ac-shield-check">
                  <path d="M8.5 12l2.5 2.5 4.5-5" strokeWidth="2.4" />
                </g>
              </svg>
            </span>
          </div>
        )}
      </div>
      <p id="otp-help" className="ac-help mt-3 justify-center" data-tone={tone} aria-live="polite">
        {state !== "idle" ? (
          <>
            <Icon name="check" size={14} stroke={2.6} /> {t("otp.secure")}
          </>
        ) : warn ? (
          <>
            <Icon name="info" size={14} /> {warn}
          </>
        ) : (
          <span className="tabular-nums">{t("otp.count", { n: dg(String(filled).padStart(2, "0")) })}</span>
        )}
      </p>
    </div>
  );
}
