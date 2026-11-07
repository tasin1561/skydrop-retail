"use client";

import { useState } from "react";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";

export function ShareButton({ title }: { title: string }) {
  const toast = useToast();
  const [done, setDone] = useState(false);

  async function share() {
    const url = window.location.href;
    if (navigator.share) {
      try {
        await navigator.share({ title, text: `${title} on Skydrop Retail`, url });
        return;
      } catch (e) {
        if ((e as Error)?.name === "AbortError") return;
      }
    }
    try {
      await navigator.clipboard.writeText(url);
      setDone(true);
      setTimeout(() => setDone(false), 1800);
      toast({ kind: "success", title: "Link copied", body: "Paste it anywhere to share this product." });
    } catch {
      toast({ kind: "error", title: "Could not copy the link", body: url });
    }
  }

  return (
    <button
      type="button"
      onClick={share}
      aria-label={done ? "Link copied" : "Share this product"}
      className="grid h-11 w-11 place-items-center rounded-full bg-surface/90 text-ink-2 shadow-[var(--shadow-1)] backdrop-blur transition hover:scale-105 hover:text-accent active:scale-95"
    >
      <span key={String(done)} className="pop grid place-items-center">
        <Icon name={done ? "check" : "share"} size={19} stroke={done ? 2.4 : 1.8} />
      </span>
    </button>
  );
}
