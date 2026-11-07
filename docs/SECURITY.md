# Skydrop Retail security

Goal: make scraping the catalog and reaching the database **very hard and expensive**, not impossible. Anything a
browser or app can display can ultimately be copied by a determined person; what we stop is cheap bulk scraping,
credential/OTP abuse, admin takeover and data leaks.

## 1. What is protected, and how

### Catalog API (`catalog.guard`)
- **Server-to-server only**: the Next.js server calls catalog endpoints with `X-Internal-Key` (`INTERNAL_API_KEY`,
  `apps/web/src/lib/data.ts`). Browser features that need catalog data (suggest, wishlist cards, marketplace switches,
  job polling) go through Next.js route handlers in `apps/web/src/app/api/*`, so the browser never talks to the catalog API.
- **Signed app clients**: the mobile app signs every request (`X-App-Key`, `X-App-Timestamp`, HMAC-SHA256 signature over
  timestamp + method + path + query, 5-minute window; `apps/mobile/src/api/sign.ts`). The secret ships inside the app,
  so this stops copy-paste curl and replays, not a reverse engineer: rotate the app key with each major release
  (`APP_SIGNING_KEYS` accepts several `id:secret` pairs so old builds keep working during rollout).
- **Rate limits** (Laravel RateLimiter, `App\Http\Middleware\CatalogThrottle`): per IP, per signed-in user and per
  device fingerprint; strict buckets for search, suggest, resolve, product detail and price history; progressive slowdown
  then 429. Search, product-view and paste-link-resolve per-IP limits are editable on Admin → System → Limits, and every
  `/api/v1` request also has a per-IP ceiling (`PublicApiThrottle`, default 300/min). Health, worker and alerting: `docs/OPERATIONS.md`. The Next.js server has one high bucket; shoppers are limited by Cloudflare in front of Next.js (rules below).
- **Pagination caps**: page ≤ 20, perPage ≤ 48, `ids` ≤ 48. Product ids are marketplace ids (ASIN etc.), not sequential;
  bursts of 404s on product ids flag the IP.
- **Bot detection** (`BotGuard`): automation user agents, headless hints, missing `Accept-Language`, more than 40 distinct
  products per minute per IP (sliding window in cache) → Turnstile challenge.
- **Honeypots**: a hidden footer link (`/api/catalog-export`, disallowed in robots.txt) and the API routes
  `GET /api/v1/products/export` and `/products/SR-HONEYPOT-0001`. A hit blocks the IP for 24 h (Admin → Sales → Blocklist)
  and is logged (Admin → System → Activity log, action `security.honeypot`).

### Accounts
- **OTP abuse**: `OtpService` allows 3 codes per phone and 10 per IP per hour (Admin → System → Limits) and 5 attempts per code. `ApiShield` adds a
  60 s cooldown per phone, a 30 min lockout after 5 wrong codes (across codes; limit `accounts.otp_wrong_lockout`), 20 verify attempts per IP per 10 min,
  the phone blocklist and Cloudflare Turnstile on `POST /auth/otp/request` (when `TURNSTILE_*` keys are set).
- **Fraud check** on every order (`App\Services\Fraud`): courier history (Steadfast live; Pathao, RedX, CarryBee stubs) and
  internal signals. High risk → "Needs review" (no automatic verification) and full prepayment instead of the 50% advance.

### Admin panel (`/admin`)
- **2FA (TOTP + recovery codes)** via Filament's app authentication; required for admin and manager roles
  (`ADMIN_REQUIRE_2FA`, or Admin → System → Finance & security). Secrets and recovery codes are encrypted columns.
- **Optional IP allowlist** and an **idle session timeout** (default 60 min) in the same settings page.
- **Strong passwords**: `Password::defaults()` = 12+ chars, mixed case, numbers, symbols.
- **Activity log** of every staff action (model diffs, Filament actions, exports, settings, sign-ins, failed sign-ins with
  IP and user agent), read-only, retention setting (default 365 days).

### Data
- Internal fields (purchase costs, supplier/marketplace refs, FX paid, payment payloads, staff notes, fraud data, raw HTML)
  are hidden from every model `toArray()/toJson()` (`OpsServiceProvider::INTERNAL_FIELDS`); API responses use explicit shapes.
- **Encrypted at rest**: secret settings (`*token*`, `*secret*`, `*password*`, `*_key`) in the `settings` table
  (`App\Casts\SettingValue`, masked as `••••last4` on the Settings page; blank = keep), 2FA secrets, blocklist values
  (looked up by keyed hash), courier check payloads (phones stored only as keyed hashes). Payment `gateway_ref` stays plain
  because reconciliation and webhooks look it up; the full payload is hidden from JSON.
