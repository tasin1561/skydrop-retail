"use client";

import "./share.css";
import { useEffect, useRef, useState } from "react";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";

/** Copy to clipboard; the label morphs to "Copied" in place (stable accessible name, polite announcement). */
export function CopyButton({ text, label = "Copy", className = "btn btn-ghost min-h-11 px-3 text-sm" }: { text: string; label?: string; className?: string }) {
  const toast = useToast();
  const [done, setDone] = useState(false);
  const t = useRef<ReturnType<typeof setTimeout>>(undefined);
  useEffect(() => () => clearTimeout(t.current), []);

  async function copy() {
    try {
      await navigator.clipboard.writeText(text);
      setDone(true);
      clearTimeout(t.current);
      t.current = setTimeout(() => setDone(false), 2000);
    } catch {
      toast({ kind: "error", title: "Couldn't copy", body: text });
    }
  }

  return (
    <button type="button" onClick={copy} className={`sh-copy ${className}`} data-done={done} aria-label={`${label}: ${text}`}>
      <span className="sh-copy-face" data-face="idle" aria-hidden>
        <Icon name="receipt" size={15} /> {label}
      </span>
      <span className="sh-copy-face" data-face="done" aria-hidden>
        <Icon name="check" size={15} stroke={2.6} /> Copied
      </span>
      <span className="sr-only" aria-live="polite">
        {done ? "Copied" : ""}
      </span>
    </button>
  );
}
