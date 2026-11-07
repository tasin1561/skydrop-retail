"use client";

import "./legal.css";
import { useDeferredValue, useId, useMemo, useState } from "react";
import Link from "next/link";
import { Icon } from "@/components/Icon";

export type Faq = { q: string; a: string; topic: string };

function Highlight({ text, term }: { text: string; term: string }) {
  if (!term) return <>{text}</>;
  const parts = text.split(new RegExp(`(${term.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")})`, "ig"));
  return <>{parts.map((p, i) => (i % 2 ? <mark key={i}>{p}</mark> : p))}</>;
}

/** FAQ with live search and topic filter. Answers open with the grid-rows accordion (.acc in globals.css). */
export function FaqSearch({ faqs }: { faqs: Faq[] }) {
  const [q, setQ] = useState("");
  const [topic, setTopic] = useState("All");
  const [open, setOpen] = useState<number | null>(0);
  const term = useDeferredValue(q.trim());
  const id = useId();
  const topics = useMemo(() => ["All", ...Array.from(new Set(faqs.map((f) => f.topic)))], [faqs]);

  const shown = faqs
    .map((f, i) => ({ ...f, i }))
    .filter((f) => (topic === "All" || f.topic === topic) && (!term || (f.q + " " + f.a).toLowerCase().includes(term.toLowerCase())));

  return (
    <div className="faq">
      <div className="field">
        <input id={id} type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder=" " autoComplete="off" enterKeyHint="search" aria-describedby={id + "-hint"} />
        <label htmlFor={id}>Search questions</label>
        <Icon name="search" size={18} className="field-icon" />
      </div>

      <p id={id + "-hint"} className="mt-1.5 pl-1 text-xs text-ink-3">
        Try refund, customs, bKash or points
      </p>

      <div className="no-scrollbar -mx-4 mt-3 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:flex-wrap sm:px-0" role="group" aria-label="Topics">
        {topics.map((t) => (
          <button
            key={t}
            type="button"
            aria-pressed={topic === t}
            onClick={() => setTopic(t)}
            className={
              "min-h-11 shrink-0 rounded-full px-4 text-sm font-semibold transition-colors " +
              (topic === t ? "bg-accent text-white" : "bg-surface text-ink-2 shadow-[var(--shadow-1)] hover:text-ink")
            }
          >
            {t}
          </button>
        ))}
      </div>

      <p className="sr-only" aria-live="polite">
        {shown.length} {shown.length === 1 ? "question" : "questions"} found
      </p>

      <ul className="mt-5 flex flex-col gap-2.5">
        {shown.map((f, n) => {
          const isOpen = open === f.i || (!!term && shown.length <= 3);
          return (
            <li key={f.i} className="acc card rise overflow-hidden" data-open={isOpen} style={{ ["--i" as string]: n % 10 }}>
              <h3>
                <button
                  type="button"
                  id={`${id}-q${f.i}`}
                  aria-expanded={isOpen}
                  aria-controls={`${id}-a${f.i}`}
                  onClick={() => setOpen(open === f.i ? null : f.i)}
                  className="faq-q flex min-h-14 w-full items-center gap-3 px-4 py-3 text-left text-[15px] font-bold"
                >
                  <span className="min-w-0 flex-1">
                    <Highlight text={f.q} term={term} />
                  </span>
                  <span className="faq-plus grid h-8 w-8 shrink-0 place-items-center rounded-full bg-surface-2 text-ink-2">
                    <Icon name="plus" size={16} stroke={2.4} />
                  </span>
                </button>
              </h3>
              <div className="acc-body" id={`${id}-a${f.i}`} role="region" aria-labelledby={`${id}-q${f.i}`}>
                <div>
                  <p className="px-4 pb-4 text-sm leading-relaxed text-ink-2">
                    <Highlight text={f.a} term={term} />
                  </p>
                </div>
              </div>
            </li>
          );
        })}
      </ul>

      {!shown.length && (
        <div className="card mt-5 p-6 text-center">
          <p className="font-bold">No answers for &ldquo;{q}&rdquo;</p>
          <p className="mt-1 text-sm text-ink-2">Try another word, or ask us directly.</p>
          <Link href="/contact" className="btn btn-primary mt-4">
            Contact support <Icon name="arrow" size={16} className="arrow" />
          </Link>
        </div>
      )}
    </div>
  );
}
