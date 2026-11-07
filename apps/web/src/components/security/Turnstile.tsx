"use client";

// Cloudflare Turnstile (bot check) for the OTP request. Renders nothing unless NEXT_PUBLIC_TURNSTILE_SITE_KEY is set;
// the API skips the check when its TURNSTILE_SECRET_KEY is unset. The solved token is read by apiClient.requestOtp().
import { useEffect, useRef } from "react";
import { setTurnstileReset, setTurnstileToken } from "@/lib/turnstile";

const SITE_KEY = process.env.NEXT_PUBLIC_TURNSTILE_SITE_KEY ?? "";
const SCRIPT = "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";

type TurnstileApi = {
  render: (el: HTMLElement, opts: Record<string, unknown>) => string;
  reset: (id?: string) => void;
  remove: (id: string) => void;
};
declare global {
  interface Window {
    turnstile?: TurnstileApi;
  }
}

let widgetId: string | null = null;

export const turnstileEnabled = SITE_KEY.length > 0;

function loadScript(): Promise<void> {
  if (window.turnstile) return Promise.resolve();
  return new Promise((resolve, reject) => {
    const existing = document.querySelector<HTMLScriptElement>(`script[src="${SCRIPT}"]`);
    const s = existing ?? document.createElement("script");
    s.addEventListener("load", () => resolve());
    s.addEventListener("error", () => reject(new Error("turnstile")));
    if (!existing) {
      s.src = SCRIPT;
      s.async = true;
      document.head.appendChild(s);
    }
  });
}

export function TurnstileWidget({ className = "" }: { className?: string }) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!turnstileEnabled || !ref.current) return;
    let cancelled = false;
    loadScript()
      .then(() => {
        if (cancelled || !ref.current || !window.turnstile) return;
        widgetId = window.turnstile.render(ref.current, {
          sitekey: SITE_KEY,
          size: "flexible",
          appearance: "interaction-only",
          callback: (token: string) => setTurnstileToken(token),
          "expired-callback": () => setTurnstileToken(null),
        });
        setTurnstileReset(() => widgetId && window.turnstile?.reset(widgetId));
      })
      .catch(() => {});
    return () => {
      cancelled = true;
      if (widgetId && window.turnstile) window.turnstile.remove(widgetId);
      widgetId = null;
      setTurnstileToken(null);
      setTurnstileReset(null);
    };
  }, []);

  if (!turnstileEnabled) return null;
  return <div ref={ref} className={className} />;
}
