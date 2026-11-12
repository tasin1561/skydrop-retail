import type { Metadata, Viewport } from "next";
import { Suspense } from "react";
import { cookies } from "next/headers";
import { Noto_Sans_Bengali, Plus_Jakarta_Sans } from "next/font/google";
import "./globals.css";
import { StoreProvider } from "@/state/store";
import { FeaturesProvider } from "@/components/features/FeaturesProvider";
import { getFeaturesResponse, NO_MAINTENANCE } from "@/lib/features";
import { getLang, getT } from "@/i18n/server";
import { LangProvider } from "@/i18n/client";
import { bn } from "@/i18n/bn";
import { LangSync } from "@/components/LangSwitch";
import { MaintenanceGate } from "@/components/system/MaintenanceGate";
import { ToastProvider } from "@/components/Toast";
import { Header } from "@/components/Header";
import { MobileNav } from "@/components/MobileNav";
import { Footer } from "@/components/Footer";
import { StoreTheme } from "@/components/StoreTheme";
import { StorePicker } from "@/components/store/StorePicker";
import { STORE_PICKED_COOKIE } from "@/lib/storePick";
import { RefCapture } from "@/components/growth/RefCapture";
import { Tracking } from "@/components/tracking/Tracking";
import { SupportButton } from "@/components/support/SupportButton";
import { Assistant } from "@/components/assistant/Assistant";
import { MotionRuntime } from "@/components/motion/MotionRuntime";
import { RouteProgress } from "@/components/motion/RouteProgress";
import { PageTransition } from "@/components/motion/PageTransition";

// Self-hosted at build time (no runtime request to Google), swapped in without layout shift.
const jakarta = Plus_Jakarta_Sans({ subsets: ["latin", "latin-ext"], weight: ["400", "500", "600", "700", "800"], display: "swap", variable: "--font-jakarta" });
// Bangla glyphs (Plus Jakarta Sans has none): next in the font stack, so Latin keeps Jakarta. Not preloaded: only bn pages use it.
const bengali = Noto_Sans_Bengali({ subsets: ["bengali"], weight: ["400", "500", "600", "700", "800"], display: "swap", variable: "--font-bengali", preload: false });

export async function generateMetadata(): Promise<Metadata> {
  const { t } = await getT();
  return {
    title: { default: t("meta.title"), template: "%s · Skydrop Retail" },
    description: t("meta.description"),
  };
}

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover",
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#f6f7f5" },
    { media: "(prefers-color-scheme: dark)", color: "#09090c" },
  ],
};

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  const [{ features, maintenance }, lang, { t }, jar] = await Promise.all([getFeaturesResponse(), getLang(), getT(), cookies()]);
  // returning shoppers already chose a store: the first-visit picker is not even rendered
  const storePicked = jar.has(STORE_PICKED_COOKIE);
  return (
    <html lang={lang} data-store="all" className={jakarta.variable + " " + bengali.variable} suppressHydrationWarning>
      <body className="min-h-dvh">
        <a
          href="#main"
          className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-full focus:bg-accent focus:px-4 focus:py-3 focus:text-sm focus:font-semibold focus:text-white"
        >
          {t("a11y.skip")}
        </a>
        <svg width="0" height="0" aria-hidden="true" className="absolute">
          <defs>
            <linearGradient id="half">
              <stop offset="50%" stopColor="currentColor" />
              <stop offset="50%" stopColor="transparent" />
            </linearGradient>
          </defs>
        </svg>
        <LangProvider initial={lang} dict={lang === "bn" ? bn : undefined}>
        <MaintenanceGate maintenance={maintenance ?? NO_MAINTENANCE}>
        <FeaturesProvider value={features}>
        <StoreProvider>
          <LangSync />
          <ToastProvider>
            <Suspense>
              <StoreTheme />
              <Header />
            </Suspense>
            <main id="main" tabIndex={-1} className="min-h-[60vh] outline-none">
              <PageTransition>{children}</PageTransition>
            </main>
            <RefCapture />
            <Footer />
            <Suspense>
              <MobileNav />
              <SupportButton />
              <Assistant />
              <RouteProgress />
              {!storePicked && <StorePicker />}
            </Suspense>
            <MotionRuntime />
          </ToastProvider>
        </StoreProvider>
        <Tracking />
        </FeaturesProvider>
        </MaintenanceGate>
        </LangProvider>
      </body>
    </html>
  );
}
