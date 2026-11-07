"use client";

import "./shopping.css";
import Link from "next/link";
import { useState } from "react";
import { Icon } from "@/components/Icon";
import { ApiError } from "@/lib/apiClient";
import { createRequest } from "@/lib/shopping/client";
import type { ProductRequest } from "@/lib/shopping/types";
import { useStore } from "@/state/store";

const field = "w-full min-h-12 rounded-xl border border-line bg-surface px-3.5 text-[15px] text-ink outline-none transition focus:border-accent";

/** /request: paste a link or describe the product; optional photo, quantity, budget; phone for guests. */
export function RequestForm({ initialQuery = "", initialUrl = "", source = "web" }: { initialQuery?: string; initialUrl?: string; source?: "web" | "search" | "header" }) {
  const { user } = useStore();
  const [mode, setMode] = useState<"link" | "describe">(initialUrl ? "link" : initialQuery ? "describe" : "link");
  const [url, setUrl] = useState(initialUrl);
  const [desc, setDesc] = useState(initialQuery);
  const [photo, setPhoto] = useState("");
  const [qty, setQty] = useState(1);
  const [budget, setBudget] = useState("");
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [done, setDone] = useState<ProductRequest | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    const errs: Record<string, string> = {};
    if (mode === "link" && !/^https?:\/\/\S+\.\S+/.test(url.trim())) errs.url = "Paste the full link, starting with https://";
    if (mode === "describe" && desc.trim().length < 3) errs.description = "Tell us what you are looking for.";
    if (!user && !/^(?:\+?88)?01[3-9]\d{8}$/.test(phone.replace(/[\s-]/g, ""))) errs.phone = "Enter your mobile number (01XXXXXXXXX).";
    setErrors(errs);
    if (Object.keys(errs).length) return;
    setBusy(true);
    try {
      const r = await createRequest({
        url: mode === "link" ? url.trim() : undefined,
        description: desc.trim() || undefined,
        photoUrl: photo.trim() || undefined,
        qty,
        budgetBdt: Number(budget) || undefined,
        name: user ? undefined : name.trim() || undefined,
        phone: user ? undefined : phone.trim(),
        query: initialQuery || undefined,
        source,
      });
      setDone(r);
    } catch (err) {
      const data = err instanceof ApiError ? (err.data as { errors?: Record<string, string[]> }) : undefined;
      setErrors(Object.fromEntries(Object.entries(data?.errors ?? { form: ["We couldn't send your request. Please try again."] }).map(([k, v]) => [k, v[0]])));
    } finally {
      setBusy(false);
    }
  }

  if (done) {
    return (
      <div className="card pop p-6 text-center sm:p-8">
        <span className="mx-auto grid h-14 w-14 place-items-center rounded-full bg-good text-white">
          <svg viewBox="0 0 24 24" width="28" height="28" className="draw-check" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" aria-hidden>
            <path d="M5 12l5 5 9-11" />
          </svg>
        </span>
        <h2 className="mt-4 text-xl font-extrabold">Request sent</h2>
        <p className="mt-1 text-sm text-ink-2">Our sourcing team will look for it and send you a price, usually within a day.</p>
        <div className="mt-5 flex flex-wrap justify-center gap-2">
          {user || done.demo ? (
            <Link href="/account/requests" className="btn btn-primary min-h-11">
              Track my requests
            </Link>
          ) : null}
          <button type="button" className="btn btn-ghost min-h-11" onClick={() => setDone(null)}>
            Request something else
          </button>
        </div>
      </div>
    );
  }

  const tabIdx = mode === "link" ? 0 : 1;
  return (
    <form onSubmit={submit} className="card p-4 sm:p-6" noValidate>
      <div className="sh-tabs w-full" role="tablist" aria-label="How to describe it">
        <span className="sh-tabs__ind" aria-hidden style={{ width: "calc((100% - 8px) / 2)", transform: `translateX(${tabIdx * 100}%)` }} />
        <button type="button" role="tab" aria-selected={mode === "link"} onClick={() => setMode("link")}>
          Paste a link
        </button>
        <button type="button" role="tab" aria-selected={mode === "describe"} onClick={() => setMode("describe")}>
          Describe it
        </button>
      </div>

      <div className="mt-4 flex flex-col gap-4">
        {mode === "link" && (
          <label className="block">
            <span className="mb-1.5 block text-sm font-semibold">Product link</span>
            <input className={field} inputMode="url" placeholder="https://www.amazon.in/… or any Indian store" value={url} onChange={(e) => setUrl(e.target.value)} aria-invalid={!!errors.url} />
            {errors.url && <span className="mt-1 block text-xs text-bad">{errors.url}</span>}
          </label>
        )}
        <label className="block">
          <span className="mb-1.5 block text-sm font-semibold">{mode === "link" ? "Anything else? (size, colour…)" : "What are you looking for?"}</span>
          <textarea
            className={field + " min-h-24 py-3"}
            placeholder={mode === "link" ? "Optional" : "Brand, model, size, colour…"}
            value={desc}
            onChange={(e) => setDesc(e.target.value)}
            aria-invalid={!!errors.description}
          />
          {errors.description && <span className="mt-1 block text-xs text-bad">{errors.description}</span>}
        </label>
        <label className="block">
          <span className="mb-1.5 block text-sm font-semibold">Photo link (optional)</span>
          <input className={field} inputMode="url" placeholder="https://…" value={photo} onChange={(e) => setPhoto(e.target.value)} />
          {errors.photoUrl && <span className="mt-1 block text-xs text-bad">{errors.photoUrl}</span>}
        </label>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <span className="mb-1.5 block text-sm font-semibold">Quantity</span>
            <div className="flex min-h-12 items-center justify-between rounded-xl border border-line bg-surface px-1">
              <button type="button" className="grid h-11 w-11 place-items-center rounded-full" aria-label="Fewer" onClick={() => setQty((q) => Math.max(1, q - 1))}>
                <Icon name="minus" size={16} />
              </button>
              <span className="font-bold tabular-nums">{qty}</span>
              <button type="button" className="grid h-11 w-11 place-items-center rounded-full" aria-label="More" onClick={() => setQty((q) => Math.min(100, q + 1))}>
                <Icon name="plus" size={16} />
              </button>
            </div>
          </div>
          <label className="block">
            <span className="mb-1.5 block text-sm font-semibold">Budget (৳, optional)</span>
            <input className={field} inputMode="numeric" placeholder="e.g. 5000" value={budget} onChange={(e) => setBudget(e.target.value.replace(/\D/g, ""))} />
          </label>
        </div>
        {!user && (
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="block">
              <span className="mb-1.5 block text-sm font-semibold">Your name</span>
              <input className={field} autoComplete="name" value={name} onChange={(e) => setName(e.target.value)} />
            </label>
            <label className="block">
              <span className="mb-1.5 block text-sm font-semibold">Mobile number</span>
              <input className={field} inputMode="tel" autoComplete="tel" placeholder="01XXXXXXXXX" value={phone} onChange={(e) => setPhone(e.target.value)} aria-invalid={!!errors.phone} />
              {errors.phone && <span className="mt-1 block text-xs text-bad">{errors.phone}</span>}
            </label>
          </div>
        )}
        {errors.form && <p className="text-sm text-bad">{errors.form}</p>}
        <button type="submit" className="btn btn-primary min-h-12" disabled={busy}>
          {busy ? "Sending…" : "Send request"} <Icon name="arrow" size={18} className="arrow" />
        </button>
        <p className="text-center text-xs text-ink-2">Free to ask. We reply with a delivered price in BDT and a pay link.</p>
      </div>
    </form>
  );
}
