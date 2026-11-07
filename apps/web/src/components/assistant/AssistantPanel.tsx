"use client";

import { Fragment, useEffect, useRef, useState, type KeyboardEvent as ReactKeyboardEvent, type ReactNode } from "react";
import { fetchConfig } from "@/lib/assistant/client";
import type { AssistantConfig } from "@/lib/assistant/types";
import { useStore } from "@/state/store";
import { useFeature } from "@/components/features/FeaturesProvider";
import { Icon } from "@/components/Icon";
import { AssistantCard } from "./AssistantCards";
import { useAssistantChat, type ChatMessage } from "./useAssistantChat";
import { AiGlyph } from "./AiGlyph";

const TOOL_LABEL: Record<string, string> = {
  search_products: "Searching the catalog",
  get_product: "Reading product details",
  compare_products: "Comparing",
  get_price_history: "Checking price history",
  delivery_estimate: "Checking delivery dates",
  get_my_orders: "Looking up your orders",
  track_order: "Tracking your order",
  add_to_cart_suggestion: "Preparing your cart button",
  create_product_request: "Sending your request",
};

/** Tiny, safe formatter: paragraphs, "- " bullets and **bold** (no HTML injection). */
function Rich({ text }: { text: string }) {
  const inline = (s: string, k: string): ReactNode[] =>
    s.split(/(\*\*[^*]+\*\*)/g).map((part, i) =>
      part.startsWith("**") && part.endsWith("**") && part.length > 4 ? <strong key={k + i}>{part.slice(2, -2)}</strong> : <Fragment key={k + i}>{part}</Fragment>,
    );
  const blocks: ReactNode[] = [];
  let list: string[] = [];
  const flush = (k: number) => {
    if (list.length) blocks.push(<ul key={"u" + k}>{list.map((l, i) => <li key={i}>{inline(l, `l${k}-${i}-`)}</li>)}</ul>);
    list = [];
  };
  text.split("\n").forEach((line, i) => {
    const m = line.match(/^\s*(?:[-*•]|\d+\.)\s+(.*)$/);
    if (m) return void list.push(m[1]);
    flush(i);
    if (line.trim()) blocks.push(<p key={"p" + i}>{inline(line, `p${i}-`)}</p>);
  });
  flush(-1);
  return <>{blocks}</>;
}

