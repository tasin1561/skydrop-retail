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
- Phone accepts `01XXXXXXXXX`, `8801…`, `+8801…` (spaces/dashes ok); must match `^1[3-9]\d{8}$` after the prefix.
- Rate limit: 3 codes per 10 min per phone and per IP → `429 { message, retryAfter }`. Codes are hashed, expire in 5 min,
  only the newest code is valid, 5 wrong attempts burns the code (`429`).

`POST /auth/otp/verify { phone, code, name?, device? }` → `{ token, user: { id, name, phone, email?, points }, isNew }`
- New phone without `name` → `422 { errors: { name: [...] }, needsName: true }`; the code stays valid so the client resends with `name`.
- Blocked account → `403 { code: "blocked" }`. New accounts get the signup points bonus.

`GET /me` → `{ user }` · `PATCH /me { name?, email? }` → `{ user }` · `POST /auth/logout` → `{ ok }` (revokes the current token)

Phone is stored normalised as `+8801XXXXXXXXX`. Staff (email + password) sign in to the Filament admin only.

## Addresses (auth)

`GET /addresses` → `{ items: Address[] }` (default first) · `POST /addresses` → `201 { address }` ·
`PATCH /addresses/{id}` → `{ address }` · `DELETE /addresses/{id}` → `{ ok }`
Address: `{ id (string), name, phone, division, district, area, line, isDefault }`
- division/district validated against `apps/web/src/lib/bd.ts` (PHP copy `App\Services\Commerce\BdGeo`).
- Exactly one default: the first address is default; setting `isDefault: true` clears the others; deleting the default promotes the newest.

## Wishlist (auth)

`GET /wishlist` → `{ items: Product[] }` · `POST /wishlist { productId }` → `201` (idempotent) · `DELETE /wishlist/{productId}`

## Watches (auth): price-drop / back-in-stock alerts

`GET /watches` → `{ items: [{ id, productId, type, targetBdt, active, notifiedAt, createdAt }] }`
`POST /watches { productId, type: "price_drop"|"back_in_stock", targetBdt? }` → `201 { watch }` (upsert per product+type)
`DELETE /watches/{productId}?type=` → `{ ok, removed }` (no `type` = both)
- price_drop fires when price ≤ targetBdt (or below the price when the watch was created); back_in_stock fires once.
  Checked when a product's price/stock changes and hourly. Notified by SMS (+ email if the user has one).

## Reviews

