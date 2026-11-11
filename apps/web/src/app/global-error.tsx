"use client";

import { translate, type Lang } from "@/i18n/core";

// Last-resort screen if the root layout itself fails. Renders its own document (no global CSS).
export default function GlobalError({ retry }: { error: unknown; retry: () => void }) {
  // The root layout failed, so there is no language context: read the switch cookie directly.
  const lang: Lang = typeof document !== "undefined" && /(?:^|;\s*)sr-lang=bn/.test(document.cookie) ? "bn" : "en";
  const t = (k: Parameters<typeof translate>[1]) => translate(lang, k);
  return (
    <html lang={lang}>
      <body style={{ margin: 0, fontFamily: "system-ui, sans-serif", background: "#f7f7f4", color: "#0b0b0f" }}>
        <title>Skydrop Retail</title>
        <main style={{ maxWidth: 420, margin: "0 auto", padding: "64px 16px", textAlign: "center" }}>
          <h1 style={{ fontSize: 24, margin: "0 0 8px" }}>{t("maint.title")}</h1>
          <p style={{ color: "#55555f", fontSize: 14 }}>{t("ge.body")}</p>
          <button
            type="button"
            onClick={() => retry()}
            style={{ marginTop: 20, minHeight: 44, padding: "0 20px", borderRadius: 12, border: 0, background: "#0f766e", color: "#fff", fontWeight: 700 }}
          >
            {t("common.tryAgain")}
          </button>
        </main>
      </body>
    </html>
  );
}
