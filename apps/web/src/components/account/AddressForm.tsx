"use client";

import { useState } from "react";
import type { Address } from "@/lib/types";
import { BD_DIVISIONS } from "@/lib/bd";
import { Icon } from "@/components/Icon";
import { PhoneField, Spinner, useShake } from "./ui";
import { checkPhone, phoneDigits, toE164 } from "./phone";

type Draft = Omit<Address, "id" | "phone"> & { phoneDigits: string };

export function AddressForm({
  initial,
  defaultName,
  defaultPhone,
  canUnsetDefault,
  onSave,
  onCancel,
}: {
  initial?: Address;
  defaultName?: string;
  defaultPhone?: string;
  canUnsetDefault: boolean;
  onSave: (a: Address) => void;
  onCancel: () => void;
}) {
  const [d, setD] = useState<Draft>(() => ({
    name: initial?.name ?? defaultName ?? "",
    phoneDigits: phoneDigits(initial?.phone ?? defaultPhone ?? ""),
    division: initial?.division ?? "",
    district: initial?.district ?? "",
    area: initial?.area ?? "",
    line: initial?.line ?? "",
    isDefault: initial?.isDefault ?? !canUnsetDefault,
  }));
  const [tried, setTried] = useState(false);
  const [state, setState] = useState<"idle" | "busy" | "done">("idle");
  const { shakeClass, shake, onAnimationEnd } = useShake();
  const set = <K extends keyof Draft>(k: K, v: Draft[K]) => setD((x) => ({ ...x, [k]: v }));

  const errs = {
    name: d.name.trim().length < 2 ? "Enter the receiver's name" : "",
    phone: checkPhone(d.phoneDigits).valid ? "" : "Enter a valid mobile number",
    division: d.division ? "" : "Choose a division",
    district: d.district ? "" : "Choose a district",
    area: d.area.trim().length < 2 ? "Enter your area or thana" : "",
    line: d.line.trim().length < 6 ? "Add house, road and landmark" : "",
  };
  const firstErr = Object.entries(errs).find(([, v]) => v);
  const doneCount = Object.values(errs).filter((v) => !v).length;
  const districts = d.division ? BD_DIVISIONS[d.division] ?? [] : [];

  function submit(e: React.FormEvent) {
    e.preventDefault();
    setTried(true);
    if (firstErr) {
      shake();
      document.getElementById("addr-" + firstErr[0])?.focus();
      return;
    }
    setState("busy");
    const a: Address = {
      id: initial?.id ?? "addr_" + Date.now().toString(36),
      name: d.name.trim().replace(/\s+/g, " "),
      phone: toE164(d.phoneDigits),
      division: d.division,
      district: d.district,
      area: d.area.trim(),
      line: d.line.trim(),
      isDefault: d.isDefault,
    };
    // Saved locally (synchronous); the short pause lets the done state register visually.
    onSave(a);
    setState("done");
  }

  const status = (k: keyof typeof errs, filled: boolean) => {
    if (!errs[k]) return filled ? "is-valid" : "";
    return tried ? "is-invalid" : "";
  };

  return (
    <form onSubmit={submit} noValidate className={"space-y-3 " + shakeClass} onAnimationEnd={onAnimationEnd}>
      <div className="flex items-center gap-2 text-xs font-semibold text-ink-2" aria-live="polite">
        <div className="ac-bar flex-1" data-tone={doneCount === 6 ? "good" : undefined}>
          <span style={{ ["--p" as string]: doneCount / 6 }} />
        </div>
        <span className="tabular-nums">{String(doneCount).padStart(2, "0")}/06 complete</span>
      </div>

      <TextField id="addr-name" label="Receiver's name" icon="user" value={d.name} onChange={(v) => set("name", v)} cls={status("name", !!d.name)} error={tried ? errs.name : ""} autoComplete="name" max={40} />
      <PhoneField id="addr-phone" label="Receiver's mobile" digits={d.phoneDigits} onChange={(v) => set("phoneDigits", v)} showErrors={tried} />

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <SelectField
          id="addr-division"
          label="Division"
          icon="globe"
          value={d.division}
          options={Object.keys(BD_DIVISIONS)}
          onChange={(v) => setD((x) => ({ ...x, division: v, district: "" }))}
          cls={status("division", !!d.division)}
          error={tried ? errs.division : ""}
        />
        <SelectField
          id="addr-district"
          label={d.division ? "District" : "District (choose division first)"}
          icon="pin"
          value={d.district}
          options={districts}
          disabled={!d.division}
          onChange={(v) => set("district", v)}
          cls={status("district", !!d.district)}
          error={tried ? errs.district : ""}
        />
      </div>
      <TextField id="addr-area" label="Area / thana" icon="grid" value={d.area} onChange={(v) => set("area", v)} cls={status("area", !!d.area)} error={tried ? errs.area : ""} autoComplete="address-level3" max={50} />
      <TextField
        id="addr-line"
        label="House, road, landmark"
        icon="home"
        value={d.line}
        onChange={(v) => set("line", v)}
        cls={status("line", !!d.line)}
        error={tried ? errs.line : ""}
        autoComplete="street-address"
        max={120}
        multiline
      />

      <label className={"ac-check flex min-h-11 cursor-pointer items-center gap-3 rounded-2xl bg-surface-2 p-3 " + (d.isDefault ? "is-active" : "")}>
        <span className="chip-icon">
          <Icon name="star" size={18} />
        </span>
        <span className="flex-1 text-sm">
          <span className="block font-semibold">Default address</span>
          <span className="block text-xs text-ink-2">
            {canUnsetDefault ? "Use this for checkout automatically" : "To change it, set another address as default"}
          </span>
        </span>
        <input
          type="checkbox"
          className="sr-only"
          checked={!!d.isDefault}
          disabled={!canUnsetDefault && !!d.isDefault}
          onChange={(e) => set("isDefault", e.target.checked)}
        />
        <span className="ac-chip" data-tone={d.isDefault ? undefined : "muted"} aria-hidden>
          {d.isDefault ? "ON" : "OFF"}
        </span>
      </label>

      <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
        <button type="button" className="btn btn-ghost" onClick={onCancel}>
          Cancel
        </button>
        <button type="submit" className="btn btn-primary" disabled={state !== "idle"} aria-busy={state === "busy"}>
          {state === "busy" ? (
            <>
              <Spinner /> Saving...
            </>
          ) : state === "done" ? (
            <>
              <svg width="18" height="18" viewBox="0 0 24 24" className="draw-check" aria-hidden>
                <path d="M5 12l5 5 9-11" fill="none" stroke="currentColor" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
              Saved
            </>
          ) : (
            <>
              <Icon name="check" size={18} /> {initial ? "Save changes" : "Save address"}
            </>
          )}
        </button>
      </div>
    </form>
  );
}

