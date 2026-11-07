# Skydrop Retail API contract (v1)

Base URL: `{API_URL}/api/v1`. JSON only. Auth: Laravel Sanctum bearer token (`Authorization: Bearer <token>`).
Errors: `{ "message": string, "errors"?: Record<string, string[]> }` with the right HTTP status.
Money: BDT integers (`*_bdt`), INR numbers (`*_inr`). Times: ISO 8601 UTC.
Field names in JSON responses are **camelCase** so the web and app types (`apps/web/src/lib/types.ts`) map 1:1.

## Client authentication (security module, see `docs/SECURITY.md`)

| Caller | Headers | Reaches |
|---|---|---|
| Next.js server (server components, `/api/*` route handlers) | `X-Internal-Key: <INTERNAL_API_KEY>` (+ `X-Client-IP` on the honeypot proxy) | everything; route throttles skipped (Cloudflare limits shoppers in front of Next.js) |
| Mobile app | `X-App-Key: <id>`, `X-App-Timestamp: <unix s>`, `X-App-Signature: hex(HMAC-SHA256(secret, "{ts}\n{METHOD}\n{path}\n{query}"))`, optional `X-Device-Id` | everything; per IP / user / device limits |
| Browser | `Authorization: Bearer` only | auth, account, orders, checkout, reviews, tracking … **not** the catalog |

- `path` is the full request path (`/api/v1/search`), `query` the raw query string without `?` (empty string when none).
  Timestamps more than 300 s off are rejected. Implementation: `apps/mobile/src/api/sign.ts`, `App\Services\Security\AppSignature`.
- **Catalog guard** (`catalog.guard`): every route in `routes/api_catalog.php` (marketplaces, suggest, offers, jobs, products*,
  search, resolve) plus `products/{id}/price-history|compare|bought-together|bundles` answers only the first two callers;
  anything else gets `401 {code:"unsigned"}`. `GET /features`, `/cms/*` and commerce routes stay public.
- Limits: `page` > 20 → `422 {code:"page_limit"}`; `perPage`/`limit` capped at 48; `ids` capped at 48 (100 for the Next.js server).
  Strict buckets (search, suggest, resolve, product detail, price history): 40/min per IP and per device, 60/min per user;
  10/min for resolve; 120/min per IP on the rest. Past 75 % of a bucket responses slow down; then `429 {code:"rate_limited", retryAfter}` + `Retry-After`.
- Bot checks (signed apps skip the header checks): automation user agents, headless hints, missing `Accept-Language`,
  more than 40 distinct products per minute per IP, bursts of 404s on product ids → `403 {code:"challenge", turnstileSiteKey}`.
  `POST /security/challenge {token}` (Cloudflare Turnstile) clears the IP for an hour.
- Blocked IPs (honeypot, staff blocklist) get `403 {code:"blocked"}` on every endpoint except payment/courier webhooks.
- OTP: `POST /auth/otp/request` takes `turnstileToken` (or `X-Turnstile-Token`) when Turnstile is configured (else `403 {code:"challenge"}`),
  one code per phone per 60 s; 5 wrong codes for a phone within an hour lock it for 30 min (`429`); 20 verify attempts per IP per 10 min.
- `GET /qr?text=<url>` → `image/svg+xml` QR code; only URLs on our own hosts (`WEB_URL`, `APP_URL`, `QR_ALLOWED_HOSTS`), 30/min.
- Honeypots (never call): `GET /products/export`, `GET /products/SR-HONEYPOT-0001`. A hit blocks the caller's IP for 24 h.

## Marketplaces

`GET /marketplaces` → `{ items: [{ id: "amazon"|"flipkart"|"bigbasket", name, source, enabled, liveFetch }] }`
- `liveFetch:false` (the default in dev) means the store is served from the local catalog only and never calls a provider.

## Catalog

