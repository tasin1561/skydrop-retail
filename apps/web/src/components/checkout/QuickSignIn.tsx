"use client";

import { useState } from "react";
import { Icon } from "@/components/Icon";
import { useStore } from "@/state/store";
import { apiEnabled } from "@/lib/apiClient";
import { formatPhone, phoneValid } from "./cart-math";
import { PhoneField, TextField } from "./Fields";

// Inline sign-in for guests: name + phone, no OTP (the /login page owns OTP).
export function QuickSignIn() {
  const { login } = useStore();
  const [name, setName] = useState("");
  const [digits, setDigits] = useState("");
  const [touched, setTouched] = useState({ name: false, phone: false });
  const [shake, setShake] = useState(0);

  const nameOk = name.trim().length >= 2;
  const ok = nameOk && phoneValid(digits);

  if (apiEnabled) {
    // With the live API, accounts are verified by SMS code on the sign-in page.
    return (
      <section className="co-card p-4 sm:p-5">
        <div className="flex items-start gap-3">
          <span className="chip-icon is-active shrink-0">
            <Icon name="user" size={18} />
          </span>
          <div className="min-w-0 flex-1">
            <h2 className="text-base font-extrabold">Sign in to continue</h2>
            <p className="text-sm text-ink-2">We&apos;ll text you a 6 digit code. Your cart stays as it is.</p>
            <a href="/login?next=/checkout" className="btn btn-primary mt-3 w-full">
              Sign in with phone <Icon name="arrow" size={18} className="arrow" />
            </a>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="co-card p-4 sm:p-5" aria-labelledby="qs-h">
      <div className="mb-4 flex items-start gap-3">
        <span className="chip-icon is-active shrink-0">
          <Icon name="user" size={18} />
        </span>
        <div>
          <h2 id="qs-h" className="text-base font-extrabold">
            Quick sign-in to continue
          </h2>
          <p className="text-xs text-ink-2">We use your number for order updates and Skydrop delivery calls.</p>
        </div>
      </div>
      <form
        noValidate
        key={shake}
        className={"grid gap-3 " + (shake ? "shake" : "")}
        onSubmit={(e) => {
          e.preventDefault();
          if (!ok) {
            setTouched({ name: true, phone: true });
            setShake((n) => n + 1);
            return;
          }
          login({ name: name.trim(), phone: formatPhone(digits) });
        }}
      >
        <TextField
          id="qs-name"
          label="Your name"
          icon="user"
          autoComplete="name"
          value={name}
          onChange={setName}
          onBlur={() => setTouched((t) => ({ ...t, name: true }))}
          state={nameOk ? "valid" : touched.name ? "invalid" : "idle"}
          msg={touched.name && !nameOk ? "Please enter your name" : undefined}
        />
        <PhoneField
          id="qs-phone"
          digits={digits}
          onDigits={setDigits}
          touched={touched.phone}
          onBlur={() => setTouched((t) => ({ ...t, phone: true }))}
        />
        <button type="submit" className="btn btn-primary mt-1 w-full">
          Continue as {nameOk ? name.trim().split(" ")[0] : "guest"} <Icon name="arrow" size={18} className="arrow" />
        </button>
        <p className="text-center text-xs text-ink-3">
          Have an account?{" "}
          <a href="/login?next=/checkout" className="font-semibold text-accent">
            Sign in with OTP
          </a>
        </p>
      </form>
    </section>
  );
}
