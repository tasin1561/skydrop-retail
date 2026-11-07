"use client";

import { useId, useState } from "react";
import { Icon } from "@/components/Icon";
import { useToast } from "@/components/Toast";
import { ApiError, apiEnabled } from "@/lib/apiClient";
import { formatGiftCode, money } from "@/lib/money";
import "./money.css";
import { useT } from "@/i18n/client";

/** Redeem a gift card code into the Skydrop Wallet (POST /gift-cards/redeem). */
export function RedeemGiftCard({ onRedeemed, autoFocus }: { onRedeemed?: (balanceBdt: number) => void; autoFocus?: boolean }) {
  const id = useId();
  const toast = useToast();
  const { t, bdt } = useT();
  const [code, setCode] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const ready = code.replace(/-/g, "").length === 16;

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!ready || busy) return;
    if (!apiEnabled) {
      setError(t("rg.notConnected"));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const r = await money.redeemGiftCard(code);
      toast({ kind: "success", title: t("rg.added", { amount: bdt(r.amountBdt) }), body: t("rg.newBalance", { amount: bdt(r.balanceBdt) }) });
      setCode("");
      onRedeemed?.(r.balanceBdt);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("rg.failed"));
    } finally {
      setBusy(false);
    }
  };

  return (
    <form onSubmit={submit} className="space-y-2" noValidate>
      <label htmlFor={id} className="block text-sm font-bold">
        {t("rg.label")}
      </label>
      <div className="flex gap-2">
        <input
          id={id}
          autoFocus={autoFocus}
          value={code}
          onChange={(e) => {
            setCode(formatGiftCode(e.target.value));
            setError(null);
          }}
          placeholder="XXXX-XXXX-XXXX-XXXX"
          autoComplete="off"
          autoCapitalize="characters"
          spellCheck={false}
          inputMode="text"
          aria-invalid={!!error}
          aria-describedby={id + "-msg"}
          className="min-h-11 min-w-0 flex-1 rounded-2xl border border-line bg-surface px-3 font-mono text-[15px] tracking-wider outline-none focus:border-accent"
        />
        <button type="submit" disabled={!ready || busy} className="btn btn-primary min-h-11 shrink-0 px-4">
          {busy ? <Icon name="refresh" size={16} className="animate-spin" /> : <Icon name="check" size={16} />} {t("rg.redeem")}
        </button>
      </div>
      <p id={id + "-msg"} aria-live="polite" className={"min-h-4 text-xs " + (error ? "font-semibold text-bad" : "text-ink-3")}>
        {error ?? t("rg.hint")}
      </p>
    </form>
  );
}
