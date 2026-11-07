"use client";

import { useCallback, useEffect, useState } from "react";
import { useStore } from "@/state/store";
import { apiEnabled, ApiError } from "@/lib/apiClient";
import {
  demoAffiliateMe,
  demoAffiliateRead,
  demoAffiliateWrite,
  demoInviteCode,
  demoReferralMe,
  growthApi,
  invalidateShareCodes,
  normalizeRef,
  type AffiliateMe,
  type ReferralMe,
} from "@/lib/growth";

const errText = (e: unknown, fallback: string) => (e instanceof ApiError ? e.message : fallback);

/** Affiliate dashboard data: live API when configured and signed in, otherwise a demo state on this device. */
export function useAffiliate() {
  const { user, token, ready } = useStore();
  const live = apiEnabled && !!token;
  const [data, setData] = useState<AffiliateMe | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    if (!ready || !user) return;
    let alive = true;
    if (!live) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- demo state lives in localStorage
      setData(demoAffiliateMe(demoAffiliateRead()));
      return;
    }
    growthApi
      .affiliate()
      .then((d) => alive && (setData(d), setError(null)))
      .catch((e) => alive && setError(errText(e, "Couldn't load your affiliate account.")));
    return () => {
      alive = false;
    };
  }, [ready, user, live, tick]);

  const apply = useCallback(
    async (body: { code?: string; payoutMethod: "bkash" | "nagad"; payoutNumber: string; about?: string }) => {
      if (live) {
        const d = await growthApi.apply(body);
        setData(d);
        invalidateShareCodes();
        return;
      }
      const code = normalizeRef(body.code) ?? (user?.name.split(" ")[0].replace(/[^a-z]/gi, "").toUpperCase().slice(0, 8) || "SKY") + "24";
      const a = { code, status: "pending" as const, rate: null, payoutMethod: body.payoutMethod, payoutNumber: body.payoutNumber, codeEditable: true, link: `${window.location.origin}/?ref=${code}` };
      demoAffiliateWrite(a);
      setData(demoAffiliateMe(a));
    },
    [live, user],
  );

  const update = useCallback(
    async (body: { code?: string; payoutMethod?: "bkash" | "nagad"; payoutNumber?: string }) => {
      if (live) {
        setData(await growthApi.updateAffiliate(body));
        invalidateShareCodes();
        return;
      }
      const cur = demoAffiliateRead();
      if (!cur) return;
      const code = body.code ? normalizeRef(body.code) : null;
      if (body.code && !code) throw new ApiError(422, "Use 4-16 letters or digits.");
      if (code && code !== cur.code && !cur.codeEditable) throw new ApiError(422, "Your code was already changed once.");
      const next = {
        ...cur,
        ...(code && code !== cur.code ? { code, codeEditable: false, link: `${window.location.origin}/?ref=${code}` } : {}),
        ...(body.payoutMethod ? { payoutMethod: body.payoutMethod } : {}),
        ...(body.payoutNumber ? { payoutNumber: body.payoutNumber } : {}),
      };
      demoAffiliateWrite(next);
      setData(demoAffiliateMe(next));
    },
    [live],
  );

  const requestPayout = useCallback(async () => {
    if (live) {
      setData(await growthApi.requestPayout());
      return;
    }
    await new Promise((r) => setTimeout(r, 700));
    setData((d) =>
      d?.stats
        ? {
            ...d,
            stats: { ...d.stats, requestedBdt: d.stats.availableBdt, availableBdt: 0 },
            payouts: [
              { id: Date.now(), amountBdt: d.stats.availableBdt, method: d.affiliate?.payoutMethod ?? "bkash", status: "requested", requestedAt: new Date().toISOString() },
              ...(d.payouts ?? []),
            ],
          }
        : d,
    );
  }, [live]);

  /** Demo only: skip the review. */
  const demoApprove = useCallback(() => {
    const cur = demoAffiliateRead();
    if (!cur) return;
    const next = { ...cur, status: "approved" as const };
    demoAffiliateWrite(next);
    setData(demoAffiliateMe(next));
  }, []);

  return { ready, user, live, data, error, reload: () => setTick((t) => t + 1), apply, update, requestPayout, demoApprove };
}

/** Refer-a-friend summary (live or demo). */
export function useReferrals() {
  const { user, token, ready } = useStore();
  const live = apiEnabled && !!token;
  const [data, setData] = useState<ReferralMe | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!ready || !user) return;
    let alive = true;
    if (!live) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- demo data derived from the phone number
      setData(demoReferralMe(user.phone, window.location.origin));
      return;
    }
    growthApi
      .referrals()
      .then((d) => alive && (setData(d), setError(null)))
      .catch((e) => alive && setError(errText(e, "Couldn't load your invite code.")));
    return () => {
      alive = false;
    };
  }, [ready, user, live]);

  return { ready, user, live, data, error, demoCode: user ? demoInviteCode(user.phone) : null };
}
