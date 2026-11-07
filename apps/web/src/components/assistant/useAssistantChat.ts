"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { AssistantHttpError, clearConversation, sendFeedback, streamChat } from "@/lib/assistant/client";
import { CARD_TYPES, isBangla, type AssistantEvent, type CardEvent, type ChatRequest } from "@/lib/assistant/types";

export type ChatMessage = {
  key: string;
  role: "user" | "assistant";
  text: string;
  cards: CardEvent[];
  status: "streaming" | "done" | "stopped" | "error";
  tool?: string | null;
  serverId?: number | null;
  degraded?: boolean;
  feedback?: "up" | "down";
  error?: string;
  /** The request that produced this reply (for Regenerate). */
  request?: ChatRequest;
};

const STORE_KEY = "skydrop-ai:v1";
let seq = 0;
const nextKey = () => `m${Date.now().toString(36)}${(seq++).toString(36)}`;

type Saved = { conversationId: string | null; messages: ChatMessage[] };

function load(): Saved {
  try {
    const raw = sessionStorage.getItem(STORE_KEY);
    if (raw) {
      const s = JSON.parse(raw) as Saved;
      return { conversationId: s.conversationId, messages: s.messages.map((m) => (m.status === "streaming" ? { ...m, status: "stopped" } : m)) };
    }
  } catch {
    /* ignore */
  }
  return { conversationId: null, messages: [] };
}

/** Chat state + streaming transport. Announcements go to a single polite live region (one per reply). */
export function useAssistantChat(token: string | null) {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [announce, setAnnounce] = useState("");
  const [remaining, setRemaining] = useState<number | null>(null);
  const abort = useRef<AbortController | null>(null);
  const convRef = useRef<string | null>(null);
  const loaded = useRef(false);

  useEffect(() => {
    const s = load();
    convRef.current = s.conversationId;
    // restore this tab's conversation once on mount (sessionStorage is client-only)
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setConversationId(s.conversationId);
    setMessages(s.messages);
    loaded.current = true;
  }, []);

  useEffect(() => {
    if (!loaded.current) return;
    try {
      sessionStorage.setItem(STORE_KEY, JSON.stringify({ conversationId, messages: messages.slice(-40) }));
    } catch {
      /* ignore */
    }
  }, [messages, conversationId]);

  useEffect(() => () => abort.current?.abort(), []);

  const patch = useCallback((key: string, fn: (m: ChatMessage) => ChatMessage) => {
    setMessages((ms) => ms.map((m) => (m.key === key ? fn(m) : m)));
  }, []);

  const run = useCallback(
    async (req: ChatRequest, replyKey: string) => {
      abort.current?.abort();
      const ctrl = new AbortController();
      abort.current = ctrl;
      setBusy(true);
      setAnnounce("");
      let text = "";
      let cards = 0;
      const announced = new Set<string>();
      try {
        await streamChat({ ...req, conversationId: convRef.current }, token, ctrl.signal, (e: AssistantEvent) => {
          switch (e.type) {
            case "meta":
              convRef.current = e.data.conversationId;
              setConversationId(e.data.conversationId);
              patch(replyKey, (m) => ({ ...m, degraded: e.data.degraded }));
              break;
            case "text":
              text += e.data.delta;
              patch(replyKey, (m) => ({ ...m, text: m.text + e.data.delta, tool: null }));
              break;
            case "tool":
              patch(replyKey, (m) => ({ ...m, tool: e.data.name }));
              if (!announced.has(e.data.name) && e.data.name === "search_products") {
                announced.add(e.data.name);
                setAnnounce(isBangla(req.message) ? "পণ্য খোঁজা হচ্ছে" : "Searching products");
              }
              break;
            case "done":
              if (typeof e.data.remaining === "number") setRemaining(e.data.remaining);
              patch(replyKey, (m) => ({ ...m, serverId: e.data.messageId, degraded: e.data.degraded, status: "done", tool: null }));
              break;
            case "error":
              patch(replyKey, (m) => ({ ...m, status: "error", error: e.data.message, tool: null }));
              break;
            default:
              if (CARD_TYPES.has(e.type)) {
                cards++;
                patch(replyKey, (m) => ({ ...m, cards: [...m.cards, e as CardEvent] }));
              }
          }
        });
        patch(replyKey, (m) => (m.status === "streaming" ? { ...m, status: "done", tool: null } : m));
        const summary = text.replace(/[*_`#]/g, "").slice(0, 160);
        setAnnounce(`Skydrop AI: ${summary}${text.length > 160 ? "…" : ""}${cards ? ` ${cards} result card${cards > 1 ? "s" : ""} shown.` : ""}`);
      } catch (err) {
        if (ctrl.signal.aborted) {
          patch(replyKey, (m) => ({ ...m, status: "stopped", tool: null }));
          setAnnounce("Stopped");
        } else {
          const msg = err instanceof AssistantHttpError ? err.message : "Connection lost. Please try again.";
          if (err instanceof AssistantHttpError && err.code === "rate_limited") setRemaining(0);
          patch(replyKey, (m) => ({ ...m, status: "error", error: msg, tool: null }));
          setAnnounce(msg);
        }
      } finally {
        if (abort.current === ctrl) abort.current = null;
        setBusy(false);
      }
    },
    [patch, token],
  );

  const send = useCallback(
    (message: string, extra: Omit<ChatRequest, "message"> = {}) => {
      const text = message.trim();
      if (!text || busy) return;
      const req: ChatRequest = { ...extra, message: text, locale: extra.locale ?? (isBangla(text) ? "bn" : "en"), client: "web" };
      const reply: ChatMessage = { key: nextKey(), role: "assistant", text: "", cards: [], status: "streaming", request: req };
      setMessages((ms) => [...ms, { key: nextKey(), role: "user", text, cards: [], status: "done" }, reply]);
      void run(req, reply.key);
    },
    [busy, run],
  );

  const stop = useCallback(() => abort.current?.abort(), []);

  /** Re-asks the last question: replaces the last reply. */
  const regenerate = useCallback(() => {
    const last = [...messages].reverse().find((m) => m.role === "assistant" && m.request);
    if (!last?.request || busy) return;
    const reply: ChatMessage = { key: nextKey(), role: "assistant", text: "", cards: [], status: "streaming", request: last.request };
    setMessages((ms) => [...ms.filter((m) => m.key !== last.key), reply]);
    void run(last.request, reply.key);
  }, [busy, messages, run]);

  const rate = useCallback(
    (key: string, rating: "up" | "down") => {
      const m = messages.find((x) => x.key === key);
      if (!m) return;
      patch(key, (x) => ({ ...x, feedback: rating }));
      if (m.serverId) void sendFeedback(m.serverId, rating, token);
      setAnnounce(rating === "up" ? "Thanks for the feedback" : "Thanks, we'll use this to improve");
    },
    [messages, patch, token],
  );

  const clear = useCallback(() => {
    abort.current?.abort();
    if (convRef.current) void clearConversation(convRef.current, token);
    convRef.current = null;
    setConversationId(null);
    setMessages([]);
    setAnnounce("Conversation cleared");
  }, [token]);

  return { messages, busy, send, stop, regenerate, rate, clear, announce, remaining, conversationId };
}
