// Shared Turnstile token slot: the widget (components/security/Turnstile.tsx) writes it, apiClient.requestOtp() reads it.
let token: string | null = null;
let resetFn: (() => void) | null = null;

export function setTurnstileToken(t: string | null) {
  token = t;
}

export function setTurnstileReset(fn: (() => void) | null) {
  resetFn = fn;
}

/** Token from the last solved challenge (single use: cleared and the widget reset once read). */
export function takeTurnstileToken(): string | undefined {
  const t = token ?? undefined;
  token = null;
  resetFn?.();
  return t;
}
