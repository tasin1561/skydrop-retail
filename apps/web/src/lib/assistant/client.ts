"use client";

// Browser side of the assistant: talks only to our Next.js route handlers (/api/assistant/*).
import { sseParser, type AssistantConfig, type AssistantEvent, type ChatRequest } from "./types";

export class AssistantHttpError extends Error {
  constructor(
    public status: number,
    message: string,
    public code?: string,
  ) {
    super(message);
  }
}

const auth = (token: string | null): Record<string, string> => (token ? { Authorization: `Bearer ${token}` } : {});

/** POST /api/assistant and call onEvent for each streamed event. Resolves when the stream ends. */
export async function streamChat(body: ChatRequest, token: string | null, signal: AbortSignal, onEvent: (e: AssistantEvent) => void) {
  const res = await fetch("/api/assistant", {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "text/event-stream", ...auth(token) },
    body: JSON.stringify(body),
    signal,
  });
  if (!res.ok || !res.body) {
    const data = (await res.json().catch(() => ({}))) as { message?: string; code?: string; error?: string };
    throw new AssistantHttpError(res.status, data.message ?? "Skydrop AI is unavailable right now.", data.code ?? data.error);
  }
  const reader = res.body.pipeThrough(new TextDecoderStream()).getReader();
  const parse = sseParser();
  for (;;) {
    const { value, done } = await reader.read();
    if (done) break;
    for (const e of parse(value)) onEvent(e);
  }
}

export async function fetchConfig(token: string | null): Promise<AssistantConfig | null> {
  try {
    const res = await fetch("/api/assistant/config", { headers: auth(token), cache: "no-store" });
    return res.ok ? ((await res.json()) as AssistantConfig) : null;
  } catch {
    return null;
  }
}

export function sendFeedback(messageId: number, rating: "up" | "down", token: string | null) {
  return fetch("/api/assistant/feedback", {
    method: "POST",
    headers: { "Content-Type": "application/json", ...auth(token) },
    body: JSON.stringify({ messageId, rating }),
  }).catch(() => null);
}

export function clearConversation(conversationId: string, token: string | null) {
  return fetch("/api/assistant/clear", {
    method: "POST",
    headers: { "Content-Type": "application/json", ...auth(token) },
    body: JSON.stringify({ conversationId }),
  }).catch(() => null);
}

/** Opens the assistant panel from anywhere (product page, search results). */
export type OpenAssistantDetail = { prompt?: string; send?: boolean; productId?: string };
export const OPEN_EVENT = "skydrop:assistant";
export function openAssistant(detail: OpenAssistantDetail = {}) {
  window.dispatchEvent(new CustomEvent<OpenAssistantDetail>(OPEN_EVENT, { detail }));
}
