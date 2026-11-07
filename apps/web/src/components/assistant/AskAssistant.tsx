"use client";

import { openAssistant } from "@/lib/assistant/client";
import { useFeature } from "@/components/features/FeaturesProvider";
import { AiGlyph } from "./AiGlyph";

/** "Ask about this product" (product page). Hidden when the ai_assistant feature is off. */
export function AskAboutProduct({ productId, title }: { productId: string; title: string }) {
  const on = useFeature("ai_assistant");
  if (!on) return null;
  return (
    <button
      type="button"
      className="ai-entry"
      onClick={() => openAssistant({ productId, prompt: `Tell me about "${title.slice(0, 80)}": is it worth it, and when would it arrive?`, send: true })}
    >
      <AiGlyph size={16} /> Ask Skydrop AI about this product
    </button>
  );
}

/** "Help me choose" (search results). */
export function HelpMeChoose({ query }: { query: string }) {
  const on = useFeature("ai_assistant");
  if (!on || !query.trim()) return null;
  return (
    <button
      type="button"
      className="ai-entry"
      onClick={() => openAssistant({ prompt: `Help me choose the best ${query.slice(0, 60)}. My budget is ৳`, send: false })}
    >
      <AiGlyph size={16} /> Help me choose
    </button>
  );
}
