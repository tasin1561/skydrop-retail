"use client";

import { useId, useState } from "react";
import { Icon } from "@/components/Icon";
import { couponHint, findCoupon, useCoupon } from "./cart-math";

// Coupon input with live staged validation; an applied code becomes a removable success chip.
export function CouponField() {
  const [coupon, setCoupon] = useCoupon();
  const [value, setValue] = useState("");
  const [shake, setShake] = useState(0);
  const [error, setError] = useState("");
  const id = useId();
  const hint = couponHint(value);

  if (coupon) {
    return (
      <div className="co-chip flex items-center gap-3 rounded-2xl p-2.5 pl-3" role="status">
        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-good text-white">
          <svg viewBox="0 0 24 24" width="18" height="18" className="draw-check" aria-hidden="true">
            <path d="M5 12l5 5 9-11" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-extrabold tracking-wide">{coupon.code} applied</p>
          <p className="text-xs text-ink-2">{coupon.label}</p>
        </div>
        <button
          type="button"
          onClick={() => setCoupon("")}
          className="grid h-11 w-11 place-items-center rounded-full text-ink-2 hover:bg-surface"
          aria-label={`Remove coupon ${coupon.code}`}
        >
          <Icon name="x" size={16} />
        </button>
      </div>
    );
  }

  const apply = () => {
    const c = findCoupon(value);
    if (c) {
      setCoupon(c.code);
      setValue("");
      setError("");
    } else {
      setError(value.trim() ? `"${value.trim().toUpperCase()}" isn't a valid code` : "Enter a coupon code");
      setShake((n) => n + 1);
    }
  };

  const state = error || hint === "unknown" ? "invalid" : hint === "match" ? "valid" : "";
  const msg =
    error ||
    (hint === "match"
      ? `Code found: ${findCoupon(value)?.label}. Tap Apply.`
      : hint === "unknown"
        ? "No coupon matches this code"
        : "Try SKYDROP10 or FREESHIP");

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        apply();
      }}
      noValidate
    >
      <div key={shake} className={"flex items-start gap-2 " + (shake ? "shake" : "")}>
        <div className={"field flex-1 " + (state ? `is-${state}` : "")}>
          <input
            id={id}
            value={value}
            onChange={(e) => {
              setValue(e.target.value.toUpperCase().replace(/\s/g, ""));
              setError("");
            }}
            placeholder=" "
            autoCapitalize="characters"
            autoComplete="off"
            spellCheck={false}
            aria-describedby={id + "-msg"}
            aria-invalid={state === "invalid"}
            className="uppercase tracking-wider"
          />
          <label htmlFor={id}>Coupon code</label>
          <Icon name="tag" size={18} className="field-icon" />
          <span className="co-status">
            {state === "valid" && <Icon key="v" name="check" size={18} stroke={2.6} className="text-good" />}
            {state === "invalid" && <Icon key="i" name="info" size={18} stroke={2.2} className="text-bad" />}
          </span>
        </div>
        <button type="submit" className="btn btn-ghost h-[52px] shrink-0 rounded-2xl px-4">
          Apply
        </button>
      </div>
      <p
        id={id + "-msg"}
        aria-live="polite"
        className={"mt-1.5 pl-1 text-xs " + (state === "invalid" ? "text-bad" : state === "valid" ? "text-good" : "text-ink-3")}
      >
        {msg}
      </p>
    </form>
  );
}