- **Signed URLs**: invoices (`/api/v1/invoices/{id}`, temporary signed route) and mock payment pages. Keep uploads on a
  private disk and serve them through signed temporary URLs.
- **Headers**: API/admin: HSTS (HTTPS only), `X-Frame-Options: DENY`, CSP `frame-ancestors 'none'` (`default-src 'none'` on
  the API), `nosniff`, `Referrer-Policy`, `Permissions-Policy`. Web: CSP in `apps/web/next.config.ts` allowing GTM/GA4,
  Meta, TikTok, Turnstile and the API origin; next/og is same-origin.

## 2. Deploy checklist

### Environment (API)
```
APP_ENV=production
APP_DEBUG=false                 # never true in production (leaks env + stack traces)
APP_URL=https://api.retail.skydrop.online
INTERNAL_API_KEY=<64 random chars, same value in the web server env>
APP_SIGNING_KEYS=mobile1:<48 random chars>
TURNSTILE_SITE_KEY=... TURNSTILE_SECRET_KEY=...
ADMIN_REQUIRE_2FA=true
ADMIN_IP_ALLOWLIST=             # optional, e.g. office IP / VPN egress
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
STEADFAST_API_KEY=... STEADFAST_SECRET_KEY=...
```
- `.env` lives outside the web root (`public/` is the only document root). Deny dotfiles in nginx:
  `location ~ /\. { deny all; }`. Never commit `.env`; `chmod 600 .env`, owned by the deploy user.
- Run `php artisan config:cache route:cache`.

### MySQL
- Bind to localhost only: `bind-address = 127.0.0.1` (or a private network interface), firewall 3306 from the internet.
- App user with only what it needs on one schema:
  `CREATE USER 'retail'@'localhost' IDENTIFIED BY '<strong>'; GRANT SELECT, INSERT, UPDATE, DELETE ON retail.* TO 'retail'@'localhost';`
  Use a separate `retail_migrate` user (adds `CREATE, ALTER, DROP, INDEX, REFERENCES`) only for `php artisan migrate`.
- No phpMyAdmin/Adminer on the public host; use an SSH tunnel.

### Backups
- Nightly `mysqldump --single-transaction` piped through `gzip | age -r <public key>` (or `gpg --encrypt`), uploaded to an
  off-site bucket with object lock / versioning. Keep the private key offline. Test a restore monthly.
- `APP_KEY` must be backed up separately (encrypted columns are unreadable without it).

### Cloudflare (in front of web and API)
- Proxy both hosts (orange cloud); SSL "Full (strict)"; Always use HTTPS; HSTS on; minimum TLS 1.2.
- **Bot Fight Mode** (or Super Bot Fight Mode on Pro: block "definitely automated", challenge "likely automated";
  allow verified bots on the web host only).
- WAF custom rules:
  1. API host: block requests to `/api/v1/(search|suggest|products|offers|marketplaces|jobs)` without `X-Internal-Key` or
     `X-App-Key` header (`not any(http.request.headers.names[*] in {"x-internal-key" "x-app-key"})`).
  2. API host: allow `/api/v1/webhooks/*` only from gateway/courier IPs if they publish them.
  3. `/admin*`: managed challenge unless IP in your office/VPN list.
  4. Country rule: challenge (not block) traffic from outside BD/IN for `/login`, `/api/v1/auth/*`.
- Rate limiting rules: web `/search*` 60/min per IP; web `/p/*` 120/min per IP; API `/api/v1/auth/otp/*` 10/min per IP.
- Restrict the origin to Cloudflare IP ranges (firewall), then set `TRUST_CF_CONNECTING_IP=true` so rate limits and blocks use the real client IP.
- Turnstile: create a widget for the web host; put the keys in env.

### Key rotation
- **Oxylabs**: the token was shared in chat earlier: **rotate it now** in the Oxylabs dashboard and update `OXYLABS_TOKEN`
  / `OXYLABS_USERNAME` / `OXYLABS_PASSWORD`.
- Rotate on staff departure and at least yearly: `INTERNAL_API_KEY` (web + API together), `APP_SIGNING_KEYS` (add new
  pair, ship app, remove old), payment gateway keys, SMS keys, courier keys, Meta/TikTok/GA4 tokens (Admin → Settings).
- `APP_KEY` rotation: set `APP_PREVIOUS_KEYS=<old>` with the new `APP_KEY` so encrypted settings, 2FA secrets and blocklist
  values still decrypt, then re-save them. Blocklist lookup hashes use `APP_KEY`; re-add entries after rotating.

## 3. What this does not stop
- A person reading pages in a real browser and copying them slowly. Cloudflare limits and the honeypot make bulk copying slow.
- A reverse-engineered mobile app secret. Rotate keys; consider Play Integrity / App Attest later.
- Payment fraud beyond what courier history and our signals show: staff still review "Needs review" orders.
