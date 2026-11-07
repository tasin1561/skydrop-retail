// Skydrop Points (loyalty). Same rules as the API's PointsService; in demo mode the balance
// is worked out in the browser from the shopper's orders.
import type { Order } from "./types";

export const POINTS = {
  earnPerBdt: 100, // 1 point per ৳100 of items
  blockPoints: 100, // redeem in blocks of 100 points
  blockValueBdt: 50, // = ৳50 off items
  maxRedeemPct: 0.2, // up to 20% of the item subtotal
  signupBonus: 100,
  expiryMonths: 12,
};

export function pointsToEarn(itemSubtotalBdt: number) {
  return Math.floor(Math.max(0, itemSubtotalBdt) / POINTS.earnPerBdt);
}

export function pointsValueBdt(points: number) {
  return Math.floor(points / POINTS.blockPoints) * POINTS.blockValueBdt;
}

/** Largest redeemable amount (in whole blocks) for a balance and an item subtotal. */
export function maxRedeem(balance: number, itemSubtotalBdt: number) {
  const capBdt = Math.floor(itemSubtotalBdt * POINTS.maxRedeemPct);
  const blocksByCap = Math.floor(capBdt / POINTS.blockValueBdt);
  const blocksByBalance = Math.floor(balance / POINTS.blockPoints);
  const blocks = Math.max(0, Math.min(blocksByCap, blocksByBalance));
  return { points: blocks * POINTS.blockPoints, valueBdt: blocks * POINTS.blockValueBdt };
}

export type PointsEntry = { type: "earn" | "pending" | "redeem" | "bonus" | "refund"; points: number; note: string; at: string; orderId?: string };

/** Demo ledger built from local orders; the API returns the real ledger. */
export function demoLedger(orders: Order[], signedUpAt?: string): { balance: number; pending: number; history: PointsEntry[] } {
  const history: PointsEntry[] = [];
  if (signedUpAt !== undefined) history.push({ type: "bonus", points: POINTS.signupBonus, note: "Welcome bonus", at: signedUpAt });
  for (const o of orders) {
    const used = o.pointsRedeemed ?? 0;
    if (used) {
      history.push({ type: "redeem", points: -used, note: `Used on order ${o.id}`, at: o.createdAt, orderId: o.id });
      if (o.status === "cancelled") history.push({ type: "refund", points: used, note: `Returned from cancelled order ${o.id}`, at: o.createdAt, orderId: o.id });
    }
    if (o.status === "cancelled") continue;
    const earn = o.pointsEarned ?? pointsToEarn(o.subtotal);
    history.push({
      type: o.status === "delivered" ? "earn" : "pending",
      points: earn,
      note: o.status === "delivered" ? `Earned on order ${o.id}` : `Pending until order ${o.id} is delivered`,
      at: o.createdAt,
      orderId: o.id,
    });
  }
  history.sort((a, b) => b.at.localeCompare(a.at));
  const balance = history.filter((h) => h.type !== "pending").reduce((s, h) => s + h.points, 0);
  const pending = history.filter((h) => h.type === "pending").reduce((s, h) => s + h.points, 0);
  return { balance: Math.max(0, balance), pending, history };
}
