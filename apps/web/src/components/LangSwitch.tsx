"use client";

// EN | বাংলা segmented toggle. Sets the sr-lang cookie (server components re-render via router.refresh)
// and, for signed-in shoppers, saves the choice to the profile so SMS/email arrive in the same language.
import { useEffect, useRef } from "react";
import { useLang, useT } from "@/i18n/client";
import type { Lang } from "@/i18n/core";
import { useFeature } from "./features/FeaturesProvider";
import { useStore } from "@/state/store";
import { api, apiEnabled } from "@/lib/apiClient";

const OPTIONS: { id: Lang; label: string }[] = [
  { id: "en", label: "EN" },
  { id: "bn", label: "বাংলা" },
];

export function LangSwitch({ className = "", size = "md" }: { className?: string; size?: "sm" | "md" }) {
  const on = useFeature("bangla");
  const { lang, setLang } = useLang();
  const { t } = useT();
  if (!on) return null;
  const idx = OPTIONS.findIndex((o) => o.id === lang);
  if (size === "sm")
    // compact header form: "EN | বাংলা" as small text, the active language in ink
    return (
      <div role="group" aria-label={t("lang.label")} className={"lang-switch shrink-0 items-center text-[13px] " + className}>
        <div className="flex items-center">
          {OPTIONS.map((o, i) => (
            <span key={o.id} className="flex items-center">
              {i > 0 && <span aria-hidden className="h-3.5 w-px bg-line-2" />}
              <button
                type="button"
                lang={o.id}
                aria-pressed={lang === o.id}
                onClick={() => setLang(o.id)}
                className={
                  "min-h-11 px-2 transition-colors duration-200 " +
                  (lang === o.id ? "font-medium text-ink" : "text-ink-3 hover:text-ink-2")
                }
              >
                {o.label}
              </button>
            </span>
          ))}
        </div>
      </div>
    );
  return (
    <div
      role="group"
      aria-label={t("lang.label")}
      className={"lang-switch relative grid shrink-0 grid-cols-2 rounded-full bg-surface-2 p-1 " + "w-[8.5rem] " + className}
    >
      <span
        aria-hidden
        className="lang-switch-pill absolute bottom-1 left-1 top-1 rounded-full bg-surface shadow-[var(--shadow-1)]"
        style={{ width: "calc(50% - 4px)", transform: `translateX(${idx * 100}%)` }}
      />
      {OPTIONS.map((o) => (
        <button
          key={o.id}
          type="button"
          lang={o.id}
          aria-pressed={lang === o.id}
          onClick={() => setLang(o.id)}
          className={
            "relative z-10 min-h-11 rounded-full px-2 text-[13px] font-medium transition-colors duration-200 " +
            (lang === o.id ? "text-ink" : "text-ink-3 hover:text-ink-2")
          }
        >
          {o.label}
        </button>
      ))}
    </div>
  );
}

/** Keeps a signed-in shopper's profile locale in step with the language they browse in. */
export function LangSync() {
  const { lang } = useLang();
  const { user, token } = useStore();
  const sent = useRef<string | null>(null);
  useEffect(() => {
    if (!apiEnabled || !token || !user) return;
    if ((user.locale ?? "en") === lang || sent.current === token + lang) return;
    sent.current = token + lang;
    api.updateMe({ locale: lang }).catch(() => {
      sent.current = null;
    });
  }, [lang, token, user]);
  return null;
}
