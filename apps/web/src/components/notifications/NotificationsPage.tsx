"use client";

import Link from "next/link";
import { useEffect, useRef } from "react";
import { Icon } from "@/components/Icon";
import { useFeature } from "@/components/features/FeaturesProvider";
import { useStore } from "@/state/store";
import { NotificationList } from "./NotificationList";
import { useNotifications } from "./useNotifications";
import { useT } from "@/i18n/client";

export function NotificationsPage() {
  const n = useNotifications();
  const { user, ready } = useStore();
  const { t } = useT();
  const on = useFeature("in_app_notifications");
  const more = useRef<HTMLDivElement>(null);
  const { loadMore } = n;

  // Infinite scroll
  useEffect(() => {
    const el = more.current;
    if (!el) return;
    const io = new IntersectionObserver((e) => e[0]?.isIntersecting && loadMore(), { rootMargin: "200px" });
    io.observe(el);
    return () => io.disconnect();
  }, [loadMore, n.hasMore]);

  if (!on) {
    return <Shell><p className="py-10 text-center text-ink-2">{t("nt.off")}</p></Shell>;
  }
  if (ready && !user) {
    return (
      <Shell>
        <div className="flex flex-col items-center py-12 text-center">
          <Icon name="bell" size={32} className="text-ink-3" />
          <p className="mt-3 font-bold">{t("nt.signIn")}</p>
          <Link href="/login?next=/notifications" className="mt-4 rounded-full bg-accent px-5 py-2.5 text-sm font-semibold text-white">{t("nav.signIn")}</Link>
        </div>
      </Shell>
    );
  }

  return (
    <Shell
      action={
        <button type="button" onClick={n.markAllRead} disabled={n.unread === 0}
          className="rounded-full px-3 py-1.5 text-sm font-semibold text-accent transition hover:bg-accent-soft disabled:opacity-40">
          {t("nt.markAll")}
        </button>
      }
      unread={n.unread}
    >
      {!n.loaded ? (
        <div className="flex flex-col gap-2">
          {[0, 1, 2, 3].map((i) => <div key={i} className="skeleton h-20 rounded-2xl" />)}
        </div>
      ) : (
        <>
          <p className="mb-3 text-xs text-ink-3 md:hidden">{t("nt.swipe")}</p>
          <NotificationList items={n.items} onRead={n.markRead} onRemove={n.remove} />
          <div ref={more} className="h-6" />
          {n.loading && n.items.length > 0 && <p className="py-3 text-center text-sm text-ink-3">{t("common.loading")}</p>}
        </>
      )}
    </Shell>
  );
}

function Shell({ children, action, unread = 0 }: { children: React.ReactNode; action?: React.ReactNode; unread?: number }) {
  const { t } = useT();
  return (
    <div className="mx-auto w-full max-w-2xl px-4 pb-28 pt-4 md:pt-8">
      <div className="mb-4 flex items-center justify-between gap-3">
        <h1 className="text-2xl font-extrabold tracking-tight">
          {t("nt.title")}
          {unread > 0 && <span className="ml-2 align-middle rounded-full bg-accent-soft px-2 py-0.5 text-sm text-accent">{t("nt.new", { count: unread })}</span>}
        </h1>
        {action}
      </div>
      {children}
    </div>
  );
}
