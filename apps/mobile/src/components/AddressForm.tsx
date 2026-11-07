// Address form with cascading Bangladesh division -> district pickers (data from lib/bd.ts).
import { useState } from "react";
import { StyleSheet, TextInput, View } from "react-native";
import { BD_DIVISIONS } from "@/lib/bd";
import type { Address } from "@/lib/types";
import { checkPhone, formatPhone, phoneDigits, toE164 } from "@/lib/phone";
import { radius, useTheme } from "@/theme";
import { haptic } from "@/lib/haptics";
import { Icon } from "./Icon";
import { Sheet } from "./Sheet";
import { Button, T, Tap } from "./ui";

export type AddressDraft = Omit<Address, "id"> & { id?: string };

export function emptyAddress(name = "", phone = ""): AddressDraft {
  return { name, phone, division: "", district: "", area: "", line: "", isDefault: false };
}

export function addressValid(a: AddressDraft) {
  return !!(a.name.trim() && checkPhone(phoneDigits(a.phone)).valid && a.division && a.district && a.area.trim() && a.line.trim());
}

export function AddressForm({ value, onChange }: { value: AddressDraft; onChange: (a: AddressDraft) => void }) {
  const { c } = useTheme();
  const [picker, setPicker] = useState<"division" | "district" | null>(null);
  const digits = phoneDigits(value.phone);
  const phoneCheck = checkPhone(digits);
  const set = (patch: Partial<AddressDraft>) => onChange({ ...value, ...patch });

  const field = (label: string, v: string, onText: (t: string) => void, extra: Partial<React.ComponentProps<typeof TextInput>> = {}) => (
    <View style={{ gap: 6 }}>
      <T v="small" style={{ fontWeight: "700" }}>
        {label}
      </T>
      <TextInput
        value={v}
        onChangeText={onText}
        placeholderTextColor={c.ink3}
        accessibilityLabel={label}
        style={[styles.input, { color: c.ink, backgroundColor: c.surface, borderColor: c.line }]}
        {...extra}
      />
    </View>
  );

  const select = (label: string, v: string, placeholder: string, onPress: () => void, disabled?: boolean) => (
    <View style={{ gap: 6, flex: 1 }}>
      <T v="small" style={{ fontWeight: "700" }}>
        {label}
      </T>
      <Tap
        onPress={onPress}
        disabled={disabled}
        accessibilityLabel={`${label}: ${v || "not chosen"}`}
        scaleTo={0.98}
        style={[styles.input, styles.select, { backgroundColor: c.surface, borderColor: c.line, opacity: disabled ? 0.5 : 1 }]}
      >
        <T v="body" tone={v ? "ink" : "ink3"} numberOfLines={1} style={{ flex: 1 }}>
          {v || placeholder}
        </T>
        <Icon name="chevron-down" size={18} color={c.ink3} />
      </Tap>
    </View>
  );

  const options = picker === "division" ? Object.keys(BD_DIVISIONS) : picker === "district" ? BD_DIVISIONS[value.division] ?? [] : [];
  const current = picker === "division" ? value.division : value.district;

  return (
    <View style={{ gap: 14 }}>
      {field("Full name", value.name, (t) => set({ name: t }), { placeholder: "Who receives the parcel", textContentType: "name", autoComplete: "name" })}
      <View style={{ gap: 6 }}>
        <T v="small" style={{ fontWeight: "700" }}>
          Mobile number
        </T>
        <View style={[styles.input, styles.select, { backgroundColor: c.surface, borderColor: digits && phoneCheck.tone === "bad" ? c.bad : c.line }]}>
          <T v="body" style={{ fontWeight: "700" }}>
            +880
          </T>
          <TextInput
            value={formatPhone(digits)}
            onChangeText={(t) => set({ phone: toE164(phoneDigits(t)) })}
            placeholder="1712-345678"
            placeholderTextColor={c.ink3}
            keyboardType="phone-pad"
            maxLength={11}
            accessibilityLabel="Mobile number after +880"
            style={{ flex: 1, color: c.ink, fontSize: 16, minHeight: 48 }}
          />
        </View>
        {!!digits && (
          <T v="tiny" style={{ color: phoneCheck.tone === "bad" ? c.bad : phoneCheck.tone === "good" ? c.good : c.ink3 }}>
            {phoneCheck.message}
          </T>
        )}
      </View>
      <View style={{ flexDirection: "row", gap: 10 }}>
        {select("Division", value.division, "Choose", () => setPicker("division"))}
        {select("District", value.district, value.division ? "Choose" : "Division first", () => setPicker("district"), !value.division)}
      </View>
      {field("Area / Thana", value.area, (t) => set({ area: t }), { placeholder: "e.g. Dhanmondi" })}
      {field("House, road, landmark", value.line, (t) => set({ line: t }), { placeholder: "House 12, Road 5, near the mosque", multiline: true, textContentType: "fullStreetAddress" })}

      <Sheet open={!!picker} onClose={() => setPicker(null)} title={picker === "division" ? "Choose division" : `Districts in ${value.division}`}>
        <View style={{ gap: 6 }}>
          {options.map((o) => {
            const on = o === current;
            return (
              <Tap
                key={o}
                onPress={() => {
                  haptic.tap();
                  if (picker === "division") set({ division: o, district: o === value.division ? value.district : "" });
                  else set({ district: o });
                  setPicker(picker === "division" && o !== value.division ? "district" : null);
                }}
                accessibilityRole="radio"
                accessibilityState={{ checked: on }}
                accessibilityLabel={o}
                scaleTo={0.98}
                style={[styles.option, { backgroundColor: on ? c.surface2 : "transparent" }]}
              >
                <T v="body" style={{ flex: 1, fontWeight: on ? "800" : "500" }}>
                  {o}
                </T>
                {on && <Icon name="check" size={18} color={c.brand} />}
              </Tap>
            );
          })}
        </View>
      </Sheet>
    </View>
  );
}

export function AddressSaveButton({ value, onSave, busy }: { value: AddressDraft; onSave: () => void; busy?: boolean }) {
  return <Button label="Save address" icon="check" onPress={onSave} busy={busy} disabled={!addressValid(value)} />;
}

const styles = StyleSheet.create({
  input: { minHeight: 52, borderWidth: 1.5, borderRadius: radius.md, paddingHorizontal: 14, fontSize: 16, paddingVertical: 10 },
  select: { flexDirection: "row", alignItems: "center", gap: 8, paddingVertical: 0 },
  option: { flexDirection: "row", alignItems: "center", minHeight: 50, paddingHorizontal: 14, borderRadius: 12 },
});
