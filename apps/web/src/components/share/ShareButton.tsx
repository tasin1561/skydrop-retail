"use client";

import "./share.css";
import { useEffect, useRef, useState } from "react";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { Sheet, Spinner } from "@/components/account/ui";
import { withCode } from "@/lib/growth";
import { useShareCodes } from "./useShareCodes";
import { useFeature } from "@/components/features/FeaturesProvider";

type Props = {
  title: string;
  /** Message placed before the link in WhatsApp/Telegram and navigator.share. */
  text?: string;
  /** Path on this site to share (default: the current page). */
  path?: string;
  /** Resolves the path lazily, e.g. creating a public wishlist link. Return null to cancel. */
  resolvePath?: () => Promise<string | null>;
  variant?: "icon" | "button" | "ghost";
  label?: string;
  /** Offer a QR code in the fallback sheet. */
  qr?: boolean;
  className?: string;
};

const isMobile = () => typeof navigator !== "undefined" && /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);

async function copyText(text: string) {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    try {
      const ta = document.createElement("textarea");
      ta.value = text;
      ta.setAttribute("readonly", "");
      ta.style.position = "fixed";
      ta.style.opacity = "0";
      document.body.appendChild(ta);
      ta.select();
      const ok = document.execCommand("copy");
      ta.remove();
      return ok;
    } catch {
      return false;
    }
  }
}

/**
 * Share a page of the store. Links get ?ref=CODE for approved affiliates, otherwise ?invite=CODE when signed in.
 * Uses the native share sheet when the browser has one; otherwise a bottom sheet (dialog on desktop) with
 * WhatsApp, Messenger, Facebook, Telegram, copy link and an optional QR code.
 */
export function ShareButton({ title, text, path, resolvePath, variant = "icon", label = "Share", qr = false, className = "" }: Props) {
  const codes = useShareCodes();
  const toast = useToast();
  const [open, setOpen] = useState<string | null>(null); // url shown in the sheet
  const [busy, setBusy] = useState(false);
  const [flash, setFlash] = useState(false);
  const enabled = useFeature("share");

  async function share() {
    if (busy) return;
    let p = path ?? window.location.pathname + window.location.search;
    if (resolvePath) {
      setBusy(true);
      try {
        const r = await resolvePath();
        if (!r) return;
        p = r;
      } catch {
        toast({ kind: "error", title: "Couldn't create the link", body: "Check your connection and try again." });
        return;
      } finally {
        setBusy(false);
      }
    }
    const url = withCode(new URL(p, window.location.origin).toString(), codes);
    const message = text ?? `${title} on Skydrop Retail`;
    if (typeof navigator.share === "function") {
      try {
        await navigator.share({ title, text: message, url });
        return;
      } catch (e) {
        if ((e as Error)?.name === "AbortError") return;
        // NotAllowedError (lost user activation) and others: fall back to our sheet
      }
    }
    setOpen(url);
  }

  const content =
    variant === "icon" ? (
      <span key={String(flash)} className="pop grid place-items-center">
        {busy ? <Spinner size={18} /> : <Icon name={flash ? "check" : "share"} size={19} stroke={flash ? 2.4 : 1.8} />}
      </span>
    ) : (
      <>
        {busy ? <Spinner size={18} /> : <Icon name="share" size={18} />} {label}
      </>
    );

  const cls =
    variant === "icon"
      ? "grid h-11 w-11 place-items-center rounded-full bg-surface/90 text-ink-2 shadow-[var(--shadow-1)] backdrop-blur transition hover:scale-105 hover:text-accent active:scale-95"
      : variant === "button"
        ? "btn btn-primary"
        : "btn btn-ghost";

  if (!enabled) return null;

  return (
    <>
      <button type="button" onClick={share} aria-label={variant === "icon" ? `Share ${title}` : undefined} aria-busy={busy} className={`${cls} ${className}`}>
        {content}
      </button>
      {open && (
        <ShareSheet
          url={open}
          title={title}
          message={text ?? `${title} on Skydrop Retail`}
          qr={qr}
          coded={codes.ref ? "ref" : codes.invite ? "invite" : null}
          onCopied={() => {
            setFlash(true);
            setTimeout(() => setFlash(false), 1800);
          }}
          onClose={() => setOpen(null)}
        />
      )}
    </>
  );
}

/* ---------------- Fallback sheet ---------------- */

