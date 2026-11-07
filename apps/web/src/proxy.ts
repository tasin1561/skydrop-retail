import { NextResponse, type NextRequest } from "next/server";

// Per-request nonce CSP for every page (see docs/SECURITY.md). With a nonce plus 'strict-dynamic', only our own
// Next.js scripts and the scripts they load (next/script tags: GTM/GA4, Meta, TikTok; Turnstile) can run, so an
// injected <script> or inline handler does not execute even if some product/CMS text ever reaches the HTML unescaped.
// 'unsafe-inline' and https: are fallbacks for browsers without CSP3 and are ignored by the rest.
// Every page is already dynamic (the root layout reads the sr-lang cookie), so nonces cost nothing extra.
const dev = process.env.NODE_ENV !== "production";
const apiOrigin = (() => {
  try {
    return process.env.NEXT_PUBLIC_API_URL ? new URL(process.env.NEXT_PUBLIC_API_URL).origin : "";
  } catch {
    return "";
  }
})();

function csp(nonce: string) {
  return [
    "default-src 'self'",
    `script-src 'self' 'nonce-${nonce}' 'strict-dynamic' https: 'unsafe-inline'${dev ? " 'unsafe-eval'" : ""}`,
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
    "font-src 'self' data: https://fonts.gstatic.com",
    "img-src 'self' data: blob: https:",
    "media-src 'self' https: blob:",
    "worker-src 'self' blob:",
    `connect-src 'self' ${apiOrigin} https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com https://connect.facebook.net https://www.facebook.com https://analytics.tiktok.com https://*.tiktok.com https://challenges.cloudflare.com${dev ? " ws: http://localhost:*" : ""}`,
    "frame-src 'self' https://challenges.cloudflare.com https://www.googletagmanager.com https://www.facebook.com",
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "form-action 'self' https:",
    "object-src 'none'",
    ...(dev ? [] : ["upgrade-insecure-requests"]),
  ]
    .join("; ")
    .replace(/\s+/g, " ");
}

export function proxy(request: NextRequest) {
  const nonce = btoa(crypto.randomUUID());
  const value = csp(nonce);
  // Next.js reads the nonce from the request's CSP header and stamps it on its own <script> tags.
  const headers = new Headers(request.headers);
  headers.set("x-nonce", nonce);
  headers.set("Content-Security-Policy", value);
  const res = NextResponse.next({ request: { headers } });
  res.headers.set("Content-Security-Policy", value);
  return res;
}

export const config = {
  matcher: [
    {
      // pages only: API routes, build assets and public files get the static headers from next.config.ts
      source: "/((?!api/|_next/static|_next/image|favicon\\.ico|icon\\.svg|robots\\.txt|sitemap\\.xml|manifest\\.webmanifest|.*\\.(?:png|jpg|jpeg|gif|webp|avif|svg|ico|txt|xml|json|webmanifest|woff2?)$).*)",
      missing: [
        { type: "header", key: "next-router-prefetch" },
        { type: "header", key: "purpose", value: "prefetch" },
      ],
    },
  ],
};