export function AssistantPanel({
  open,
  onClose,
  pending,
  onPendingHandled,
  productId,
}: {
  open: boolean;
  onClose: () => void;
  pending: { prompt?: string; send?: boolean; productId?: string } | null;
  onPendingHandled: () => void;
  productId?: string;
}) {
  const { token, cart, mode } = useStore();
  const bangla = useFeature("bangla");
  const chat = useAssistantChat(token);
  const [config, setConfig] = useState<AssistantConfig | null>(null);
  const [lang, setLang] = useState<"en" | "bn">("en");
  const [draft, setDraft] = useState("");
  const panel = useRef<HTMLElement>(null);
  const input = useRef<HTMLTextAreaElement>(null);
  const log = useRef<HTMLDivElement>(null);
  const stick = useRef(true);

  useEffect(() => {
    if (!open) return;
    let off = false;
    fetchConfig(token).then((c) => !off && setConfig(c));
    return () => {
      off = true;
    };
  }, [open, token]);

  const ctx = (pid?: string) => ({
    store: mode,
    context: { productId: pid ?? productId, cartIds: cart.map((c) => c.productId).slice(0, 20) },
  });

  const submit = (text: string, pid?: string) => {
    if (!text.trim() || chat.busy) return;
    stick.current = true;
    chat.send(text, ctx(pid));
    setDraft("");
  };

  // Entry points ("Ask about this product", "Help me choose") hand over a prompt.
  useEffect(() => {
    if (!open || !pending) return;
    // A one-shot hand-over from an entry-point click: send it, or prefill the composer for the shopper to finish.
    /* eslint-disable react-hooks/set-state-in-effect */
    if (pending.send && pending.prompt) submit(pending.prompt, pending.productId);
    else if (pending.prompt) setDraft(pending.prompt);
    /* eslint-enable react-hooks/set-state-in-effect */
    onPendingHandled();
    requestAnimationFrame(() => input.current?.focus());
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, pending]);

  useEffect(() => {
    if (open) requestAnimationFrame(() => input.current?.focus());
  }, [open]);

  // Keep the newest content in view while streaming, unless the shopper scrolled up.
  useEffect(() => {
    const el = log.current;
    if (el && stick.current) el.scrollTop = el.scrollHeight;
  }, [chat.messages]);

  const onKeyDown = (e: ReactKeyboardEvent) => {
    if (e.key === "Escape") {
      e.stopPropagation();
      if (chat.busy) chat.stop();
      else onClose();
      return;
    }
    if (e.key !== "Tab" || !panel.current) return;
    const f = panel.current.querySelectorAll<HTMLElement>('button:not([disabled]), a[href], textarea, [tabindex]:not([tabindex="-1"])');
    if (!f.length) return;
    const first = f[0];
    const last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  };

  const greeting = config?.greeting?.[lang] || (lang === "bn" ? "হ্যালো! কী খুঁজছেন বলুন।" : "Hi! Tell me what you're looking for and your budget in taka.");
  const prompts = (config?.prompts?.[lang] ?? []).slice(0, 6);
  const remaining = chat.remaining ?? config?.remaining ?? null;
  const out = remaining === 0;
  const last = chat.messages[chat.messages.length - 1];
  const lastAssistantKey = [...chat.messages].reverse().find((m) => m.role === "assistant")?.key;

  return (
    <section
      ref={panel}
      role="dialog"
      aria-modal="true"
      aria-labelledby="ai-title"
      className="ai-panel"
      data-open={open}
      onKeyDown={onKeyDown}
      hidden={!open}
    >
      <header className="ai-head">
        <span className="ai-avatar" aria-hidden>
          <AiGlyph size={20} />
        </span>
        <div className="min-w-0 flex-1">
          <h2 id="ai-title" className="text-[15px] font-extrabold leading-tight">
            Skydrop AI
          </h2>
          <p className="truncate text-[11px] text-ink-2">
            {config?.demo ? "Demo mode · answers from the sample catalog" : config?.basic ? "Basic mode · showing search results" : "Shopping help · prices in BDT, all-inclusive"}
          </p>
        </div>
        {chat.messages.length > 0 && (
          <button type="button" className="ai-icon-btn" onClick={chat.clear} aria-label="Clear conversation" title="Clear conversation">
            <Icon name="trash" size={18} />
          </button>
        )}
        <button type="button" className="ai-icon-btn" onClick={onClose} aria-label="Close Skydrop AI">
          <Icon name="x" size={20} />
        </button>
      </header>

      <div
        ref={log}
        className="ai-log"
        role="log"
        aria-live="off"
        aria-label="Conversation"
        tabIndex={0}
        onScroll={(e) => {
          const el = e.currentTarget;
          stick.current = el.scrollHeight - el.scrollTop - el.clientHeight < 60;
        }}
      >
        {chat.messages.length === 0 ? (
          <div className="ai-empty">
            <span className="ai-orb" aria-hidden>
              <AiGlyph size={30} />
            </span>
            <p className="max-w-[30ch] text-center text-[15px] font-semibold leading-snug">{greeting}</p>
            {bangla && (
              <div className="ai-lang" role="group" aria-label="Suggestion language">
                {(["en", "bn"] as const).map((l) => (
                  <button key={l} type="button" aria-pressed={lang === l} onClick={() => setLang(l)}>
                    {l === "en" ? "English" : "বাংলা"}
                  </button>
                ))}
              </div>
            )}
            <div className="ai-chips">
              {productId && (
                <button type="button" className="ai-chip" onClick={() => submit(lang === "bn" ? "এই পণ্যটি কেমন? ঈদের আগে পাব?" : "Is this product worth it, and when would it arrive?")}>
                  {lang === "bn" ? "এই পণ্য সম্পর্কে জানতে চাই" : "Ask about this product"}
                </button>
              )}
              {prompts.map((p, i) => (
                <button key={p} type="button" className="ai-chip" style={{ ["--i" as string]: i }} onClick={() => submit(p)} disabled={out}>
                  {p}
                </button>
              ))}
            </div>
          </div>
        ) : (
          chat.messages.map((m) => <Bubble key={m.key} m={m} isLast={m.key === lastAssistantKey} busy={chat.busy} onNavigate={onClose} onRate={chat.rate} onRegenerate={chat.regenerate} />)
        )}
      </div>

      <form
        className="ai-compose"
        onSubmit={(e) => {
          e.preventDefault();
          submit(draft);
        }}
      >
        {out && <p className="ai-limit">Daily AI limit reached.{token ? " Try again tomorrow." : " Sign in for more messages."}</p>}
        <div className="ai-input">
          <label htmlFor="ai-msg" className="sr-only">
            Message Skydrop AI
          </label>
          <textarea
            id="ai-msg"
            ref={input}
            rows={1}
            value={draft}
            maxLength={2000}
            disabled={out}
            placeholder={lang === "bn" ? "কী খুঁজছেন? বাজেট সহ লিখুন…" : "What are you looking for? Add a budget…"}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter" && !e.shiftKey && !e.nativeEvent.isComposing) {
                e.preventDefault();
                submit(draft);
              }
            }}
          />
          {chat.busy ? (
            <button type="button" className="ai-send ai-stop" onClick={chat.stop} aria-label="Stop generating">
              <span className="ai-stop-sq" aria-hidden />
            </button>
          ) : (
            <button type="submit" className="ai-send" disabled={!draft.trim() || out} aria-label="Send message">
              <Icon name="arrow" size={18} stroke={2.4} className="-rotate-90" />
            </button>
          )}
        </div>
        <p className="ai-foot">
          AI can make mistakes. Prices and stock are re-checked at checkout.
          {remaining !== null && remaining <= 5 && remaining > 0 ? ` ${remaining} messages left today.` : ""}
        </p>
      </form>

      <p className="sr-only" aria-live="polite" aria-atomic="true">
        {chat.announce}
      </p>
      {last?.status === "streaming" && <span className="sr-only">Skydrop AI is replying</span>}
    </section>
  );
}

