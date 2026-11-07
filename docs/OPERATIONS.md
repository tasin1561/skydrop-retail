# Skydrop Retail operations

Running the API in production: worker and scheduler, health endpoints, alerting, system limits and maintenance mode.
Security setup (keys, Cloudflare, backups) is in `SECURITY.md`; the API contract is in `API.md`.

## 1. Processes

Three long-running things must be up besides the web server (PHP-FPM / Octane):

| Process | Command | Notes |
| --- | --- | --- |
| Queue worker | `php artisan queue:work --tries=3 --max-time=3600` | Live fetches, messaging, fraud scoring, broadcasts. Run 2+ under Supervisor/systemd. With priority queues: `--queue=notifications,default,marketing` (see `config/messaging.php`). |
| Scheduler | `php artisan schedule:work` | Or cron: `* * * * * cd /var/www/api && php artisan schedule:run >> /dev/null 2>&1`. |
| Next.js | `pnpm --filter web start` | Not covered here. |

After every deploy: `php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan queue:restart`
(or Admin → System → Settings → "Restart queue workers"). `queue:restart` only asks workers to exit after their current job;
Supervisor/systemd must start them again.

Example Supervisor program:

```ini
[program:skydrop-worker]
command=php /var/www/api/artisan queue:work --tries=3 --max-time=3600 --sleep=2
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
stopwaitsecs=120
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/api/storage/logs/worker.log

[program:skydrop-scheduler]
command=php /var/www/api/artisan schedule:work
autostart=true
autorestart=true
user=www-data
```

### Heartbeats

Every minute the scheduler writes a `scheduler` heartbeat and queues `App\Jobs\System\WorkerHeartbeat`, which writes
a `worker` heartbeat when a worker runs it (table `system_heartbeats`). Older than 3 minutes (`HEARTBEAT_STALE_AFTER`, seconds)
→ the Queue card on Admin → System → Health goes red and `GET /api/v1/health/ready` returns 503. With `QUEUE_CONNECTION=sync`
there is no worker, so the worker heartbeat is not required.

## 2. Health endpoints (prefix `/api/v1`)

| Endpoint | Auth | Answer |
| --- | --- | --- |
| `GET /health` | none | Liveness: `200 { ok: true, time }`. |
| `GET /health/ready` | none | `200 { ok: true, checks: { database, cache, queue }, time }` or `503` with the same body (`ok:false`). `queue` = fresh worker heartbeat (always true on a sync queue). |
| `GET /health/details` | `X-Health-Token: <HEALTH_TOKEN>` (or `Authorization: Bearer`) | The full report behind Admin → System → Health: `{ status: green\|amber\|red, generatedAt, cards: { app, database, cache, queue, providers, fetchJobs, messaging, payments, storage, security, features, errors } }`. Each card `{ title, status, summary, items: [{ label, value, status }] , ... }`. `503` when the overall status is red. `404` when `HEALTH_TOKEN` is unset, `401` on a wrong token. |

Health routes skip the per-IP API limit. Suggested monitors:
- **UptimeRobot / Better Stack (HTTP keyword)**: `GET https://api.example.com/api/v1/health/ready`, alert on non-200, every 1–3 min.
- **Better Stack (JSON / keyword)**: `GET /api/v1/health/details` with header `X-Health-Token`, alert when the body contains `"status":"red"` (or on 503).

## 3. Admin → System → Health

Dashboard (permission `system.health`), refreshes every 30 s (Livewire polling). Cards turn green / amber / red:

| Card | Red when | Amber when |
| --- | --- | --- |
| App | `APP_DEBUG=true` in production | debug on elsewhere |
| Database | connection fails | latency > 250 ms |
| Cache & sessions | write/read test fails | |
| Queue | worker or scheduler heartbeat stale; failed jobs in the last hour ≥ `HEALTH_FAILED_JOBS_SPIKE` (20) | any failed job in 24 h; oldest pending > 5 min |
| Data providers | a circuit open > 15 min; Oxylabs daily cap reached; monthly budget ≥ 100% | any circuit open/half-open; ≥ 90% of budget; projected month-end over budget |
| Fetch jobs (1h) | ≥ 5 failed and more failed than done | any failed |
| Messaging | an SMS gateway balance below the low-balance threshold | any SMS / email / push failed in 24 h |
| Payments | gateways not `live` in production | any payment failed in 24 h |
| Storage | disk free < 10% | < 20% |
| Security | | staff without 2FA |
| Feature switches | | maintenance on or checkout off |
| Errors | ≥ 50 occurrences in the last hour | any exception in 24 h |

Actions: retry / forget a failed job, reset a provider circuit, refresh the SMS balance (cached 30 min), clear grouped errors.
Exceptions are grouped in `error_events` by class + file + line (`App\Services\System\ErrorRecorder`, registered as a reportable
callback; at most one write per group per minute, counts in between are added on the next write; messages are secret-masked).

## 4. Alerts: `retail:health-check`

Scheduled every 5 minutes (`SystemServiceProvider`). Conditions: database down, worker heartbeat stale, failed-jobs spike,
provider circuit open > 15 min, SMS balance low, disk free < 10%, Oxylabs daily cap hit. Each condition alerts **once** when it
turns red and sends one **recovery** message when it clears (state in `storage/framework/health-alerts.json`, so it works even
when the DB or cache is the problem).

Channels: `Notifier::admin` (email to `ALERT_EMAIL`, else the log) and Telegram (`ALERT_TELEGRAM_BOT` + the chat id from
Admin → Settings "Alert Telegram chat id", else `ALERT_TELEGRAM_CHAT`). Run by hand: `php artisan retail:health-check`
(add `--prune-logs` to also apply log retention).

