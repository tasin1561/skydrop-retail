import { Fragment, type ReactNode } from "react";

/**
 * Fill a translated string with React nodes: rich("Pay {amount} now", { amount: <b>৳500</b> }).
 * Works in server and client components.
 */
export function rich(s: string, parts: Record<string, ReactNode>): ReactNode {
  const out = s.split(/(\{\w+\})/g).map((seg, i) => {
    const m = /^\{(\w+)\}$/.exec(seg);
    return <Fragment key={i}>{m && m[1] in parts ? parts[m[1]] : seg}</Fragment>;
  });
  return <>{out}</>;
}
