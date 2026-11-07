import type { Metadata, Viewport } from "next";
import { Suspense } from "react";
import { Plus_Jakarta_Sans } from "next/font/google";
import "./globals.css";
import { StoreProvider } from "@/state/store";
import { FeaturesProvider } from "@/components/features/FeaturesProvider";
import { getFeaturesResponse, NO_MAINTENANCE } from "@/lib/features";
import { MaintenanceGate } from "@/components/system/MaintenanceGate";
import { ToastProvider } from "@/components/Toast";
import { Header } from "@/components/Header";
import { MobileNav } from "@/components/MobileNav";
import { Footer } from "@/components/Footer";
import { StoreTheme } from "@/components/StoreTheme";
import { RefCapture } from "@/components/growth/RefCapture";
import { Tracking } from "@/components/tracking/Tracking";
import { SupportButton } from "@/components/support/SupportButton";
import { Assistant } from "@/components/assistant/Assistant";
import { MotionRuntime } from "@/components/motion/MotionRuntime";
import { RouteProgress } from "@/components/motion/RouteProgress";
import { PageTransition } from "@/components/motion/PageTransition";

// Self-hosted at build time (no runtime request to Google), swapped in without layout shift.
const jakarta = Plus_Jakarta_Sans({ subsets: ["latin", "latin-ext"], weight: ["400", "500", "600", "700", "800"], display: "swap", variable: "--font-jakarta" });

export const metadata: Metadata = {
  title: { default: "Skydrop Retail: shop Indian stores from Bangladesh", template: "%s · Skydrop Retail" },
  description:
    "Shop amazon.in, flipkart.com and bigbasket.com products from Bangladesh. Prices in BDT, verified at checkout, delivered to your door by Skydrop.",
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover",
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#f7f7f4" },
    { media: "(prefers-color-scheme: dark)", color: "#09090c" },
  ],
};

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  const { features, maintenance } = await getFeaturesResponse();
  return (
    <html lang="en" data-store="all" className={jakarta.variable} suppressHydrationWarning>
      <body className="min-h-dvh">
        <svg width="0" height="0" aria-hidden="true" className="absolute">
          <defs>
            <linearGradient id="half">
              <stop offset="50%" stopColor="currentColor" />
              <stop offset="50%" stopColor="transparent" />
            </linearGradient>
          </defs>
        </svg>
        <MaintenanceGate maintenance={maintenance ?? NO_MAINTENANCE}>
        <FeaturesProvider value={features}>
        <StoreProvider>
          <ToastProvider>
            <Suspense>
              <StoreTheme />
              <Header />
            </Suspense>
            <main className="min-h-[60vh]">
              <PageTransition>{children}</PageTransition>
            </main>
            <RefCapture />
            <Footer />
            <Suspense>
              <MobileNav />
              <SupportButton />
              <Assistant />
              <RouteProgress />
            </Suspense>
            <MotionRuntime />
          </ToastProvider>
        </StoreProvider>
        <Tracking />
        </FeaturesProvider>
        </MaintenanceGate>
      </body>
    </html>
  );
}
