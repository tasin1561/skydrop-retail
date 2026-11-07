"use client";

import "./support.css";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { SUPPORT } from "@/lib/support";
import { useFeature } from "@/components/features/FeaturesProvider";
import { Icon } from "@/components/Icon";

const CHAT_PATH = "M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1h-9l-5 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM8 10h.01M12 10h.01M16 10h.01";

/**
 * Floating WhatsApp / Messenger button (NEXT_PUBLIC_SUPPORT_WHATSAPP / _MESSENGER). Hidden during checkout,
 * above the mobile tab bar on phones, and switchable in Admin → Features → "support_chat".
 */
export function SupportButton() {
  const on = useFeature("support_chat");
  const path = usePathname() ?? "";
  const [open, setOpen] = useState(false);
  const wrap = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onDown = (e: PointerEvent) => !wrap.current?.contains(e.target as Node) && setOpen(false);
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && setOpen(false);
    document.addEventListener("pointerdown", onDown);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("pointerdown", onDown);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  if (!on || path.startsWith("/checkout") || path.startsWith("/payments")) return null;

  const options = [
    SUPPORT.whatsappHref && { href: SUPPORT.whatsappHref, label: "WhatsApp", bg: "#25d366" },
    SUPPORT.messengerHref && { href: SUPPORT.messengerHref, label: "Messenger", bg: "#0084ff" },
  ].filter(Boolean) as { href: string; label: string; bg: string }[];

  return (
    <div ref={wrap} className="sc-wrap" data-open={open} data-lift={path.startsWith("/p/") || path === "/cart"}>
      {options.map((o, i) => (
        <a
          key={o.label}
          href={o.href}
          target="_blank"
          rel="noopener noreferrer"
          className="sc-opt"
          style={{ ["--k" as string]: options.length - i }}
          tabIndex={open ? 0 : -1}
          aria-hidden={!open}
          onClick={() => setOpen(false)}
        >
          <span className="sc-dot" style={{ background: o.bg }}>
            <Icon name="phone" size={16} />
          </span>
          Chat on {o.label}
        </a>
      ))}
      <Link href="/contact" className="sc-opt" style={{ ["--k" as string]: 0 }} tabIndex={open ? 0 : -1} aria-hidden={!open} onClick={() => setOpen(false)}>
        <span className="sc-dot bg-accent">
          <Icon name="help" size={16} />
        </span>
        Help and contact
      </Link>
      <button type="button" className="sc-main" aria-expanded={open} aria-label={open ? "Close support options" : "Chat with support"} onClick={() => setOpen((o) => !o)}>
        <svg className="sc-ic sc-ic-chat" viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
          <path d={CHAT_PATH} />
        </svg>
        <span className="sc-ic sc-ic-x" aria-hidden>
          <Icon name="x" size={24} stroke={2.4} />
        </span>
      </button>
    </div>
  );
}
