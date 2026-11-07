"use client";

import { openConsent } from "@/lib/track/consent";

/** Footer link that reopens the cookie banner. */
export function ConsentLink({ className = "" }: { className?: string }) {
  return (
    <button type="button" onClick={openConsent} className={"underline-offset-2 hover:text-accent hover:underline " + className}>
      Cookie settings
    </button>
  );
}
