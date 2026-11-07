"use client";

import { useEffect, useState } from "react";

const TIPS = [
  "Prices you see already include import duty, service and Skydrop delivery to your door.",
  "We buy from the store in India, check it at our Kolkata hub, then Skydrop brings it home.",
  "Add Unboxing check at checkout and we'll photograph your item before it ships.",
  "Every ৳100 you spend earns 1 Skydrop Point. 100 points = ৳50 off.",
  "Turn on a price alert and we'll tell you when this gets cheaper.",
  "Paste any amazon.in, Flipkart or BigBasket link in search to shop it here.",
];

/**
 * A small story while the shopper waits: a parcel travels India → Kolkata → Bangladesh along a
 * dashed route, with a rotating tip. Pure SVG/CSS (transform + stroke-dashoffset), paused off-screen
 * via CSS animation-play-state when the tab is hidden; reduced-motion users get a static route.
 */
export function WaitStory() {
  const [tip, setTip] = useState(0);
  useEffect(() => {
    const t = setInterval(() => !document.hidden && setTip((i) => (i + 1) % TIPS.length), 4500);
    return () => clearInterval(t);
  }, []);
  return (
    <div className="wait-story card overflow-hidden p-4" aria-hidden="false">
      <svg viewBox="0 0 320 90" className="h-auto w-full" role="img" aria-label="Your parcel's journey from India to Bangladesh">
        <path id="ws-route" d="M20 70 C 90 10, 150 10, 170 45 S 260 85, 300 30" className="ws-route" fill="none" />
        <path d="M20 70 C 90 10, 150 10, 170 45 S 260 85, 300 30" className="ws-route-done" fill="none" pathLength={100} />
        {[
          [20, 70, "India store"],
          [170, 45, "Kolkata hub"],
          [300, 30, "Your door"],
        ].map(([x, y, label]) => (
          <g key={label as string}>
            <circle cx={x as number} cy={y as number} r="5" className="ws-stop" />
            <text x={x as number} y={(y as number) + 18} textAnchor="middle" className="ws-label">
              {label}
            </text>
          </g>
        ))}
        <g className="ws-parcel">
          <rect x="-8" y="-8" width="16" height="14" rx="3" />
          <path d="M-8 -3h16M0 -8v14" />
        </g>
      </svg>
      <p key={tip} className="ws-tip mt-2 min-h-10 text-center text-xs text-ink-2 sm:text-sm">
        <span className="font-bold text-ink">Did you know? </span>
        {TIPS[tip]}
      </p>
    </div>
  );
}
