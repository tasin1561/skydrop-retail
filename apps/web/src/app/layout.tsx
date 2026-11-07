import type { Metadata, Viewport } from "next";
import { Suspense } from "react";
import "./globals.css";
import { StoreProvider } from "@/state/store";
import { ToastProvider } from "@/components/Toast";
import { Header } from "@/components/Header";
import { MobileNav } from "@/components/MobileNav";
import { Footer } from "@/components/Footer";
import { StoreTheme } from "@/components/StoreTheme";

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
    { media: "(prefers-color-scheme: light)", color: "#f6f7fb" },
    { media: "(prefers-color-scheme: dark)", color: "#0b1020" },
  ],
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" data-store="all" suppressHydrationWarning>
      <body className="min-h-dvh">
        <StoreProvider>
          <ToastProvider>
            <Suspense>
              <StoreTheme />
              <Header />
            </Suspense>
            <main className="min-h-[60vh]">{children}</main>
            <Footer />
            <Suspense>
              <MobileNav />
            </Suspense>
          </ToastProvider>
        </StoreProvider>
      </body>
    </html>
  );
}
