"use client";

import { useId, useState } from "react";
import { Icon } from "@/components/Icon";
import { API_URL, apiEnabled } from "@/lib/apiClient";
import { useStore } from "@/state/store";
import { useFeature } from "@/components/features/FeaturesProvider";
import { couponHint, couponLabel, findCoupon, useCoupon, type Coupon } from "./cart-math";
import { useT } from "@/i18n/client";

type QuoteError = { message?: string; errors?: Record<string, string[]> };

/**
 * API mode: validate with POST /checkout/quote (server prices, so admin-created coupons and minimum-spend rules work).
 * Returns the coupon, an error message for the shopper, or null when the API could not answer (use the demo list).
 */
async function validateWithApi(code: string, items: { productId: string; variant?: string; qty: number; unitBdt: number }[], token: string | null) {
  try {
    const res = await fetch(`${API_URL}/checkout/quote`, {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: JSON.stringify({ items: items.map((i) => ({ productId: i.productId, variant: i.variant, qty: i.qty, unitBdt: i.unitBdt })), coupon: code }),
    });
    const data = (await res.json().catch(() => ({}))) as { coupon?: Coupon | null } & QuoteError;
    if (res.ok && data.coupon) return { coupon: data.coupon };
    if (data.errors?.coupon?.[0]) return { error: data.errors.coupon[0] };
    return null;
  } catch {
    return null;
  }
}

// Coupon input with live staged validation; an applied code becomes a removable success chip.
export function CouponField() {
  const enabled = useFeature("coupons");
  return enabled ? <CouponFieldInner /> : null;
}

function CouponFieldInner() {
  const [coupon, setCoupon] = useCoupon();
  const [value, setValue] = useState("");
  const [shake, setShake] = useState(0);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const { cart, token } = useStore();
  const { t } = useT();
  const id = useId();
  const rawHint = couponHint(value);
  // With the API any code may exist, so an unknown code is not "invalid" until the server says so.
  const hint = apiEnabled && rawHint === "unknown" ? "partial" : rawHint;

  if (coupon) {
    return (
      <div className="co-chip flex items-center gap-3 rounded-2xl p-2.5 pl-3" role="status">
        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-good text-white">
          <svg viewBox="0 0 24 24" width="18" height="18" className="draw-check" aria-hidden="true">
            <path d="M5 12l5 5 9-11" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-extrabold tracking-wide">{t("coupon.applied", { code: coupon.code })}</p>
          <p className="text-xs text-ink-2">{couponLabel(coupon, t)}</p>
        </div>
        <button
          type="button"
          onClick={() => setCoupon("")}
          className="grid h-11 w-11 place-items-center rounded-full text-ink-2 hover:bg-surface"
          aria-label={t("coupon.remove", { code: coupon.code })}
        >
          <Icon name="x" size={16} />
        </button>
      </div>
    );
  }

  const fail = (msg: string) => {
    setError(msg);
    setShake((n) => n + 1);
  };

  const apply = async () => {
    const code = value.trim().toUpperCase();
    if (!code) return fail(t("coupon.enter"));
    if (busy) return;
    if (apiEnabled && cart.length) {
      setBusy(true);
      const r = await validateWithApi(code, cart, token);
      setBusy(false);
      if (r?.coupon) {
        setCoupon(r.coupon.code, r.coupon);
        setValue("");
        setError("");
        return;
      }
      if (r?.error) return fail(r.error);
    }
    const c = findCoupon(code); // demo list (and fallback when the API is unreachable)
    if (c) {
      setCoupon(c.code);
      setValue("");
      setError("");
    } else {
      fail(t("coupon.invalid", { code }));
    }
  };

  const state = error || hint === "unknown" ? "invalid" : hint === "match" ? "valid" : "";
  const msg =
    error ||
    (hint === "match"
      ? t("coupon.found", { label: couponLabel(findCoupon(value)!, t) })
      : hint === "unknown"
        ? t("coupon.noMatch")
        : apiEnabled && value
          ? t("coupon.tapCheck")
          : t("coupon.try"));

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
          <label htmlFor={id}>{t("coupon.label")}</label>
          <Icon name="tag" size={18} className="field-icon" />
          <span className="co-status">
            {state === "valid" && <Icon key="v" name="check" size={18} stroke={2.6} className="text-good" />}
            {state === "invalid" && <Icon key="i" name="info" size={18} stroke={2.2} className="text-bad" />}
          </span>
        </div>
        <button type="submit" disabled={busy} aria-busy={busy} className="btn btn-ghost h-[52px] shrink-0 rounded-2xl px-4">
          {busy ? t("coupon.checking") : t("coupon.apply")}
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
