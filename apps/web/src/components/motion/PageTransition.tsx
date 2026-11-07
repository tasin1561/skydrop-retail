import * as React from "react";

// React 19.3's <ViewTransition> drives the browser's View Transitions API on route changes (a short
// crossfade, see app/motion.css). Browsers without the API simply swap content. If this React build
// has no ViewTransition export, children render unchanged.
const VT = (React as unknown as { ViewTransition?: React.ComponentType<{ children: React.ReactNode; default?: string }> })
  .ViewTransition;

export function PageTransition({ children }: { children: React.ReactNode }) {
  return VT ? <VT default="auto">{children}</VT> : <>{children}</>;
}
