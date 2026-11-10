# Skydrop Retail

Shop Indian stores from Bangladesh. Shoppers browse products from **amazon.in**, **flipkart.com** and **myntra.com**, pay in BDT, and **Skydrop** delivers to their door. Each store can be switched on or off at any time.

| Store mode | Source | Status |
| --- | --- | --- |
| All Stores | every enabled store | combined search and home |
| Amazon | amazon.in | real product data from the Oxylabs test (255 products, 12 fully detailed) |
| Flipkart | flipkart.com | live through Oxylabs |
| Myntra | myntra.com | live through Oxylabs |

## Monorepo

```
apps/
  web/        Next.js 16 storefront (mobile-first, premium motion, store themes)
  api/        Laravel 12 API: Oxylabs providers, parser, queues, Skydrop integration (next phase)
  mobile/     Expo Android app on the same API (later phase)
packages/     shared types and API client (as the API lands)
scripts/      build-catalog.py: turns Oxylabs responses into the demo catalog
```

## Run the storefront

```bash
pnpm install
pnpm --filter web dev      # http://localhost:3000
pnpm --filter web build
```

Node 20+ and pnpm 10.

## What works today (Phase 0)

- Home with store switcher and scene-switching hero; store fronts with their own themes
- Search with store selector, autocomplete, filters (store, category, price, rating, fast delivery), sort and pagination
- Product page: gallery, videos (HLS), all variants, price breakdown, Skydrop delivery timeline, reviews
- Cart grouped by source store with per-store Skydrop shipping, coupons
- Checkout: address (BD divisions and districts), delivery, payment (bKash, Nagad, card, 50% advance), live price verification, order placement
- Orders with a Skydrop tracking timeline, account, addresses, wishlist, OTP sign-in
- Demo admin: switch each marketplace on or off (`/admin/marketplaces`)

Cart, account and orders are stored in the browser (localStorage) until the Laravel API is connected. Prices use placeholder pricing rules in `apps/web/src/lib/pricing.ts` (FX 1.42, 10% margin, import estimate by category, Skydrop shipping by weight).

## Next phases

1. Laravel API: Oxylabs providers (Amazon raw HTML parser, Flipkart, Myntra), caching, queues, fallbacks
2. Real checkout: payments, order sync to Skydrop, tracking webhooks
3. Filament admin: marketplaces, pricing rules, orders, cost dashboard
4. Expo Android app

The full system spec lives in the project doc ("Amazon India Storefront: System Spec").