All catalog reads detect crawlers by User-Agent (the storefront may forward the shopper's agent as `X-Client-UA`);
crawler requests never trigger provider fetches. Callers must be the Next.js server or a signed app (see "Client authentication").

`GET /search?q=&store=all|amazon|flipkart|bigbasket&category=&sort=relevance|price_asc|price_desc|rating|discount&min=&max=&rating=&fast=1&page=1`
→ `{ status: "ready"|"pending", jobId?: string, total, page, pages, items: Product[] }` (24 per page)
- Scoring, filters (`min`/`max` are BDT) and sorts match `apps/web/src/lib/catalog.ts`.
- Cache first per (store, query, store page, sort): 6 h fresh, 7 days stale-while-revalidate. One store page (~48 items)
  is one provider call. On a miss for a live marketplace the API returns local matches immediately with
  `status:"pending"` and a `jobId`; the client polls `GET /jobs/{jobId}` or re-requests. Store-ranked results are then
  included even when not every word matches the title.

`GET /suggest?q=&store=&limit=6` → `{ items: [{ id, title, market }] }` (local DB only, never calls a provider)

`GET /products/{id}` → `{ status: "ready"|"pending", jobId?, product: Product }`
- Fresh (< 24 h; 30 min while a deal ends within 24 h; immediately stale once `dealEndsAt` passed) → ready.
  Stale or listing-only → cached data with `status:"pending"` and a queued refresh (raw HTML + own parser), followed by
  the variants (colours first, then sizes). Unknown id on a live store → fetched synchronously (hedged) → ready, else 404.
- Unavailable/undeliverable products stay reachable here (SEO) but are hidden from lists.

`GET /products?ids=a,b,c` → `{ items: Product[] }` (cards, max 100, request order)

`GET /products/popular?store=&category=&limit=12` · `GET /products/deals?store=&limit=12` (with `offers`) ·
`GET /products/{id}/related?limit=8` → `{ items: Product[] }`

`POST /products/{id}/cancel-variants` → `{ ok, pending }` — shopper bounced: drops the queued size-variant phase.

`POST /products/resolve { url }` — "paste any link". Accepts amazon.in (`/dp/ASIN`, `/gp/product/ASIN`, `amzn.in`/`amzn.to`/`a.co`
short links, followed via redirect), flipkart.com (`/p/itm…`, `fkrt.it`/`dl.flipkart.com` short links) and bigbasket.com (`/pd/{id}/`).
→ `200 { status: "ready"|"pending", jobId?, productId, market, product }` when known, or
`202 { status: "pending", jobId, productId, market }` (fetch queued; poll the job, then `GET /products/{productId}`).
Other domains, links without a product id, or a store that is not live → `422 { errors: { url: [friendly message] } }`.

`GET /offers?store=&type=&limit=24` → `{ items: Product[] }` — "Live deals": products with an active coupon, deal price or
deal badge, biggest saving first; expired deals excluded. `type` is one of the offer types below.

`GET /jobs/{id}` → `{ id, status: "queued"|"running"|"done"|"failed", type: "search"|"product"|"variants", result?: any, error?: string }`

`Product` (camelCase, same as web `Product` type): `id, market, category, title, brand, priceInr, mrpInr, priceBdt, mrpBdt,
rating, reviews, image, images[], placeholder?, prime, bestSeller, amazonsChoice, salesVolume, detailed, demo?, inStock, stock?,
bullets?, specs?, weight?, variants?, aplus?, hasVideos?, videos?, reviewList?, starBreakdown?, deliveryKolkata?,
categoryPath?, fetchedAt?, pincodeVerified, offers[], dealBadge, couponInr, effectivePriceInr, dealEndsAt, flashSale, restriction`
- `flashSale`: `{ saleId, title, endsAt, extraOffBdt, maxQty } | null` — live CMS flash sale; `priceBdt` **already** has `extraOffBdt` taken off.
- `restriction`: `{ action: "block"|"warn"|"surcharge", message, surchargeBdt } | null` — can it ship to Bangladesh (see CMS);
  `surcharge` amounts are already **added** to `priceBdt`. Both come from `App\Support\ProductJson::cms()`.
- `variants[]`: `{ id, label, dimensions, swatch, selected, available }` (`available:false` only when the sibling is known
  to be out of stock or undeliverable).
- `priceBdt` is `PricingService::productBdt()`: it uses `effectivePriceInr` (deal price minus a clippable coupon) while the
  deal is live and the admin setting `pass_coupons` is on (default), else `priceInr`.
- `offers[]`: `{ type, title, detail?, savingInr?, appliesToOurPrice, endsAt?, claimedPct? }` with `type` one of
  `coupon, deal, lightning, bank, cashback, emi, partner, combo, special_price, subscribe, exchange, other`.
  `appliesToOurPrice:true` (coupons, deal/lightning/special prices) are passed through to our price;
  `false` (bank/card, cashback, EMI, exchange, partner, combo, subscribe) are shown as "Available on the source store".
  Cards from lists other than `/products/deals` and `/offers` carry `offers: []`; the columns `dealBadge`/`couponInr` are always set.

## Auth (phone OTP)

`POST /auth/otp/request { phone }` → `{ ok: true, expiresIn: 300, demoCode?: "123456" }` (demoCode only when `OTP_DRIVER=log`)
- Phone accepts `01XXXXXXXXX`, `8801…`, `+8801…` (spaces/dashes ok); must match `^1[3-9]\d{8}$` after the prefix.
- Rate limit: 3 codes per 10 min per phone and per IP → `429 { message, retryAfter }`. Codes are hashed, expire in 5 min,
  only the newest code is valid, 5 wrong attempts burns the code (`429`).

`POST /auth/otp/verify { phone, code, name?, device?, invite? }` → `{ token, user: { id, name, phone, email?, points }, isNew }`
- New phone without `name` → `422 { errors: { name: [...] }, needsName: true }`; the code stays valid so the client resends with `name`.
- Blocked account → `403 { code: "blocked" }`. New accounts get the signup points bonus.

`GET /me` → `{ user }` · `PATCH /me { name?, email? }` → `{ user }` · `POST /auth/logout` → `{ ok }` (revokes the current token)
- Changing `email` sends a verification link (signed `GET /email/verify/{user}/{hash}`, 3 days, redirects to `WEB_URL/account?email=verified|invalid`). Resend: `POST /me/email/verification` → `{ ok, verified }` (3/hour).

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

Feature `reviews` (off → `404 { error: "feature_disabled" }`). These are **Skydrop shopper** reviews; the source store's rating stays in
`Product.rating/reviews/reviewList` and is shown separately ("4.3 on amazon.in").

`GET /products/{id}/reviews` → `{ items: [{ id, productId, author, rating, title, content, date, verifiedPurchase, status, photoUrl }], count, average,
breakdown: [{ rating: 5..1, count }], canReview }` (approved reviews, plus the caller's own pending review when a token is sent)
`POST /products/{id}/reviews { rating 1-5, title?, body, photoUrl? (https URL) }` (auth) → `201 { review }` — only buyers with a **delivered** order
containing the product (`401` signed out, `403 { code: "not_a_buyer" }` otherwise). New reviews are `pending` until approved in the admin;
posting again updates your review. No helpful-vote counts yet.

## Loyalty points (auth)

`GET /points` → `{ balance, pending, valueBdt, rules: { earnPerBdt, redeemBlock, redeemBlockBdt, maxRedeemRatio, expireMonths }, history: [{ type, points, note, at, orderId, expiresAt }] }`
- Earn 1 point per ৳100 of item subtotal (after coupon/points), credited when the order is **delivered** (`pending` until then).
- Redeem in blocks of 100 points = ৳50 off items, max 20% of item subtotal. Refunded if the order is cancelled.
- Points expire 12 months after they are credited (FIFO). Signup bonus 100. Rules: `config/retail.php` `points`, overridable by setting key `points`.
- history `type`: `earn | redeem | expire | adjust | refund`.

## Checkout

`POST /checkout/verify { items: [{ productId, variant?, qty, unitBdt }] }`
→ `{ ok, checkedAt, results: [{ productId, ok, freshBdt, changed, deliverable, reason?, restriction? }] }`
Items blocked by a restricted rule come back `ok:false, deliverable:false, reason:"restricted", message`.
Always a live provider call per item (Oxylabs with pincode 700001), never cached (delegates to `CatalogService::verify`).
`CatalogService::verify` results also carry `offerExpired` (a coupon/deal behind the shopper's price ended; `freshBdt` is the
new price), `pincodeVerified` and `provider`. Provider failure → `ok:false, reason:"verify_failed"`. Stores with
`liveFetch:false` verify against the stored catalog row.

`POST /checkout/quote { items, coupon?, redeemPoints? }` (token optional; needed for points) →
`{ groups: [{ market, count, subtotal, shipping, weightKg }], count, subtotal, shipping, itemDiscount, shipDiscount, couponDiscount,
pointsRedeemed, pointsDiscount, maxRedeemPoints, pointsToEarn, discount, total,
coupon?: { code, label, kind: "percent"|"freeship"|"fixed", value, maxDiscountBdt, minSubtotalBdt },
flashDiscount, restrictions: [{ productId, title, action, message, surchargeBdt }],
items: [{ productId, variant, qty, unitBdt, weightKg, market, changed, flashSale, restriction }] }`
- `flashDiscount`: flash-sale savings already inside `subtotal`. More than the sale's `maxQty` of one product → `422 { code: "flash_limit", productId, maxQty }`.
- The web `CouponField` calls this endpoint to validate any code (admin-created ones too) and keeps `coupon` to price the cart.
- Uses **server** prices (client `unitBdt` only sets `changed`). Shipping per marketplace group (`PricingService::shipping`).
- Coupons (table `coupons`): `SKYDROP10` = 10% off items up to ৳1,000; `FREESHIP` = all shipping free. Invalid → `422 errors.coupon`.
  Feature `coupons` off → any code is rejected with `422 errors.coupon`.

## Orders (auth)

`POST /orders { items: [{ productId, variant?, qty, unitBdt }], addressId | address: {name, phone, division, district, area, line},
payment: "bkash"|"nagad"|"card"|"advance50" ("half" accepted), paymentVia?: "bkash"|"nagad"|"card" (wallet for advance50, default bkash),
coupon?, redeemPoints?, returnUrl?, ref? }`
(`ref` = affiliate code, see Growth)
→ `201 { order: Order, payment: { status: "pending"|"redirect"|"paid", redirectUrl?, amount, paymentId?, message? } }`
- Server re-verifies every item live. Price moved > 3% vs the client's `unitBdt` → `409 { code: "price_changed", prices: [{ productId, variant, title, oldBdt, freshBdt }], results }`;
  unavailable → `422 { code: "unavailable", unavailable: [...] }`. Within 3% the order uses the fresh price.
- Items that cannot ship to Bangladesh → `422 { code: "restricted", restricted: [{ productId, title, message }], errors.items }` (checked before the live verify).
- Live flash sales are applied through `PricingService::productBdt`, so the verified price, order lines and totals all include them.
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

## Notifications and messaging

Every customer message is rendered from `message_templates` (event key × channel `sms|email|push` × locale `en|bn`, `{{placeholder}}`,
`{{#flag}}…{{/flag}}` / `{{^flag}}…{{/flag}}`), logged in `notifications_log` and delivered by a queued job with retries. Each event also writes
an in-app item. Categories: **transactional** (orders, payments, points, review requests: always sent), **alert** (price drop / back in stock the
shopper asked for), **marketing** (abandoned cart, broadcasts: respects opt-outs, held during quiet hours 22:00–09:00 Asia/Dhaka), **otp**
(SMS only, sent synchronously, never through marketing). Email is only sent when the shopper has an email address. The user's `locale` picks the language.
Feature switches: `sms` (OTP ignores it), `email`, `push`, `in_app_notifications`, `abandoned_cart`, `marketing_messages`; a disabled feature's
routes answer `404 { error: "feature_disabled", feature }`.

Events: `welcome`, `order_placed`, `payment_received`, `payment_failed`, `payment_pending` (30 min reminder), `order_verified`, `order_purchased`,
`order_india_hub`, `order_in_transit`, `order_dhaka_hub`, `order_out_for_delivery` (includes the rider's due amount for 50% advance orders),
`order_delivered`, `order_cancelled`, `review_request` (3 days after delivery), `points_expiring` (14 days before), `price_drop`, `back_in_stock`,
`cart_reminder_1h` (push), `cart_reminder_24h` (SMS + email), `email_verify`, plus `custom` (staff), `broadcast`, `admin_alert`.

### In-app notification centre (auth, feature `in_app_notifications`)
`GET /notifications?page=1&perPage=20&unread=0|1` → `{ items: Notification[], unreadCount, page, perPage, total, hasMore }`
`Notification = { id, type, title, body?, icon?, link? ("/orders/SR-…", "/p/{id}", "/cart"), image?, data, read, readAt?, createdAt }`
`POST /notifications/{id}/read` → `{ item, unreadCount }` · `POST /notifications/read-all` → `{ ok, marked, unreadCount: 0 }` ·
`DELETE /notifications/{id}` → `{ ok, unreadCount }`. Another user's id → 404. Clients poll every 60 s while visible.

### Preferences (auth)
`GET /me/notifications` → `{ locale: "en"|"bn", marketing: { sms, email, push, inapp }, email?, emailVerified, transactional }`
`PATCH /me/notifications { locale?, marketing?: { sms?, email?, push?, inapp? } }` → same shape. Marketing defaults to on; transactional cannot be turned off.
Email footers link to a signed one-click `GET|POST /unsubscribe/{user}/{channel}` (also sent as `List-Unsubscribe`).

### Push devices (auth, feature `push` for registering)
`POST /push/expo-token { token: "ExponentPushToken[…]", platform?: ios|android }` → `201 { ok, id }` (upsert; a token moves to the signed-in user) ·
`DELETE /push/expo-token { token }` (sign-out). Sent through `https://exp.host/--/api/v2/push/send`; `DeviceNotRegistered` tokens are removed.
Push `data`: `{ type, link (web path), route (app route: /order/{id}, /product/{id}, /cart, /points, /notifications), orderId?, productId? }`.
`POST /push/subscribe { endpoint, keys: { p256dh, auth } }` → `201 { ok, delivering, vapidPublicKey }` stores a browser Web Push subscription;
`delivering` is `false` until `minishlink/web-push` is installed and a `WebPushSender` is bound (TODO). `DELETE /push/subscribe { endpoint }`.

### Cart sync (auth)
`PUT /cart { items: [{ productId, market?, title?, image?, variant?, unitBdt?, qty }] }` → `{ ok, items }` (replaces; `[]` clears) · `GET /cart` → `{ items, updatedAt }`.
Web and mobile send it debounced (1.5 s) for signed-in shoppers. A change restarts the reminder clock; placing an order stops reminders.

### Schedule (`routes/console.php`)
Cart reminders every 15 min (push after 1 h, SMS/email after 24 h, carts up to 7 days old) · payment reminders every 5 min (once, 30 min after an
unpaid order, retry link `WEB_URL/orders/{id}?pay=1`) · review requests hourly (3 days after delivery, skipped if reviewed) · points expiry daily ·
`retail:sms-balance --alert` every 6 h (emails `ALERT_EMAIL` below the low-balance threshold). Timings are editable in Admin → Messaging settings.

### SMS
Drivers: `log` (dev), `bulksmsbd` (form POST `{url}/smsapi` `api_key, type=text|unicode, number=8801…, senderid, message`; success
`response_code: 202`; balance `{url}/getBalanceApi`), `sslwireless` (ISMS v3), `alpha` (api.sms.net.bd), `http` (legacy generic). Failover walks
`SMS_FAILOVER` / the admin list until one gateway accepts; invalid numbers stop the chain. Phones are normalised to `8801XXXXXXXXX`. Non-OTP SMS
are limited per phone per hour (default 8). Segments: GSM-7 160 / 153 per part, Unicode (Bangla) 70 / 67; stored in `notifications_log.segments`.

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

## CMS: banners, flash sales, shipping restrictions

`GET /cms/home?store=all|amazon|flipkart|bigbasket` → `{ store, enabled: { banners, flashSales }, banners: Banner[], flashSales: FlashSale[], serverTime }`
(cache 60 s). `store=all` returns everything; a marketplace returns its own items plus `store:"all"` ones.
- `Banner`: `{ id, title, subtitle, image, link, cta, store, placement: "hero"|"strip"|"category", startsAt, endsAt, sort }` — active and inside
  its schedule only. `image` is the uploaded file (public disk) or the image URL.
- `FlashSale`: `{ id, title, store, status: "live"|"upcoming", startsAt, endsAt, maxQty, items: [{ product: Product, regularBdt, extraOffBdt, flashBdt }] }`
  — live sales plus those starting within 7 days; hidden products are skipped.
- Flash discount (`flash_sale_items.off_pct` or `off_bdt`) is funded from our margin and **capped at the margin** of that price
  (`FlashSaleService::amount`). Applied in `PricingService::productBdt` while the sale is live and the product's market matches the sale store.
  `flash_sales.max_qty` = max units of one product per order at the flash price.
- Restricted items (table `restricted_rules`: `type` keyword|category|brand, `pattern` = comma-separated words or `/regex/i`,
  `action` block|warn|surcharge, `message`, `surcharge_bdt`). Highest action wins (block > surcharge > warn). Defaults seeded by
  `RestrictedRuleSeeder` (migration): power banks/lithium batteries, liquids > 1 L, medicines/supplements, drones → warn; aerosols/flammables,
  weapons/knives, seeds/plants, meat, alcohol, tobacco/vapes → block. `RestrictionService::check(Product)` feeds product JSON, prices
  (surcharge), quote notices, verify and order creation.
- Features: `banners`, `flash_sales` (off → no discount anywhere), `restrictions` (off → no checks), `reviews`, `coupons`, `support_chat` (web only).

## Admin

Filament panel at `/admin` (staff users). Marketplace toggles, pricing rules, orders, products, provider health, API cost dashboard.
CMS: Sales → Banners (upload or URL, schedule, live preview), Sales → Flash sales (product picker by title/id with flash-price preview),
Catalog → Shipping restrictions (rules + "Test a title" action).
Messaging group: **Message templates** (live preview with sample data, SMS segment counter, test send, "Add missing defaults"), **Notification log**
(filters, masked recipients, resend), **Broadcast** (segment all / bought in last N days / store / has points; SMS, email, push, in-app; dry-run count
and SMS cost; queued in chunks of 200; permission `broadcast.send`), **Messaging settings** (SMS gateways + keys stored encrypted, failover order,
mail from / reply-to, reminder timings, quiet hours, balance check). Template editing uses permission `templates.manage`.

## Tracking and feeds (features `tracking`, `feeds`; off → `404 { error: "feature_disabled" }`)

`POST /track` (public, 120/min, optional bearer) `{ event, eventId, url?, referrer?, value?, search?, orderId?, items?: [{ id, title?, brand?, category?, price?, qty? }],
fbp?, fbc?, ttp?, ttclid?, gclid?, gaClientId?, user?: { phone?, email? } }` → `202 { ok, queued }`
- `event`: `PageView | ViewContent | Search | AddToCart | AddToWishlist | InitiateCheckout | AddShippingInfo | AddPaymentInfo | CompleteRegistration | Lead`.
  Purchase is sent by the API only (no client POST).
- The client IP and user agent come from the request. With a token, the user's phone (`8801XXXXXXXXX`) and email are SHA-256 hashed and added.
- Queued per destination by setting `tracking.driver`: `off | delivro | direct (Meta CAPI v21.0, GA4 Measurement Protocol, TikTok Events API v1.3) | both`.
  Logged in `tracking_events`, where (event_id, destination) is unique (server-side dedupe). Retries 429/5xx with backoff.
- Purchase: event id is `purchase_<ORDER_ID>`, the same on browser and server. It is sent when `tracking.purchase_on` = `paid` (paid or advance paid) | `placed` | `delivered`.
  It is skipped when the order's attribution `consent` is `essential`. An optional custom `Delivered` event (`delivered_<ORDER_ID>`) is sent when `tracking.send_delivered` is on.

`GET /tracking/config` → `{ metaPixelId, ga4Id, tiktokPixelId, gtmId, server }` (public ids only; `server` = a server driver is on)

`POST /orders` also takes an optional `attribution: { first?, last?: { utm_source, utm_medium, utm_campaign, utm_term, utm_content, fbclid, gclid, ttclid,
landing, referrer, at }, fbp?, fbc?, ttp?, gaClientId?, consent?: "all"|"essential" }`. The fields are whitelisted and stored in `orders.attribution` with the client ip and user agent.

`GET /feeds/meta.csv` · `GET /feeds/google.xml`: `?store=amazon|flipkart|bigbasket`, and `?key=` when setting `tracking.feed_key` is set (otherwise 403). Cached for 1 h.
- Skips hidden and unpriced products. Price comes from `PricingService::productBdt`; `price` is the MRP when higher, with `sale_price` set.
- Other fields: `link` is `{WEB_URL}/p/{id}`, `custom_label_0` is the store.

## Growth: affiliate program, refer a friend, sharing

Feature switches: `affiliate`, `referral`, `share` (Admin → System → Features). Off → `404 { error: "feature_disabled", feature }`;
with `affiliate` off, orders are not attributed and commissions don't start/auto-approve (reversals still run); with `referral` off,
invites are not linked or rewarded. Settings (Admin → Growth → Growth settings; settings table, defaults in `config/growth.php`):
`affiliate.rates.{category}` (percent; `affiliate.rates.default` = 3), `affiliate.cookie_days` 30, `affiliate.hold_days` 7,
`affiliate.min_payout_bdt` 500, `affiliate.max_per_order_bdt` 1000 (0 = no cap), `referral.inviter_points` 200, `referral.friend_points` 100.

### Affiliates
`GET /affiliate/program` (public) → `{ program: { defaultRate, rates: { category: percent }, cookieDays, holdDays, minPayoutBdt, maxPerOrderBdt, payoutMethods } }`

`POST /r/click { code, productId?, url? }` (public, 60/min) → `{ ok, valid, cookieDays }` — records a click for an approved affiliate
(IP stored as an HMAC hash; the same visitor + product within 30 min counts once).

`POST /affiliate/apply { code?, payoutMethod?: "bkash"|"nagad", payoutNumber?, about? }` (auth) → `201` dashboard (below); `200` when already applied.
Code: 4-16 letters/digits, uppercase, unique; generated from the name when omitted. Status starts `pending`; staff approve in the admin.

`GET /affiliate/me` (auth) → `{ affiliate: null | { code, status: "pending"|"approved"|"suspended", rate (own % or null), payoutMethod,
payoutNumber, codeEditable, link, approvedAt, createdAt }, stats?: { clicks, clicks30d, orders, conversion (%), pendingBdt, approvedBdt,
paidBdt, availableBdt, requestedBdt }, commissions?: [{ id, order: "SR-••••AB", baseBdt, rate, amountBdt, status, approveAfter, at }] (latest 20),
payouts?: [{ id, amountBdt, method, status: "requested"|"paid"|"rejected", reference, requestedAt, paidAt }], program }`

`PATCH /affiliate/me { code?, payoutMethod?, payoutNumber? }` (auth) → dashboard. The code can be changed once (`422 errors.code` after).

`POST /affiliate/payouts` (auth) → `201 { payout, ...dashboard }` — bundles every approved, unpaid commission. `422 { code: "below_minimum",
availableBdt, minPayoutBdt }`, `409 { code: "payout_pending" }`, `403 { code: "not_approved" }`. Staff mark it paid with a bKash/Nagad reference.

Attribution: last click. The storefront keeps the latest `?ref=` for `cookie_days` and sends it as `ref` on `POST /orders`.
`orders.affiliate_id` + `orders.ref_code` are stored. No commission for self-referrals (same account, or the buyer's account phone /
delivery phone equals the affiliate's phone or payout number).
Commission = items subtotal − coupon item discount − points discount (shipping excluded) × category rate (or the affiliate's own rate), capped per order.
Lifecycle: `pending` (placed) → delivered: `approveAfter = deliveredAt + hold_days` → `approved` (hourly job `ApproveAffiliateCommissions`) →
`paid` (payout marked paid). Cancelled or refunded before payout → `reversed`.

### Refer a friend
`GET /referrals/me` (auth) → `{ code, link: "{WEB_URL}/?invite=CODE", invited, rewarded, pointsEarned, rules: { inviterPoints, friendPoints },
friends: [{ name: "Karim U.", joinedAt, status: "joined"|"rewarded" }] }`
New accounts send `invite` at `POST /auth/otp/verify`. When the friend's first order is delivered, both get points through the points
ledger (type `adjust`, note "Referral bonus: …" / "Referral welcome bonus: …"), once per friend. No reward when the order is delivered to
the inviter's phone.

### Shared wishlists
`POST /wishlist/share` (auth) → `{ code, url: "{WEB_URL}/w/{code}" }` (stable per user)
`GET /wishlists/{code}` (public) → `{ code, owner: { name (first name only) }, count, items: Product[] }` · unknown → `404`


## AI shopping assistant (feature `ai_assistant`; off → `404 { error: "feature_disabled" }`)

Claude API (`ANTHROPIC_API_KEY`), called directly over HTTP with our own SSE parser. Token optional (Sanctum); guests are
tracked by a hashed IP. The web storefront calls it only through its proxy `POST /api/assistant` (+ `/api/assistant/config`,
`/feedback`, `/clear`), which adds `X-Internal-Key: INTERNAL_API_KEY` and `X-Client-IP`; the API trusts `X-Client-IP` only with
that key.

`GET /assistant/config` → `{ enabled, basic, greeting: { en, bn }, prompts: { en: string[], bn: string[] }, limit, remaining, signedIn }`
(`basic:true` = no API key or monthly budget reached: replies are plain search results)

`POST /assistant/chat { conversationId?, message (≤2000), store?, locale?: "en"|"bn", client?: "web"|"mobile", context?: { productId?, cartIds?[] } }`
→ `text/event-stream`, each event `event: <type>\ndata: <json>\n\n`:
- `meta { conversationId, degraded }` (first) · `text { delta }` · `tool { name }` (a tool started, for "Searching…")
- cards: `products { query?, total?, items: AssistantProduct[] }` · `compare { items, rows: [{ label, values[] }] }` ·
  `orders { items: OrderCard[] }` · `order OrderCard` (with `timeline`) · `cart { product, variant?, qty }` (an "Add to cart" action;
  the server never changes the cart) · `delivery { minDays: 7, maxDays: 12, earliest, latest, cutoffs: [{ name, date, orderBy,
  status: "on_time"|"maybe"|"too_late" }], restriction? }` · `priceHistory { productId, currentBdt, points: [{ date, bdt }] }` ·
  `login { reason }` (a sign-in-only tool was asked for by a guest) · `request { id, title }`
- `done { conversationId, messageId, degraded, remaining }` (last) · `error { code, message }`
- `AssistantProduct`: `{ id, market, category, title, brand, image, placeholder, priceBdt, mrpBdt, rating, reviews, inStock, weightKg, restriction }`
- `OrderCard`: `{ id, status, createdAt, total, paymentStatus, trackingCode, itemCount, items: [{ title, image, qty }], expectedBy? }`
- `?stream=0` → JSON `{ conversationId, messageId, text, events: [{ type, data }], degraded, remaining }` (same events, for clients without streaming).
- Daily limit (UTC day): guests 10 per IP, signed-in 40, Skydrop Plus members 120 (admin settings) → `429 { code: "rate_limited", limit, remaining: 0, retryAfter }`.
- Server-side tool loop (max 5 tool rounds, then a final answer without tools): `search_products`, `get_product`, `compare_products`,
  `delivery_estimate` (holiday cutoffs from admin settings), `get_my_orders` / `track_order` (signed-in only, own orders only:
  another user's order id answers `order_not_found`), `add_to_cart_suggestion`, `get_price_history` (feature `price_history`),
  `create_product_request` (when a `product_requests` table exists and feature `product_requests` is on). Prices only come from tools (BDT).
- System prompt and tool definitions use prompt caching (`cache_control: ephemeral`).
- Budget: monthly USD budget with a hard stop. At the limit (or without a key, or on API errors) the reply degrades to plain
  catalog search (`degraded: true`). The admin model is used until `fallback_at_pct` of the budget, then the cheaper fallback
  model; an overloaded/5xx primary is retried once on the fallback.
- Storage: `assistant_conversations`, `assistant_messages` (PII-scrubbed text, cards, tools, tokens incl. cache read/write, `cost_usd`),
  `assistant_feedback`. Phone numbers, emails and long digit runs are replaced before storing; message text is never logged.

`GET /assistant/conversations/{id}` → `{ id, messages: [{ id, role, text, events, degraded, createdAt }] }` (owner only; guest
conversations only without a token) · `DELETE /assistant/conversations/{id}` → `{ ok }` (wipes the transcript)
`POST /assistant/feedback { messageId, rating: "up"|"down", comment? }` → `{ ok }` (one vote per shopper per message)

Admin: System → **AI conversations** (read-only, masked phones; permission `assistant.view`, with the usage/cost widget),
System → **AI assistant** settings (enable, model, fallback model and threshold, monthly budget, daily limits, greeting and
suggested prompts in en/bn, holiday cutoffs `YYYY-MM-DD | Name | note` per line; permission `settings.manage`).

## Wallet, gift cards, Skydrop Plus, card EMI (money module)

Feature switches (Admin → System → Features): `wallet`, `gift_cards`, `membership`, `pay.emi`. Routes in `routes/api_wallet.php`;
switched-off routes answer `404 { error: "feature_disabled", feature }`. Settings: Admin → Wallet & Plus → Settings (`money.*` keys,
defaults in `config/money.php`). `GET /checkout/options` includes `card_emi` (when `pay.emi` is on), `membership` and `emi` (config).

**Wallet (auth)** `GET /wallet` → `{ balanceBdt, expiringBdt, expiringAt, history: [{ id, type: "credit"|"debit", amountBdt, reason, note,
orderId, balanceAfterBdt, expiresAt, at }], refund: { default: "wallet"|"original", bonusPct } }`
- reasons: `refund | gift_card | admin | promo | order_payment | expire`. Ledger `wallet_ledger`, cached `users.wallet_balance_bdt`; every
  change locks the user row and the balance can never go negative. Credits may expire (FIFO, soonest-expiring spent first; daily job).

**Checkout additions** — `POST /checkout/quote` and `POST /orders` accept `useWallet` (true = as much as possible, or a BDT cap),
`payment: "card_emi"` with `emiTenure` (+ optional `emiBank`). The quote adds:
`membershipDiscount, membership: { active, plan, lines: [{ key, label, amountBdt }], savedBdt, pointsMultiplier, bonusPoints } | null,
plusPreview: { plan, savedBdt, lines } | null` (non-members: "You'd save ৳X with Plus"), `wallet: { balanceBdt, appliedBdt } | null,
walletApplied, emi: { eligible, reason, tenure, bank, bankName, ratePct, chargeBdt, totalBdt, monthlyBdt } | null, emiCharge,
emiAvailable, payNow` (= total − walletApplied). `discount`/`total`/`pointsToEarn` include Plus; `total` includes the EMI charge.
- Order: wallet is debited inside the order transaction (a `payments` row with gateway `wallet`); the gateway collects the rest. A fully
  covered order is paid immediately. With `advance50` the wallet counts towards the advance.
- `card_emi` below the minimum / not offered → `422 errors.emiTenure`. SSLCommerz gets `emi_option=1, emi_max_inst_option, emi_selected_inst,
  emi_allow_only=1`. `Order` JSON adds `kind, walletBdt, membershipDiscount, emiChargeBdt, emi`.
- `POST /orders/{id}/cancel { reason?, refundTo?: "wallet"|"original" }` — wallet payments always go back to the wallet; others follow
  `refundTo`, else the admin default. Wallet refunds are instant and get the optional bonus %.

**Gift cards** `GET /gift-cards/config` → `{ presets, minBdt, maxBdt, expiryMonths, designs: [{ key, label }] }`
`POST /gift-cards { amountBdt, design, recipientName, recipientPhone? | recipientEmail?, message?, payment: bkash|nagad|card, returnUrl? }` (auth)
→ `201 { giftCard, order: { id, total }, payment }` — an order of `kind: "gift_card"` (not listed in `GET /orders`) paid through the normal
gateway flow; default return `{WEB_URL}/gift-cards/success?order=&status=`. Once paid the card is activated and the code sent by SMS/email.
`GET /gift-cards` (auth) → cards you bought, with codes. `POST /gift-cards/redeem { code }` (auth, also needs `wallet`, 10/min)
→ `{ amountBdt, balanceBdt, giftCard }`; the whole balance moves into the wallet. Codes: 16 chars, shown `XXXX-XXXX-XXXX-XXXX`.

**Skydrop Plus** `GET /membership/plans` → `{ items: [{ id, name, slug, priceBdt, period: monthly|quarterly|yearly, months, monthlyBdt,
highlight, tagline, benefits, benefitList }] }` · `GET /membership` (auth) → `{ membership: { status, plan, startedAt, renewsAt, cancelledAt,
daysLeft } | null, benefits, savings: { totalBdt, ordersBdt, creditsBdt, orders, paidBdt } }` ·
`POST /membership { plan (slug|id), payment, returnUrl? }` (auth) → `201 { order, plan, payment }` (order `kind: "membership"`).
- Benefits (admin plan builder): `free_shipping`, `free_shipping_threshold_bdt`, `shipping_discount_pct`, `points_multiplier`,
  `extra_discount_pct` + `extra_discount_cap_bdt`, `free_unboxing`, `priority_support`, `early_flash_sale_access_minutes`,
  `monthly_wallet_credit_bdt`, `free_returns`. Server code reads them only through `MembershipService::benefitsFor($user)`.
- Renewal is manual (BD gateways have no reliable recurring billing): paying again stacks on the paid-through date; a daily job texts a
  pay link `{WEB_URL}/plus?renew={slug}` `reminder_days` before `renewsAt`, expires lapsed plans and gives the monthly wallet credit.

**EMI** `GET /emi?amount=` → `{ enabled, minOrderBdt, tenures, banks: [{ code, name, rates: { "3": pct, … } }], amountBdt?, eligible?,
fromMonthlyBdt?, options?: EmiPlan[] }` (public; `enabled:false` when `pay.emi` or the EMI setting is off). Charge = rate % of the amount
paid by card, paid by the customer, added to the order total. bKash "pay later" is a TODO (needs merchant approval).