function Help({ id, error }: { id: string; error: string }) {
  return (
    <p id={id + "-help"} className="ac-help" data-tone={error ? "bad" : undefined}>
      {error && (
        <>
          <Icon name="info" size={13} /> {error}
        </>
      )}
    </p>
  );
}

function TextField({
  id,
  label,
  icon,
  value,
  onChange,
  cls,
  error,
  autoComplete,
  max,
  multiline,
}: {
  id: string;
  label: string;
  icon: string;
  value: string;
  onChange: (v: string) => void;
  cls: string;
  error: string;
  autoComplete?: string;
  max: number;
  multiline?: boolean;
}) {
  const props = {
    id,
    placeholder: " ",
    value,
    maxLength: max,
    autoComplete,
    "aria-invalid": !!error,
    "aria-describedby": id + "-help",
  };
  return (
    <div>
      <div className={"field " + cls}>
        {multiline ? (
          <textarea {...props} rows={2} onChange={(e) => onChange(e.target.value)} />
        ) : (
          <input {...props} onChange={(e) => onChange(e.target.value)} />
        )}
        <label htmlFor={id}>{label}</label>
        <span className="field-icon">
          <Icon name={icon} size={20} />
        </span>
        {cls && (
          <span className="ac-status pop" data-tone={cls === "is-valid" ? "good" : "bad"} aria-hidden>
            <Icon name={cls === "is-valid" ? "check" : "x"} size={13} stroke={3} />
          </span>
        )}
      </div>
      <Help id={id} error={error} />
    </div>
  );
}

function SelectField({
  id,
  label,
  icon,
  value,
  options,
  onChange,
  cls,
  error,
  disabled,
}: {
  id: string;
  label: string;
  icon: string;
  value: string;
  options: string[];
  onChange: (v: string) => void;
  cls: string;
  error: string;
  disabled?: boolean;
}) {
  return (
    <div>
      <div className={"field " + cls}>
        <select
          id={id}
          value={value}
          disabled={disabled}
          onChange={(e) => onChange(e.target.value)}
          aria-invalid={!!error}
          aria-describedby={id + "-help"}
          style={cls === "is-invalid" ? { borderColor: "color-mix(in srgb, var(--bad) 60%, var(--line))" } : cls === "is-valid" ? { borderColor: "color-mix(in srgb, var(--good) 60%, var(--line))" } : undefined}
        >
          <option value="" disabled>
            Select
          </option>
          {options.map((o) => (
            <option key={o} value={o}>
              {o}
            </option>
          ))}
        </select>
        <label htmlFor={id}>{label}</label>
        <span className="field-icon">
          <Icon name={icon} size={20} />
        </span>
        <Icon name="chevron" size={18} className="ac-select-chev" />
      </div>
      <Help id={id} error={error} />
    </div>
  );
}