function Bubble({
  m,
  isLast,
  busy,
  onNavigate,
  onRate,
  onRegenerate,
}: {
  m: ChatMessage;
  isLast: boolean;
  busy: boolean;
  onNavigate: () => void;
  onRate: (key: string, r: "up" | "down") => void;
  onRegenerate: () => void;
}) {
  if (m.role === "user") {
    return (
      <div className="ai-msg ai-user">
        <span className="sr-only">You said: </span>
        {m.text}
      </div>
    );
  }
  const streaming = m.status === "streaming";
  return (
    <div className="ai-msg ai-bot" aria-busy={streaming}>
      <span className="sr-only">Skydrop AI: </span>
      {m.text ? (
        <div className="ai-text">
          <Rich text={m.text} />
          {streaming && <span className="ai-caret" aria-hidden />}
        </div>
      ) : streaming ? (
        <div className="ai-typing" aria-hidden>
          <i />
          <i />
          <i />
        </div>
      ) : null}
      {streaming && m.tool && (
        <p className="ai-tool">
          <span className="ai-spin" aria-hidden /> {TOOL_LABEL[m.tool] ?? "Working"}…
        </p>
      )}
      {m.cards.map((c, i) => (
        <div key={i} className="ai-card-wrap">
          <AssistantCard card={c} onNavigate={onNavigate} />
        </div>
      ))}
      {m.status === "error" && (
        <p className="ai-error" role="alert">
          {m.error}
        </p>
      )}
      {m.status === "stopped" && <p className="ai-tool">Stopped.</p>}
      {!streaming && (
        <div className="ai-actions">
          {m.status === "done" && m.serverId !== undefined && (
            <>
              <button type="button" className="ai-icon-btn sm" aria-pressed={m.feedback === "up"} aria-label="Helpful" onClick={() => onRate(m.key, "up")}>
                <Thumb up />
              </button>
              <button type="button" className="ai-icon-btn sm" aria-pressed={m.feedback === "down"} aria-label="Not helpful" onClick={() => onRate(m.key, "down")}>
                <Thumb />
              </button>
            </>
          )}
          {isLast && !busy && (
            <button type="button" className="ai-icon-btn sm" aria-label="Regenerate answer" title="Regenerate" onClick={onRegenerate}>
              <Icon name="refresh" size={16} />
            </button>
          )}
        </div>
      )}
    </div>
  );
}

function Thumb({ up }: { up?: boolean }) {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden style={up ? undefined : { transform: "rotate(180deg)" }}>
      <path d="M7 10v11H4a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1zM7 10l4-8a3 3 0 0 1 3 3v4h5.5a2 2 0 0 1 2 2.3l-1.4 8A2 2 0 0 1 18.1 21H7" />
    </svg>
  );
}
