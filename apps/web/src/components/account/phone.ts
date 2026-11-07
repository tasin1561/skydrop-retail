// Bangladesh mobile numbers: +880 1[3-9]XX-XXXXXX (10 digits after the country code).

/** Keep only the 10 national digits, dropping a typed 880 / 0 prefix. */
export function phoneDigits(raw: string) {
  let d = raw.replace(/\D/g, "");
  if (d.startsWith("880")) d = d.slice(3);
  if (d.startsWith("0")) d = d.slice(1);
  return d.slice(0, 10);
}

/** "1712345678" -> "1712-345678" (formats progressively as you type). */
export function formatPhone(digits: string) {
  return digits.length > 4 ? `${digits.slice(0, 4)}-${digits.slice(4)}` : digits;
}

export type PhoneCheck = { tone: "idle" | "good" | "warn" | "bad"; message: string; valid: boolean };

export function checkPhone(digits: string): PhoneCheck {
  if (!digits) return { tone: "idle", message: "Bangladesh mobile number, we will text you a code", valid: false };
  if (digits[0] !== "1") return { tone: "bad", message: "Mobile numbers start with 1 after +880 (e.g. 1712-345678)", valid: false };
  if (digits.length >= 2 && !/^1[3-9]/.test(digits))
    return { tone: "bad", message: "Use a valid operator code: 13 to 19", valid: false };
  if (digits.length < 10) {
    const left = 10 - digits.length;
    return { tone: "warn", message: `${left} more digit${left === 1 ? "" : "s"}`, valid: false };
  }
  return { tone: "good", message: "Looks good", valid: true };
}

export function toE164(digits: string) {
  return "+880" + digits;
}

/** Pretty print a stored phone ("+8801712345678" -> "+880 1712-345678"). */
export function prettyPhone(stored: string) {
  const d = phoneDigits(stored);
  return d ? "+880 " + formatPhone(d) : stored;
}
