"use client";

import { usePathname } from "next/navigation";
import type { ReactNode } from "react";
import { Icon } from "@/components/Icon";
import type { Maintenance } from "@/lib/features";
import { useT } from "@/i18n/client";

/**
 * Storefront maintenance (Admin → System → Settings → GET /features `maintenance`): replaces the whole page with a
 * "We'll be right back" screen. /admin is skipped (the admin lives on the API host; kept for safety).
 */
export function MaintenanceGate({ maintenance, children }: { maintenance: Maintenance; children: ReactNode }) {
  const pathname = usePathname() ?? "/";
  if (!maintenance.on || pathname === "/admin" || pathname.startsWith("/admin/")) return children;
  return <MaintenanceScreen message={maintenance.message} />;
}

export function MaintenanceScreen({ message }: { message: string }) {
  const { t } = useT();
  return (
    <main
      role="status"
      aria-live="polite"
      className="fade-in grid min-h-dvh place-items-center px-4 py-16 text-center"
      style={{ background: "radial-gradient(1200px 600px at 50% -10%, var(--accent-soft), transparent 60%), var(--bg)" }}
    >
      <div className="mx-auto w-full max-w-md">
        <span className="chip-icon mx-auto h-16 w-16 rounded-2xl" aria-hidden="true">
          <Icon name="tool" size={30} />
        </span>
        <p className="mt-6 text-xs font-bold uppercase tracking-[0.18em] text-ink-3">Skydrop Retail</p>
        <h1 className="mt-2 text-3xl font-extrabold tracking-tight">{t("maint.title")}</h1>
        <p className="mt-3 whitespace-pre-line text-base text-ink-2">{message}</p>
        <div className="mt-8 flex flex-col gap-2 sm:flex-row sm:justify-center">
          <button type="button" className="btn btn-primary min-h-11" onClick={() => window.location.reload()}>
            <Icon name="refresh" size={18} /> {t("maint.retry")}
          </button>
        </div>
        <p className="mt-6 text-xs text-ink-3">{t("maint.safe")}</p>
      </div>
    </main>
  );
}
