# Skydrop Retail API contract (v1)

Base URL: `{API_URL}/api/v1`. JSON only. Auth: Laravel Sanctum bearer token (`Authorization: Bearer <token>`).
Errors: `{ "message": string, "errors"?: Record<string, string[]> }` with the right HTTP status.
Money: BDT integers (`*_bdt`), INR numbers (`*_inr`). Times: ISO 8601 UTC.
Field names in JSON responses are **camelCase** so the web and app types (`apps/web/src/lib/types.ts`) map 1:1.

## Marketplaces

`GET /marketplaces` → `{ items: [{ id: "amazon"|"flipkart"|"bigbasket", name, source, enabled }] }`

## Catalog

`GET /search?q=&store=all|amazon|flipkart|bigbasket&category=&sort=relevance|price_asc|price_desc|rating|discount&min=&max=&rating=&fast=1&page=1`
→ `{ status: "ready"|"pending", jobId?: string, total, page, pages, items: Product[] }`
- Cache first (6 h fresh, 7 days stale-while-revalidate). On a miss for an enabled live marketplace the API returns
  catalog matches immediately with `status:"pending"` and a `jobId`; the client polls `GET /jobs/{jobId}` or re-requests.

`GET /suggest?q=&store=` → `{ items: [{ id, title, market }] }` (local DB only, never calls a provider)

`GET /products/{id}` → `{ status: "ready"|"pending", jobId?, product: Product }`
- Fresh (< 24 h) → ready. Stale or listing-only → returns cached data with `status:"pending"` and queues a refresh
  (raw HTML + own parser), plus fetch of all variants (colours first, then sizes).

`GET /products?ids=a,b,c` → `{ items: Product[] }` (cards, max 100)

`GET /jobs/{id}` → `{ id, status: "queued"|"running"|"done"|"failed", type, result?: any, error?: string }`

`Product` (camelCase, same as web `Product` type): `id, market, category, title, brand, priceInr, mrpInr, priceBdt, mrpBdt,
rating, reviews, image, images[], placeholder?, prime, bestSeller, amazonsChoice, salesVolume, detailed, demo?, stock?,
bullets?, specs?, weight?, variants?, aplus?, hasVideos?, videos?, reviewList?, starBreakdown?, deliveryKolkata?,
categoryPath?, fetchedAt?, pincodeVerified?`

## Auth (phone OTP)

`POST /auth/otp/request { phone }` → `{ ok: true, expiresIn: 300, demoCode?: "123456" }` (demoCode only when `OTP_DRIVER=log`)
`POST /auth/otp/verify { phone, code, name? }` → `{ token, user: { id, name, phone, email? }, isNew }`
`GET /me` → `{ user }` · `PATCH /me { name?, email? }` · `POST /auth/logout`

Phone is stored normalised as `+8801XXXXXXXXX`.

## Addresses (auth)

`GET /addresses` · `POST /addresses` · `PATCH /addresses/{id}` · `DELETE /addresses/{id}`
Address: `{ id, name, phone, division, district, area, line, isDefault }`

## Wishlist (auth)

`GET /wishlist` → `{ items: Product[] }` · `POST /wishlist { productId }` · `DELETE /wishlist/{productId}`

## Checkout

`POST /checkout/verify { items: [{ productId, variant?, qty, unitBdt }] }`
→ `{ ok, checkedAt, results: [{ productId, ok, freshBdt, changed, deliverable, reason? }] }`
Always a live provider call per item (Oxylabs with pincode 700001), never cached.

`POST /checkout/quote { items, coupon? }` → `{ groups: [{ market, subtotal, shipping }], subtotal, shipping, discount, total, coupon? }`

## Orders (auth)

`POST /orders { items: [{ productId, variant?, qty, unitBdt }], addressId, payment: "bkash"|"nagad"|"card"|"advance50", coupon? }`
→ `{ order: Order, payment: { status: "pending"|"redirect"|"paid", redirectUrl? } }`
- Server re-verifies prices, computes totals, creates the order (status `placed`), starts payment.

`GET /orders` → `{ items: Order[] }` · `GET /orders/{id}` → `{ order: Order }` · `POST /orders/{id}/cancel` (only before `purchased`)

`Order`: `{ id ("SR-XXXXXX"), createdAt, items: CartItem[], address, payment, paymentStatus, subtotal, shipping, discount,
total, status, trackingCode, timeline: [{ status, at }] }`
Statuses: `placed, verified, purchased, india_hub, in_transit, dhaka_hub, out_for_delivery, delivered, cancelled`.

## Webhooks

`POST /webhooks/skydrop` (HMAC `X-Skydrop-Signature`) → updates order status and timeline from Skydrop consignment events.
`POST /webhooks/payments/{gateway}` → marks payment paid/failed.

## Admin

Filament panel at `/admin` (staff users). Marketplace toggles, pricing rules, orders, products, provider health, API cost dashboard.
