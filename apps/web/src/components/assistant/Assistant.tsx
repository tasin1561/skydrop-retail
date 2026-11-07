"use client";

import "./assistant.css";
import { usePathname } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import { OPEN_EVENT, type OpenAssistantDetail } from "@/lib/assistant/client";
import { useFeature } from "@/components/features/FeaturesProvider";
import { AssistantPanel } from "./AssistantPanel";
import { AiGlyph } from "./AiGlyph";

/**
 * Floating "Ask Skydrop AI" launcher + chat panel (feature switch "ai_assistant").
 * Stacks beside the WhatsApp/Messenger support button (support_chat) so their menus never overlap.
 * Other components open it with openAssistant({ prompt, send, productId }) from @/lib/assistant/client.
 */
export function Assistant() {
  const on = useFeature("ai_assistant");
  const support = useFeature("support_chat");
  const path = usePathname() ?? "";
  const [open, setOpen] = useState(false);
  const [mounted, setMounted] = useState(false);
  const [pending, setPending] = useState<OpenAssistantDetail | null>(null);
  const launcher = useRef<HTMLButtonElement>(null);

  const show = useCallback((detail?: OpenAssistantDetail) => {
    setMounted(true);
    setOpen(true);
    if (detail && (detail.prompt || detail.productId)) setPending(detail);
  }, []);

  useEffect(() => {
    const h = (e: Event) => show((e as CustomEvent<OpenAssistantDetail>).detail);
    window.addEventListener(OPEN_EVENT, h);
    return () => window.removeEventListener(OPEN_EVENT, h);
  }, [show]);

  useEffect(() => {
    if (!open) return;
    // Mobile: full-screen sheet, so stop the page behind it from scrolling.
    const mq = window.matchMedia("(max-width: 767px)");
    if (!mq.matches) return;
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = prev;
    };
  }, [open]);

  const close = useCallback(() => {
    setOpen(false);
    requestAnimationFrame(() => launcher.current?.focus());
  }, []);

  if (!on) return null;
  const hideLauncher = path.startsWith("/checkout") || path.startsWith("/payments") || open;
  const productId = path.startsWith("/p/") ? decodeURIComponent(path.slice(3).split("/")[0]) : undefined;

  return (
    <>
      {!hideLauncher && (
        <button
          ref={launcher}
          type="button"
          className="ai-launch"
          data-beside-support={support}
          aria-haspopup="dialog"
          aria-expanded={open}
          onClick={() => show()}
        >
          <AiGlyph size={22} />
          <span className="ai-launch-label">Ask Skydrop AI</span>
          <span className="sr-only md:hidden">Ask Skydrop AI</span>
        </button>
      )}
      {open && <div className="ai-scrim" aria-hidden onClick={close} />}
      {mounted && <AssistantPanel open={open} onClose={close} pending={pending} onPendingHandled={() => setPending(null)} productId={productId} />}
    </>
  );
}
