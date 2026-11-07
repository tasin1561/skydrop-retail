"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useStore } from "@/state/store";
import { Icon, Logo } from "@/components/Icon";
import { PhoneField, Spinner, useShake } from "./ui";
import { checkPhone, formatPhone, toE164 } from "./phone";
import { OtpInput } from "./OtpInput";
import { api, apiEnabled, ApiError } from "@/lib/apiClient";

type Step = "phone" | "code" | "name";

// Names of people who signed in on this device, so returning shoppers skip the name step.
// Demo only: the Laravel API will answer "new or returning" after OTP verification.
const KNOWN_KEY = "skydrop-retail:known-phones";
function readKnown(): Record<string, string> {
  try {
    return JSON.parse(localStorage.getItem(KNOWN_KEY) ?? "{}");
  } catch {
    return {};
  }
}
function remember(phone: string, name: string) {
  try {
    localStorage.setItem(KNOWN_KEY, JSON.stringify({ ...readKnown(), [phone]: name }));
  } catch {
    /* storage unavailable */
  }
}

const STEPS: { id: Step; label: string }[] = [
  { id: "phone", label: "Number" },
  { id: "code", label: "Code" },
  { id: "name", label: "Name" },
];

export function LoginFlow({ next }: { next: string }) {
  const router = useRouter();
  const { login, user, ready } = useStore();
  const [step, setStep] = useState<Step>("phone");
  const [digits, setDigits] = useState("");
  const [tried, setTried] = useState(false);
  const [sending, setSending] = useState<"idle" | "busy">("idle");
  const phoneShake = useShake();
  const [name, setName] = useState("");
  const [nameTried, setNameTried] = useState(false);
  const [finishing, setFinishing] = useState(false);
  const [resendIn, setResendIn] = useState(0);
  const [verified, setVerified] = useState(false);
  const [live, setLive] = useState("");
  const [demoCode, setDemoCode] = useState<string | null>(null);
  const [pendingCode, setPendingCode] = useState<string | null>(null);
  const [apiError, setApiError] = useState("");
  const [otpKey, setOtpKey] = useState(0);
  const nameRef = useRef<HTMLInputElement>(null);

  const phoneOk = checkPhone(digits).valid;
  const stepIdx = STEPS.findIndex((s) => s.id === step);

  useEffect(() => {
    if (resendIn <= 0) return;
    const t = setTimeout(() => setResendIn((n) => n - 1), 1000);
    return () => clearTimeout(t);
  }, [resendIn]);

  useEffect(() => {
    if (step === "name") nameRef.current?.focus();
  }, [step]);

  function sendCode(e?: React.FormEvent) {
    e?.preventDefault();
    setTried(true);
    if (!phoneOk) {
      phoneShake.shake();
      setLive("Check your mobile number");
      return;
    }
    setSending("busy");
    setApiError("");
    if (apiEnabled) {
      api
        .requestOtp(toE164(digits))
        .then((res) => {
          setSending("idle");
          setDemoCode(res.demoCode ?? null);
          setStep("code");
          setResendIn(30);
          setLive(`Code sent to +880 ${formatPhone(digits)}`);
        })
        .catch((err: unknown) => {
          setSending("idle");
          phoneShake.shake();
          setApiError(err instanceof ApiError ? err.message : "Couldn't send the code. Check your connection and try again.");
        });
      return;
    }
    // Demo: no SMS gateway yet, so nothing is actually sent. The short wait stands in for
    // the API call; the page says plainly that any 6 digits work.
    setTimeout(() => {
      setSending("idle");
      setStep("code");
      setResendIn(30);
      setLive(`Code step. Enter the 6 digit code for +880 ${formatPhone(digits)}`);
    }, 700);
  }

  function finish(n: string) {
    const phone = toE164(digits);
    setFinishing(true);
    if (apiEnabled && pendingCode) {
      api
        .verifyOtp(phone, pendingCode, n)
        .then((r) => {
          login(r.user, r.token);
          setLive(`Signed in as ${r.user.name}`);
          router.replace(next);
        })
        .catch((err: unknown) => {
          setFinishing(false);
          setApiError(err instanceof ApiError ? err.message : "Couldn't finish signing in. Please try again.");
        });
      return;
    }
    remember(phone, n);
    login({ name: n, phone });
    setLive(`Signed in as ${n}`);
    router.replace(next);
  }

  function onVerified(code: string) {
    if (apiEnabled) {
      setApiError("");
      api
        .verifyOtp(toE164(digits), code)
        .then((r) => {
          setVerified(true);
          setLive("Code verified");
          setTimeout(() => {
            login(r.user, r.token);
            router.replace(next);
          }, 900);
        })
        .catch((err: unknown) => {
          const data = err instanceof ApiError ? (err.data as { errors?: Record<string, string[]> }) : undefined;
          if (data?.errors?.name) {
            // new shopper: code accepted, the API needs a name to create the account
            setVerified(true);
            setPendingCode(code);
            setTimeout(() => setStep("name"), 900);
            return;
          }
          setApiError(err instanceof ApiError ? err.message : "That code didn't work. Try again.");
          setOtpKey((k) => k + 1); // reset the boxes
        });
      return;
    }
    setVerified(true);
    setLive("Code verified");
    const known = readKnown()[toE164(digits)] ?? (user?.phone === toE164(digits) ? user.name : undefined);
    setTimeout(() => {
      if (known) finish(known);
      else setStep("name");
    }, 1300);
  }

  const nameOk = name.trim().length >= 2;

  return (
    <div className="relative mx-auto flex min-h-[80dvh] max-w-md flex-col justify-center px-4 pb-24 pt-6 md:pb-12">
      {/* soft accent glow behind the card */}
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 top-10 -z-10 mx-auto h-64 max-w-sm rounded-full opacity-60"
        style={{ background: "radial-gradient(closest-side, color-mix(in srgb, var(--accent) 22%, transparent), transparent)" }}
      />
      <div className="card rise overflow-hidden p-5 shadow-[var(--shadow-2)] sm:p-7">
        <div className="flex items-center justify-between gap-2">
          <Logo className="text-base" />
          <span className="ac-chip" data-tone="muted">
            Step {String(stepIdx + 1).padStart(2, "0")}/{String(STEPS.length).padStart(2, "0")}
          </span>
        </div>

        {/* progress through the three steps */}
        <ol className="mt-5 grid grid-cols-3 gap-2" aria-label="Sign-in progress">
          {STEPS.map((s, i) => (
            <li key={s.id} className="min-w-0">
              <div className="ac-bar" data-tone={i < stepIdx ? "good" : undefined}>
                <span style={{ ["--p" as string]: i <= stepIdx ? 1 : 0 }} />
              </div>
              <p className={"mt-1 truncate text-[11px] font-semibold " + (i <= stepIdx ? "text-ink" : "text-ink-3")}>
                {i < stepIdx ? "✓ " : ""}
                {s.label}
                <span className="sr-only">{i < stepIdx ? " (done)" : i === stepIdx ? " (current)" : ""}</span>
              </p>
            </li>
          ))}
        </ol>

        <p className="sr-only" aria-live="polite">
          {live}
        </p>

        {step === "phone" && (
          <form key="phone" className="fade-in mt-6" onSubmit={sendCode} noValidate>
            <h1 className="text-2xl font-extrabold tracking-tight">Sign in or create an account</h1>
            <p className="mt-1 text-sm text-ink-2">Track Skydrop deliveries, save addresses and check out faster.</p>
            <div className={"mt-5 " + phoneShake.shakeClass} onAnimationEnd={phoneShake.onAnimationEnd}>
              <PhoneField digits={digits} onChange={setDigits} autoFocus showErrors={tried} />
            </div>
            <button type="submit" className="btn btn-primary mt-3 w-full" aria-busy={sending === "busy"} disabled={sending === "busy"}>
              {sending === "busy" ? (
                <>
                  <Spinner /> Sending code...
                </>
              ) : (
                <>
                  Send code <Icon name="arrow" size={18} className="arrow" />
                </>
              )}
            </button>
            {apiError && (
              <p role="alert" className="mt-3 text-center text-sm font-semibold text-bad">
                {apiError}
              </p>
            )}

            <div className="my-5 flex items-center gap-3 text-xs font-semibold text-ink-3">
              <span className="h-px flex-1 bg-line" /> or <span className="h-px flex-1 bg-line" />
            </div>
            <button type="button" className="btn btn-ghost w-full" disabled aria-describedby="google-soon">
              <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden>
                <circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" strokeWidth="2" />
                <path d="M12 12h8" stroke="currentColor" strokeWidth="2" />
              </svg>
              Continue with Google
              <span id="google-soon" className="ac-chip" data-tone="muted">
                Coming soon
              </span>
            </button>
            <p className="mt-4 flex items-start gap-2 text-xs text-ink-2">
              <Icon name="shield" size={16} className="mt-px shrink-0 text-good" />
              We only use your number for sign-in and delivery updates.
            </p>
          </form>
        )}

        {step === "code" && (
          <div key="code" className="fade-in mt-6">
            <h1 className="text-2xl font-extrabold tracking-tight">Enter the 6 digit code</h1>
            <p className="mt-1 text-sm text-ink-2">
              Sent to <span className="font-semibold text-ink">+880 {formatPhone(digits)}</span>{" "}
              <button
                type="button"
                className="inline-flex min-h-11 items-center px-1 font-semibold text-accent"
                onClick={() => {
                  setStep("phone");
                  setVerified(false);
                }}
                disabled={verified}
              >
                Change
              </button>
            </p>
            <p className="mb-5 inline-flex items-center gap-2 rounded-xl bg-surface-2 px-3 py-2 text-xs font-semibold text-ink-2">
              <Icon name="info" size={15} className="text-accent" />{" "}
              {!apiEnabled ? "Demo: no SMS is sent. Use any 6 digits." : demoCode ? `Test mode code: ${demoCode}` : "Didn't get it? Wait for the timer, then resend."}
            </p>
            <OtpInput key={otpKey} onComplete={onVerified} disabled={verified} />
            {apiError && (
              <p role="alert" className="mt-3 text-center text-sm font-semibold text-bad">
                {apiError}
              </p>
            )}
            {!verified && (
              <div className="mt-5 flex items-center justify-center text-sm text-ink-2">
                {resendIn > 0 ? (
                  <span className="inline-flex min-h-11 items-center gap-1.5">
                    <Icon name="clock" size={15} /> Resend in 00:{String(resendIn).padStart(2, "0")}
                  </span>
                ) : (
                  <button
                    type="button"
                    className="inline-flex min-h-11 items-center gap-1.5 rounded-full px-3 font-semibold text-accent hover:bg-surface-2"
                    onClick={() => {
                      setResendIn(30);
                      if (apiEnabled) api.requestOtp(toE164(digits)).then((res) => setDemoCode(res.demoCode ?? null)).catch(() => {});
                      setLive(apiEnabled ? "A new code is on its way" : "Demo: a new code would be sent now");
                    }}
                  >
                    <Icon name="refresh" size={15} /> Resend code
                  </button>
                )}
              </div>
            )}
          </div>
        )}

        {step === "name" && (
          <form
            key="name"
            className="fade-in mt-6"
            noValidate
            onSubmit={(e) => {
              e.preventDefault();
              setNameTried(true);
              if (nameOk) finish(name.trim().replace(/\s+/g, " "));
            }}
          >
            <span className="chip-icon is-active pop mb-3 h-11 w-11 rounded-2xl" style={{ background: "var(--good)", color: "#fff" }}>
              <Icon name="check" size={22} stroke={2.4} />
            </span>
            <h1 className="text-2xl font-extrabold tracking-tight">Welcome! What should we call you?</h1>
            <p className="mt-1 text-sm text-ink-2">Your rider will use this name at delivery.</p>
            <div className={"field mt-5 " + (nameOk ? "is-valid" : nameTried ? "is-invalid" : "")}>
              <input
                ref={nameRef}
                id="login-name"
                placeholder=" "
                autoComplete="name"
                value={name}
                maxLength={40}
                onChange={(e) => setName(e.target.value)}
                aria-invalid={nameTried && !nameOk}
                aria-describedby="login-name-help"
              />
              <label htmlFor="login-name">Full name</label>
              <span className="field-icon">
                <Icon name="user" size={20} />
              </span>
              {(nameOk || nameTried) && (
                <span className="ac-status pop" data-tone={nameOk ? "good" : "bad"} aria-hidden>
                  <Icon name={nameOk ? "check" : "x"} size={13} stroke={3} />
                </span>
              )}
            </div>
            <p id="login-name-help" className="ac-help" data-tone={nameOk ? "good" : nameTried ? "bad" : undefined}>
              {nameOk ? "Nice to meet you" : nameTried ? "Enter at least 2 letters" : "As it should appear on your parcel"}
              <span className="ml-auto tabular-nums text-ink-3">{name.length}/40</span>
            </p>
            <button type="submit" className="btn btn-primary mt-3 w-full" disabled={finishing} aria-busy={finishing}>
              {finishing ? (
                <>
                  <Spinner /> Signing in...
                </>
              ) : (
                <>
                  Continue <Icon name="arrow" size={18} className="arrow" />
                </>
              )}
            </button>
          </form>
        )}
      </div>
      {ready && user && step === "phone" && (
        <p className="mt-4 text-center text-sm text-ink-2">
          Already signed in as <span className="font-semibold text-ink">{user.name}</span>.{" "}
          <button type="button" className="min-h-11 font-semibold text-accent" onClick={() => router.replace(next)}>
            Go to your account
          </button>
        </p>
      )}
    </div>
  );
}