const TARGETS: { id: string; label: string; color: string; svg: React.ReactNode; href: (url: string, msg: string) => string }[] = [
  {
    id: "whatsapp",
    label: "WhatsApp",
    color: "#25d366",
    href: (u, m) => `https://wa.me/?text=${encodeURIComponent(`${m} ${u}`)}`,
    svg: (
      <path d="M12 3.2a8.7 8.7 0 0 0-7.5 13.2L3.3 21l4.7-1.2A8.7 8.7 0 1 0 12 3.2zm4.9 12.1c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .2-3.2-.7a11.4 11.4 0 0 1-4.4-4c-.4-.6-1-1.7-1-2.9s.7-1.8.9-2.1c.3-.3.6-.3.8-.3h.6c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.4 0 .6l-.4.6-.4.4c-.1.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.5.1.7-.1l1-1.1c.2-.3.4-.2.7-.1l2 .9c.3.2.5.2.6.4.1.1.1.7-.1 1.3z" />
    ),
  },
  {
    id: "messenger",
    label: "Messenger",
    color: "#0866ff",
    href: (u) =>
      isMobile() ? `fb-messenger://share?link=${encodeURIComponent(u)}` : `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(u)}`,
    svg: (
      <path d="M12 2.8c-5.2 0-9.2 3.8-9.2 8.9 0 2.7 1.1 5 2.9 6.6v3.2l3-1.7c1 .3 2.1.5 3.3.5 5.2 0 9.2-3.8 9.2-8.9S17.2 2.8 12 2.8zm1 11.9-2.4-2.5-4.6 2.5 5-5.4 2.4 2.5 4.6-2.5-5 5.4z" />
    ),
  },
  {
    id: "facebook",
    label: "Facebook",
    color: "#1877f2",
    href: (u) => `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(u)}`,
    svg: <path d="M13.6 21v-7.6h2.6l.4-3h-3V8.5c0-.9.3-1.5 1.5-1.5h1.6V4.3c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H8v3h2.6V21h3z" />,
  },
  {
    id: "telegram",
    label: "Telegram",
    color: "#229ed9",
    href: (u, m) => `https://t.me/share/url?url=${encodeURIComponent(u)}&text=${encodeURIComponent(m)}`,
    svg: <path d="M20.7 4.3 3.4 11c-1.2.5-1.2 1.2-.2 1.5l4.4 1.4 1.7 5.2c.2.6.4.8.8.8s.6-.2.9-.5l2.2-2.1 4.5 3.3c.8.5 1.4.2 1.6-.8l2.9-13.7c.3-1.2-.4-1.7-1.5-1.3zm-3.4 3.3-7.6 6.9-.3 3.1-1.4-4.5 9.3-5.9c.4-.3.8 0 0 .4z" />,
  },
];

function ShareSheet({
  url,
  title,
  message,
  qr,
  coded,
  onCopied,
  onClose,
}: {
  url: string;
  title: string;
  message: string;
  qr: boolean;
  coded: "ref" | "invite" | null;
  onCopied: () => void;
  onClose: () => void;
}) {
  const toast = useToast();
  const [copied, setCopied] = useState(false);
  const [showQr, setShowQr] = useState(false);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
  useEffect(() => () => clearTimeout(timer.current), []);

  async function copy() {
    if (await copyText(url)) {
      setCopied(true);
      onCopied();
      clearTimeout(timer.current);
      timer.current = setTimeout(() => setCopied(false), 2000);
    } else {
      toast({ kind: "error", title: "Couldn't copy the link", body: url });
    }
  }

  return (
    <Sheet title="Share" description={title.length > 80 ? title.slice(0, 77) + "..." : title} icon="share" size="sm" onClose={onClose}>
      {() => (
        <div className="space-y-4">
          <div className="sh-targets" role="list">
            {TARGETS.map((t, i) => (
              <a
                key={t.id}
                role="listitem"
                href={t.href(url, message)}
                target={t.id === "messenger" && isMobile() ? undefined : "_blank"}
                rel="noopener noreferrer"
                className="sh-target"
                style={{ ["--i" as string]: i }}
              >
                <span className="sh-badge" style={{ background: t.color, color: t.color }}>
                  <svg viewBox="0 0 24 24" width="24" height="24" fill="#fff" aria-hidden>
                    {t.svg}
                  </svg>
                </span>
                {t.label}
              </a>
            ))}
          </div>

          <div className="sh-link">
            <Icon name="globe" size={16} className="shrink-0 text-ink-3" />
            <span className="min-w-0 flex-1 truncate text-sm font-medium" title={url}>
              {url.replace(/^https?:\/\//, "")}
            </span>
            <button type="button" onClick={copy} className="sh-copy btn btn-ghost min-h-11 px-3 text-sm" data-done={copied} aria-live="polite">
              <span className="sh-copy-face" data-face="idle" aria-hidden={copied}>
                <Icon name="receipt" size={15} /> Copy
              </span>
              <span className="sh-copy-face" data-face="done" aria-hidden={!copied}>
                <Icon name="check" size={15} stroke={2.6} /> Copied
              </span>
            </button>
          </div>

          {coded && (
            <p className="flex items-start gap-2 text-xs text-ink-2">
              <Icon name="spark" size={14} className="mt-px shrink-0 text-accent" />
              {coded === "ref"
                ? "Your affiliate code is in this link. You earn commission when friends order."
                : "Your invite code is in this link. You both get Skydrop Points after their first delivery."}
            </p>
          )}

          {qr && (
            <div>
              <button
                type="button"
                className="inline-flex min-h-11 items-center gap-2 rounded-full px-1 text-sm font-semibold text-accent"
                aria-expanded={showQr}
                onClick={() => setShowQr((s) => !s)}
              >
                <Icon name="grid" size={16} /> {showQr ? "Hide QR code" : "Show QR code"}
              </button>
              {showQr && (
                <div className="sh-qr mt-2 flex flex-col items-center gap-2 rounded-2xl border border-line bg-white p-4">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={`https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=0&data=${encodeURIComponent(url)}`}
                    alt={`QR code for ${url}`}
                    width={180}
                    height={180}
                    className="h-[180px] w-[180px]"
                  />
                  <span className="text-xs font-semibold text-slate-600">Scan with a phone camera</span>
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </Sheet>
  );
}
