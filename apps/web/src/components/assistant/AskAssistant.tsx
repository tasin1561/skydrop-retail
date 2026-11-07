"use client";

import { openAssistant } from "@/lib/assistant/client";
import { useFeature } from "@/components/features/FeaturesProvider";
import { AiGlyph } from "./AiGlyph";
import { useT } from "@/i18n/client";

/** "Ask about this product" (product page). Hidden when the ai_assistant feature is off. */
export function AskAboutProduct({ productId, title }: { productId: string; title: string }) {
  const on = useFeature("ai_assistant");
  const { t } = useT();
  if (!on) return null;
  return (
    <button
      type="button"
      className="ai-entry"
      onClick={() => openAssistant({ productId, prompt: t("ai.askProductPrompt", { title: title.slice(0, 80) }), send: true })}
    >
      <AiGlyph size={16} /> {t("ai.askProduct")}
    </button>
  );
}

/** "Help me choose" (search results). */
export function HelpMeChoose({ query }: { query: string }) {
  const on = useFeature("ai_assistant");
  const { t } = useT();
  if (!on || !query.trim()) return null;
  return (
    <button
      type="button"
      className="ai-entry"
      onClick={() => openAssistant({ prompt: t("ai.helpChoosePrompt", { query: query.slice(0, 60) }), send: false })}
    >
      <AiGlyph size={16} /> {t("ai.helpChoose")}
    </button>
  );
}
