"use client";

import { Icon } from "@/components/Icon";
import { formatPhone, nextPhoneDigits, phoneState } from "./cart-math";
import { useT } from "@/i18n/client";

export type FieldState = "idle" | "typing" | "valid" | "invalid";

// Floating-label field wrapper around the shared `.field` CSS, with an inline status icon and message.
export function TextField({
  id,
  label,
  icon,
  value,
  onChange,
  onBlur,
  state = "idle",
  msg,
  type = "text",
  inputMode,
  autoComplete,
  maxLength,
}: {
  id: string;
  label: string;
  icon: string;
  value: string;
  onChange: (v: string) => void;
  onBlur?: () => void;
  state?: FieldState;
  msg?: string;
  type?: string;
  inputMode?: React.HTMLAttributes<HTMLInputElement>["inputMode"];
  autoComplete?: string;
  maxLength?: number;
}) {
  const cls = state === "valid" ? "is-valid" : state === "invalid" ? "is-invalid" : "";
  return (
    <div>
      <div className={"field " + cls}>
        <input
          id={id}
          type={type}
          value={value}
          placeholder=" "
          inputMode={inputMode}
          autoComplete={autoComplete}
          maxLength={maxLength}
          onChange={(e) => onChange(e.target.value)}
          onBlur={onBlur}
          aria-invalid={state === "invalid"}
          aria-describedby={msg ? id + "-msg" : undefined}
          className="pr-10"
        />
        <label htmlFor={id}>{label}</label>
        <Icon name={icon} size={18} className="field-icon" />
        <StatusIcon state={state} />
      </div>
      <FieldMsg id={id} state={state} msg={msg} />
    </div>
  );
}

export function StatusIcon({ state }: { state: FieldState }) {
  return (
    <span className="co-status">
      {state === "valid" && <Icon key="v" name="check" size={18} stroke={2.6} className="text-good" />}
      {state === "invalid" && <Icon key="i" name="info" size={18} stroke={2.2} className="text-bad" />}
    </span>
  );
}

export function FieldMsg({ id, state, msg }: { id: string; state: FieldState; msg?: string }) {
  if (!msg) return null;
  return (
    <p
      id={id + "-msg"}
      aria-live="polite"
      className={
        "mt-1 flex items-center gap-1 pl-1 text-xs " + (state === "invalid" ? "text-bad" : state === "valid" ? "text-good" : "text-ink-3")
      }
    >
      {msg}
    </p>
  );
}

/** +880 phone input: formats as you type and validates Bangladeshi mobile numbers. */
export function PhoneField({
  id,
  digits,
  onDigits,
  touched,
  onBlur,
}: {
  id: string;
  digits: string;
  onDigits: (d: string) => void;
  touched?: boolean;
  onBlur?: () => void;
}) {
  const s = phoneState(digits);
  const { t } = useT();
  const state: FieldState = s === "typing" && touched ? "invalid" : s === "idle" && touched ? "invalid" : s;
  const msg =
    state === "valid"
      ? t("phone.valid")
      : state === "invalid"
        ? digits
          ? t("phone.use11")
          : t("phone.required")
        : digits
          ? t("phone.more", { count: 10 - digits.length })
          : t("phone.updates");
  return (
    <TextField
      id={id}
      label={t("phone.label")}
      icon="phone"
      type="tel"
      inputMode="tel"
      autoComplete="tel-national"
      value={formatPhone(digits)}
      onChange={(v) => onDigits(nextPhoneDigits(digits, v))}
      onBlur={onBlur}
      state={state}
      msg={msg}
    />
  );
}

export function SelectField({
  id,
  label,
  icon,
  value,
  onChange,
  options,
  optionLabel,
  disabled,
  state = "idle",
  msg,
  placeholder,
}: {
  id: string;
  label: string;
  icon: string;
  value: string;
  onChange: (v: string) => void;
  options: string[];
  /** Display text for an option (the value stays as given), e.g. Bangla district names. */
  optionLabel?: (o: string) => string;
  disabled?: boolean;
  state?: FieldState;
  msg?: string;
  placeholder: string;
}) {
  const cls = state === "valid" ? "is-valid" : state === "invalid" ? "is-invalid" : "";
  return (
    <div>
      <div className={"field " + cls}>
        <select
          id={id}
          value={value}
          disabled={disabled}
          onChange={(e) => onChange(e.target.value)}
          aria-invalid={state === "invalid"}
          aria-describedby={msg ? id + "-msg" : undefined}
          className="pr-10 disabled:opacity-60"
          style={{
            borderColor:
              state === "valid"
                ? "color-mix(in srgb, var(--good) 60%, var(--line))"
                : state === "invalid"
                  ? "color-mix(in srgb, var(--bad) 60%, var(--line))"
                  : undefined,
          }}
        >
          <option value="" disabled>
            {placeholder}
          </option>
          {options.map((o) => (
            <option key={o} value={o}>
              {optionLabel ? optionLabel(o) : o}
            </option>
          ))}
        </select>
        <label htmlFor={id}>{label}</label>
        <Icon name={icon} size={18} className="field-icon" />
        <span className="co-status text-ink-3">
          {state === "valid" ? <Icon name="check" size={18} stroke={2.6} className="text-good" /> : <Icon name="chevron" size={18} />}
        </span>
      </div>
      <FieldMsg id={id} state={state} msg={msg} />
    </div>
  );
}