## 5. System limits (Admin → System → Limits)

`App\Support\Limits` is the one registry for every operational limit (permission `system.limits`). Values are stored as
`limits.{key}` (cached 60 s); limits that another module already owned read and write that module's own setting, so the older
pages show the same value. Each field shows its unit, help, default, a reset button and current usage. Saves are logged
(`limits.updated`).

| Group | Key | Default | Enforced in |
| --- | --- | --- | --- |
| Catalog | `catalog.oxylabs_monthly_usd` | 100 USD (setting `oxylabs.monthly_budget_usd`) | `BudgetGuard` |
| | `catalog.oxylabs_daily_usd` | 10 USD (0 = off) | `BudgetGuard::dailyCapHit()` → browsing cache/fallback only, checkout verify still live |
| | `catalog.store_daily_fetches` | 5000 calls (0 = off) | `BudgetGuard::allowsStore()` in `ProviderChain` (verify exempt) |
| | `catalog.max_concurrent_fetches` | 10 | `OxylabsClient::pool`, sibling / deal batches |
| | `catalog.variant_max` | 60 (setting `variants.max`) | `CatalogService::variantPlan` |
| | `catalog.search_pages` | 5 | `CatalogService::search` (store pages fetched per query) |
| | `catalog.product_ttl_min_minutes` / `catalog.search_ttl_min_minutes` | 30 / 60 min | `CatalogService::isFresh`, search freshness |
| | `catalog.searches_per_min` / `catalog.product_views_per_min` / `catalog.resolves_per_min` | config `security.limits.strict_ip` (40) / 40 / `resolve_ip` (10) | `CatalogThrottle` (per IP, separate buckets) |
| Orders | `orders.max_items` / `orders.max_qty` | 50 / 20 | quote, verify and order → `422 code limit_items / limit_qty` |
| | `orders.max_value_bdt` | 500000 | quote + order → `422 limit_order_value` |
| | `orders.min_value_bdt` | 0 (off) | order only → `422 limit_min_order` |
| | `orders.max_per_day` | 10 | order → `422 limit_orders_per_day` |
| | `orders.max_partial_pay_bdt` | 50000 | quote/order with `advance50` → `422 full_prepayment_required` (`errors.payment`) |
| | `orders.max_unpaid` | 5 | order → `422 limit_unpaid_orders` |
| Accounts | `accounts.otp_per_phone_hour` / `accounts.otp_per_ip_hour` | 3 / 10 | `OtpService` (429) |
| | `accounts.otp_wrong_lockout` | config `security.otp.wrong_lockout` (5) | `ApiShield` |
| | `accounts.max_addresses` / `max_wishlist` / `max_price_alerts` | 10 / 200 / 50 | `AddressService` (checkout inline addresses exempt), `WishlistController`, `WatchController` (422) |
| Messaging | `messaging.sms_per_phone_hour` | 8 (setting `messaging.sms.per_phone_per_hour`) | `DeliverMessage` |
| | `messaging.marketing_sms_per_day` | 5000 | `DeliverMessage` (marketing SMS fail as "Daily marketing SMS limit reached") |
| | `messaging.broadcast_max_recipients` | 20000 | `SendBroadcast` |
| AI assistant | `assistant.guest_per_day` / `user_per_day` / `plus_per_day` / `monthly_budget_usd` | 10 / 40 / 120 / 50 USD (settings `assistant.limits.*`, `assistant.monthly_budget_usd`) | `AssistantLimits`, `AssistantSettings` |
| Uploads | `uploads.image_mb` / `video_mb` / `max_files` | 8 / 50 MB / 10 | `POST /uploads`, returns `photos`, Hub receive form |
| API | `api.per_ip_per_minute` | 300 | `PublicApiThrottle` (outermost `api` middleware; Next.js internal key, webhooks and health exempt; only with `SECURITY_ENFORCE`) |
| | `api.admin_session_minutes` | config `security.admin.session_timeout_minutes` (60) | `AdminGuard` |

## 6. Admin → System → Settings (maintenance)

Permission `system.settings`.
- **Maintenance mode** with a message: `GET /api/v1/features` returns `maintenance: { on, message, since }`. The web replaces every
  page with a "We'll be right back" screen (`MaintenanceGate` in `app/layout.tsx`); the app shows a full-screen overlay on start
  with "Try again". The API, admin and webhooks keep working (this is not `artisan down`).
- **Cache clear**: catalog (cached search pages, live-store flags, CMS caches), settings (all settings caches, knobs, limits),
  features.
- **Restart queue workers** (`queue:restart`).
- **Log retention** (`system.log_retention_days`, default `LOG_RETENTION_DAYS` 14; daily at 03:55) and a read-only viewer of the
  last 50–500 lines of the newest `storage/logs/*.log` with configured secrets, Bearer tokens and `key=value` credentials masked.

## 7. Environment

| Variable | Default | Purpose |
| --- | --- | --- |
| `HEALTH_TOKEN` | – | Enables `GET /health/details` (send as `X-Health-Token`). |
| `HEARTBEAT_STALE_AFTER` | 180 | Seconds before a heartbeat is stale. |
| `HEALTH_FAILED_JOBS_SPIKE` | 20 | Failed jobs per hour that count as a spike (0 = off). |
| `LOG_RETENTION_DAYS` | 14 | Default log retention. |
| `APP_VERSION`, `APP_DEPLOYED_AT` | – | Shown on the App card (else the git short sha and the config-cache time). |
| `ALERT_EMAIL`, `ALERT_TELEGRAM_BOT`, `ALERT_TELEGRAM_CHAT` | – | Alert channels. |
