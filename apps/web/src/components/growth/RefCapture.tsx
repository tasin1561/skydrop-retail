"use client";

import { useEffect } from "react";
import { usePathname } from "next/navigation";
import { apiEnabled } from "@/lib/apiClient";
import { DEFAULT_COOKIE_DAYS, growthApi, normalizeInvite, normalizeRef, saveInvite, saveRef } from "@/lib/growth";

/**
 * Captures ?ref=CODE (affiliate, last click wins, 30 days in localStorage + cookie, click recorded with the API)
 * and ?invite=CODE (refer a friend, sent at sign-up). The params are then removed from the address bar so a
 * shopper re-sharing the page does not pass someone else's code along.
 */
export function RefCapture() {
  const pathname = usePathname();

  useEffect(() => {
    let params: URLSearchParams;
    try {
      params = new URLSearchParams(window.location.search);
    } catch {
      return;
    }
    const ref = normalizeRef(params.get("ref"));
    const invite = normalizeInvite(params.get("invite"));
    if (!ref && !invite) return;

    if (ref) {
      saveRef(ref, DEFAULT_COOKIE_DAYS);
      if (apiEnabled) {
        const productId = pathname.startsWith("/p/") ? decodeURIComponent(pathname.slice(3).split("/")[0]) : undefined;
        growthApi.click(ref, productId, window.location.href).then((r) => {
          if (r?.valid && r.cookieDays && r.cookieDays !== DEFAULT_COOKIE_DAYS) saveRef(ref, r.cookieDays);
        });
      }
    }
    if (invite) saveInvite(invite);

    params.delete("ref");
    params.delete("invite");
    const qs = params.toString();
    try {
      window.history.replaceState(window.history.state, "", pathname + (qs ? `?${qs}` : "") + window.location.hash);
    } catch {
      /* ignore */
    }
  }, [pathname]);

  return null;
}