`GET /products/{id}/reviews` → `{ items: [{ id, productId, author, rating, title, content, date, verifiedPurchase, status }], count, average, canReview }`
(approved reviews, plus the caller's own pending review when a token is sent)
`POST /products/{id}/reviews { rating 1-5, title?, body }` (auth) → `201 { review }` — only buyers with a **delivered** order containing
the product (`403 { code: "not_a_buyer" }` otherwise). New reviews are `pending` until approved in the admin.

## Loyalty points (auth)

`GET /points` → `{ balance, pending, valueBdt, rules: { earnPerBdt, redeemBlock, redeemBlockBdt, maxRedeemRatio, expireMonths }, history: [{ type, points, note, at, orderId, expiresAt }] }`
- Earn 1 point per ৳100 of item subtotal (after coupon/points), credited when the order is **delivered** (`pending` until then).
- Redeem in blocks of 100 points = ৳50 off items, max 20% of item subtotal. Refunded if the order is cancelled.
- Points expire 12 months after they are credited (FIFO). Signup bonus 100. Rules: `config/retail.php` `points`, overridable by setting key `points`.
- history `type`: `earn | redeem | expire | adjust | refund`.

## Checkout

`POST /checkout/verify { items: [{ productId, variant?, qty, unitBdt }] }`
→ `{ ok, checkedAt, results: [{ productId, ok, freshBdt, changed, deliverable, reason? }] }`
Always a live provider call per item (Oxylabs with pincode 700001), never cached (delegates to `CatalogService::verify`).

`POST /checkout/quote { items, coupon?, redeemPoints? }` (token optional; needed for points) →
`{ groups: [{ market, count, subtotal, shipping, weightKg }], count, subtotal, shipping, itemDiscount, shipDiscount, couponDiscount,
pointsRedeemed, pointsDiscount, maxRedeemPoints, pointsToEarn, discount, total, coupon?: { code, label, kind: "percent"|"freeship"|"fixed" },
items: [{ productId, variant, qty, unitBdt, weightKg, market, changed }] }`
- Uses **server** prices (client `unitBdt` only sets `changed`). Shipping per marketplace group (`PricingService::shipping`).
- Coupons (table `coupons`): `SKYDROP10` = 10% off items up to ৳1,000; `FREESHIP` = all shipping free. Invalid → `422 errors.coupon`.

## Orders (auth)

`POST /orders { items: [{ productId, variant?, qty, unitBdt }], addressId | address: {name, phone, division, district, area, line},
payment: "bkash"|"nagad"|"card"|"advance50" ("half" accepted), paymentVia?: "bkash"|"nagad"|"card" (wallet for advance50, default bkash),
coupon?, redeemPoints?, returnUrl? }`
→ `201 { order: Order, payment: { status: "pending"|"redirect"|"paid", redirectUrl?, amount, paymentId?, message? } }`
- Server re-verifies every item live. Price moved > 3% vs the client's `unitBdt` → `409 { code: "price_changed", prices: [{ productId, variant, title, oldBdt, freshBdt }], results }`;
  unavailable → `422 { code: "unavailable", unavailable: [...] }`. Within 3% the order uses the fresh price.
- An inline `address` is validated like `/addresses` and saved to the address book (deduplicated).
- `returnUrl`: where the shopper lands after paying. Must be on `WEB_URL`'s host or the app scheme `skydropretail://`.
  The API redirects to `returnUrl` + `order=SR-XXXXXX&status=paid|failed|cancelled` (`&` if it already has a query).
  Default: `{WEB_URL}/checkout/success`.
- `advance50`: 50% (rounded up) now through `paymentVia`, the rest collected by the Skydrop rider (COD) → `paymentStatus: "partial"`.
- Blocked account → `403 { code: "blocked" }`.

`GET /orders` → `{ items: Order[] }` · `GET /orders/{id}` → `{ order }` (owner only, else 404)
`POST /orders/{id}/cancel { reason? }` → `{ order }` — only before `purchased` (`409 { code: "not_cancellable" }`); refunds online payments
through the gateway (`paymentStatus: "refunded"` or `"refund_pending"` when manual) and redeemed points.
`POST /orders/{id}/pay` → `{ order, payment }` — retry an unpaid/failed payment.
`GET /orders/{id}/invoice` → printable HTML invoice (bearer auth). `Order.invoiceUrl` is a signed link (24 h) that opens without a token.
`POST /orders/{id}/support { type: "return"|"damaged"|"wrong_item"|"other", message, photos?: url[] }` → `201 { request }`
(return/damaged/wrong_item only after delivery and within 3 days: `422 { code: "not_delivered"|"window_closed" }`) · `GET /support` → `{ items }`

`Order`: `{ id ("SR-XXXXXX"), createdAt, items: CartItem[], address, payment (label, e.g. "bKash"), paymentMethod, paymentStatus
("pending"|"paid"|"partial"|"failed"|"refunded"|"refund_pending"), paidBdt, dueOnDelivery, subtotal, shipping, discount, couponCode,
couponDiscount, pointsRedeemed, pointsDiscount, pointsToEarn, pointsEarned, total, status, trackingCode ("SD"+9 digits), canCancel,
deliveredAt, invoiceUrl, timeline: [{ status, at, note }] }`
Statuses: `placed, verified, purchased, india_hub, in_transit, dhaka_hub, out_for_delivery, delivered, cancelled`.
`placed → verified` happens automatically when the (advance) payment succeeds; later stages come from the admin and Skydrop webhooks.

## Webhooks

`POST /webhooks/skydrop` — header `X-Skydrop-Signature: <hex HMAC-SHA256 of the raw body with SKYDROP_WEBHOOK_SECRET>` (`sha256=` prefix ok;
bad/missing → 401). Body `{ event, tracking_code | reference, consignment_id?, at?, note?, cod_collected? }` (or wrapped in `data`).
Event map: `picked_up|received_at_origin_hub|india_hub → india_hub`, `in_transit|border_crossing|customs_clearance → in_transit`,
`dhaka_intake|received_at_destination_hub → dhaka_hub`, `out_for_delivery|assigned_to_rider → out_for_delivery`, `delivered → delivered`.
Duplicates/backward events are acknowledged and ignored. The consignment (two legs: Kolkata hub → Dhaka intake → door, COD = unpaid balance)
is booked by a queued job when the order reaches `purchased`.

`GET|POST /webhooks/payments/{bkash|nagad|sslcommerz|mock}?payment={id}` — browser return (redirects to `returnUrl`) or IPN (JSON).
Always re-validated with the gateway (bKash execute/query, Nagad verify, SSLCommerz validator API, HMAC for mock); idempotent.
`PAYMENTS_MODE=mock` (default) sends shoppers to a signed local test page `/payments/mock/{id}` with Pay / Fail / Cancel buttons.

## Admin

Filament panel at `/admin` (staff users). Marketplace toggles, pricing rules, orders, products, provider health, API cost dashboard.
