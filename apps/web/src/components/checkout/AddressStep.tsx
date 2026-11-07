"use client";

import { useState } from "react";
import { Icon } from "@/components/Icon";
import { BD_DIVISIONS } from "@/lib/bd";
import type { Address } from "@/lib/types";
import { useStore } from "@/state/store";
import { formatPhone, phoneDigits, phoneValid } from "./cart-math";
import { PhoneField, SelectField, TextField } from "./Fields";

export function addressText(a: Address) {
  return [a.line, a.area, a.district, a.division].filter(Boolean).join(", ");
}

// Saved addresses as radio cards + an animated "add new" form.
export function AddressStep({ selected, onSelect }: { selected: string | null; onSelect: (id: string) => void }) {
  const { addresses, user } = useStore();
  const [adding, setAdding] = useState(addresses.length === 0);
  const sorted = [...addresses].sort((a, b) => Number(!!b.isDefault) - Number(!!a.isDefault));

  return (
    <div className="space-y-4">
      {sorted.length > 0 && (
        <fieldset>
          <legend className="mb-3 text-sm font-bold">Deliver to</legend>
          <div className="grid gap-3" role="radiogroup" aria-label="Saved addresses">
            {sorted.map((a) => {
              const on = a.id === selected;
              return (
                <label key={a.id} className={"co-option " + (on ? "is-active" : "")}>
                  <input
                    type="radio"
                    name="address"
                    className="sr-only"
                    checked={on}
                    onChange={() => onSelect(a.id)}
                  />
                  <span className="chip-icon shrink-0">
                    <Icon name={a.isDefault ? "home" : "pin"} size={18} />
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="flex flex-wrap items-center gap-2 text-sm font-bold">
                      {a.name}
                      {a.isDefault && (
                        <span className="rounded-full bg-accent/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-accent">
                          Default
                        </span>
                      )}
                    </span>
                    <span className="mt-0.5 block text-xs text-ink-2">{a.phone}</span>
                    <span className="mt-1 block text-xs leading-relaxed text-ink-2">{addressText(a)}</span>
                  </span>
                  <span className="co-radio mt-1" aria-hidden />
                </label>
              );
            })}
          </div>
        </fieldset>
      )}

      <div className="acc" data-open={adding}>
        {sorted.length > 0 && (
          <button
            type="button"
            onClick={() => setAdding((v) => !v)}
            aria-expanded={adding}
            aria-controls="new-address"
            className="flex min-h-[52px] w-full items-center gap-3 rounded-2xl bg-surface px-3 text-left text-sm font-bold shadow-[var(--shadow-1)]"
          >
            <span className={"chip-icon shrink-0 " + (adding ? "is-active" : "")}>
              <Icon name="plus" size={18} />
            </span>
            <span className="flex-1">Add a new address</span>
            <Icon name="chevron" size={18} className="acc-chev text-ink-3" />
          </button>
        )}
        <div className="acc-body" id="new-address">
          <div>
            {adding && (
              <NewAddressForm
                defaultName={user?.name ?? ""}
                defaultPhone={user?.phone ?? ""}
                first={sorted.length === 0}
                onSaved={(id) => {
                  onSelect(id);
                  setAdding(false);
                }}
              />
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

function NewAddressForm({
  defaultName,
  defaultPhone,
  first,
  onSaved,
}: {
  defaultName: string;
  defaultPhone: string;
  first: boolean;
  onSaved: (id: string) => void;
}) {
  const { saveAddress } = useStore();
  const [f, setF] = useState({ name: defaultName, division: "", district: "", area: "", line: "" });
  const [digits, setDigits] = useState(phoneDigits(defaultPhone));
  const [isDefault, setIsDefault] = useState(first);
  const [touched, setTouched] = useState(false);
  const [shake, setShake] = useState(0);

  const v = {
    name: f.name.trim().length >= 2,
    phone: phoneValid(digits),
    division: !!f.division,
    district: !!f.district,
    area: f.area.trim().length >= 2,
    line: f.line.trim().length >= 6,
  };
  const ok = Object.values(v).every(Boolean);
  const st = (k: keyof typeof v, typed: boolean) => (v[k] ? "valid" : touched ? "invalid" : typed ? "typing" : "idle") as "valid" | "invalid" | "typing" | "idle";
  const set = (k: keyof typeof f) => (val: string) => setF((s) => ({ ...s, [k]: val }));

  return (
    <form
      noValidate
      key={shake}
      className={"co-card mt-3 grid gap-3 p-4 sm:grid-cols-2 sm:p-5 " + (shake ? "shake" : "")}
      aria-label="New delivery address"
      onSubmit={(e) => {
        e.preventDefault();
        if (!ok) {
          setTouched(true);
          setShake((n) => n + 1);
          return;
        }
        const id = "addr_" + Date.now().toString(36);
        saveAddress({
          id,
          name: f.name.trim(),
          phone: formatPhone(digits),
          division: f.division,
          district: f.district,
          area: f.area.trim(),
          line: f.line.trim(),
          isDefault,
        });
        onSaved(id);
      }}
    >
      <TextField id="na-name" label="Full name" icon="user" autoComplete="name" value={f.name} onChange={set("name")} state={st("name", !!f.name)} msg={touched && !v.name ? "Enter the receiver's name" : undefined} />
      <PhoneField id="na-phone" digits={digits} onDigits={setDigits} touched={touched} />
      <SelectField
        id="na-division"
        label="Division"
        icon="globe"
        placeholder="Choose division"
        value={f.division}
        onChange={(d) => setF((s) => ({ ...s, division: d, district: "" }))}
        options={Object.keys(BD_DIVISIONS)}
        state={v.division ? "valid" : touched ? "invalid" : "idle"}
        msg={touched && !v.division ? "Choose a division" : undefined}
      />
      <SelectField
        id="na-district"
        label="District"
        icon="pin"
        placeholder={f.division ? "Choose district" : "Choose a division first"}
        value={f.district}
        onChange={set("district")}
        options={f.division ? BD_DIVISIONS[f.division] : []}
        disabled={!f.division}
        state={v.district ? "valid" : touched ? "invalid" : "idle"}
        msg={touched && !v.district ? "Choose a district" : undefined}
      />
      <TextField id="na-area" label="Area / thana" icon="grid" autoComplete="address-level3" value={f.area} onChange={set("area")} state={st("area", !!f.area)} msg={touched && !v.area ? "Enter your area or thana" : undefined} />
      <TextField
        id="na-line"
        label="House, road, landmark"
        icon="home"
        autoComplete="street-address"
        value={f.line}
        onChange={set("line")}
        state={st("line", !!f.line)}
        msg={touched && !v.line ? "Add house and road so the rider can find you" : undefined}
      />
      <label className="flex min-h-11 cursor-pointer items-center gap-3 sm:col-span-2">
        <input type="checkbox" checked={isDefault} onChange={(e) => setIsDefault(e.target.checked)} className="h-5 w-5 accent-[var(--accent)]" />
        <span className="text-sm">Set as my default address</span>
      </label>
      <button type="submit" className="btn btn-primary sm:col-span-2">
        <Icon name="check" size={18} stroke={2.4} /> Save and deliver here
      </button>
    </form>
  );
}
