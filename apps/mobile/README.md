# Skydrop Retail: Android and iOS app

Expo (SDK 58) + React Native + TypeScript + expo-router. One codebase ships to Android and iOS.
Shoppers in Bangladesh buy from Indian stores. The app names the source of every item in plain text:

| Store | Source |
| --- | --- |
| Amazon | amazon.in |
| Flipkart | flipkart.com |
| BigBasket | bigbasket.com |
| All Stores | every enabled store |

We buy in India and Skydrop delivers to the shopper's door. Prices are in BDT. The app uses no third-party logos, colours or trade dress.

- App name: **Skydrop Retail**
- Android package / iOS bundle id: `online.skydrop.retail`
- Deep-link scheme: `skydropretail://`

## Run it

From the repo root, install once with `pnpm install`. Then:

```bash
cd apps/mobile
cp .env.example .env          # optional: set EXPO_PUBLIC_API_URL (Laravel base URL, without /api/v1)
npx expo start                # scan the QR code with Expo Go (Android or iOS)
```

**Demo mode.** When `EXPO_PUBLIC_API_URL` is empty, or the API doesn't answer `GET /api/v1/marketplaces` within 4 s, the app runs entirely on the device:

- catalog: `src/demo/catalog.json`
- search, product, verify, quote, orders: `src/demo/backend.ts`
- orders, addresses, wishlist and user: AsyncStorage

In demo mode OTP sign-in accepts any 6 digits, payments are not taken, and order tracking moves forward one stage every 2 minutes. A "Demo data" pill is shown on Home.

### Android

- **Expo Go:** `npx expo start`, then press `a` or scan the QR code.
- **Native dev build** (Android Studio + SDK installed): `npx expo run:android`.
- **Installable APK via EAS** (cloud, no local SDK needed):
  ```bash
  npx eas-cli@latest login
  npx eas-cli@latest build -p android --profile preview      # APK, internal distribution
  npx eas-cli@latest build -p android --profile production   # AAB for Google Play
  ```

### iOS

An **Apple Developer Program account** (paid) is required for device builds, TestFlight and the App Store. Expo Go and the simulator work without one.

- **Expo Go:** install Expo Go from the App Store, run `npx expo start` and scan the QR code with the Camera app.
- **Native build on a Mac** (Xcode installed): `npx expo run:ios` (simulator), or `npx expo run:ios --device` for a plugged-in iPhone.
- **EAS cloud builds** (no Mac needed):
  ```bash
  npx eas-cli@latest build -p ios --profile preview      # internal distribution (ad hoc, registered devices)
  npx eas-cli@latest build -p ios --profile production   # App Store / TestFlight build
  npx eas-cli@latest submit -p ios --profile production  # upload to App Store Connect -> TestFlight
  ```
  - EAS asks for your Apple credentials and creates the certificates and provisioning profiles.
  - For `preview`, register test devices first with `npx eas-cli@latest device:create`.
  - Put the App Store Connect app id in `eas.json` under `submit.production.ios.ascAppId`.

## Scripts

```bash
pnpm typecheck                         # tsc --noEmit
pnpm lint                              # expo lint (eslint-config-expo, flat config in eslint.config.js)
pnpm demo-catalog                      # rebuild src/demo/catalog.json from apps/web/src/data/catalog.json
pnpm i18n                              # copy the en/bn dictionaries and i18n core from apps/web/src/i18n
npx expo export --platform android     # bundle check
npx expo export --platform ios
npx expo-doctor
```

## Structure

```
src/app/                 expo-router routes
  (tabs)/                Home, Categories, Cart, Orders, Account (custom tab bar)
  product/[id]           gallery + HLS video, variants, buy bar, breakdown, delivery route, specs, reviews
  store/[market]         themed store front with infinite grid
  search                 store selector, debounced suggestions, filters/sort sheets, infinite scroll
  checkout/index         4-step checkout, live verify, Skydrop Points toggle, payment redirect, van animation
  checkout/success       payment return deep link (skydropretail://checkout/success?order=<id>)
  success                parachute success screen
  deals                  Live deals and offers (store tabs)
  points                 Skydrop Points balance, rules, history
  order/[id]             tracking timeline
  login, profile, addresses, wishlist
src/api/                 typed client for docs/API.md; demo fallback
src/demo/                trimmed catalog + local backend
src/lib/                 types, markets, pricing + points (verbatim from web), bd, orderStatus, cart math, phone, variants
src/components/offers/   offer cards, deal badge, countdown (shared 1 s ticker, paused in background)
src/components/          UI primitives, sheets, cards, checkout/product parts
src/state/store.tsx      cart, coupon, wishlist, session, store mode (AsyncStorage; token in SecureStore)
src/theme/               light/dark/system palettes and store accents (from apps/web globals.css)
assets/icon-src/         original SVG sources for the icon (parcel under a parachute)
scripts/make-demo-catalog.mjs
```

## Notes

- **Language (EN | বাংলা).** English is the default. The switch is on the Account tab (shown while the `bangla` feature switch is on). The choice is kept in AsyncStorage and, for signed-in shoppers, saved to the profile with `PATCH /me { locale }`. `src/i18n/en.ts`, `bn.ts` and `core.ts` are generated copies of `apps/web/src/i18n/`: edit the web files, then run `pnpm i18n` (`scripts/sync-i18n.mjs`). Screens use `const { t, bdt } = useT()` from `@/i18n`; `bdt()` prints taka with Bangla digits in Bangla mode (one switch, `BANGLA_DIGITS` in `core.ts`). In Bangla mode the `T` text component uses Noto Sans Bengali (`@expo-google-fonts/noto-sans-bengali`, wired in `src/theme/fonts.ts`) with a little more line height. Store names, brand names and product data stay in English.
- **Pricing.** `src/lib/pricing.ts` is a verbatim copy of `apps/web/src/lib/pricing.ts`; keep them in sync. When the API returns `priceBdt`/`mrpBdt`, the app shows the server figures.
- **Video.** Product videos use `expo-video`: AVPlayer on iOS, ExoPlayer on Android. They play HLS natively, with `contentType: "hls"` set for iOS.
- **Platform details.**
  - iOS: native stack with full-screen swipe-back, `oneTimeCode` SMS autofill on the OTP field, keyboard avoidance on login, checkout, addresses and profile.
  - Android: `softwareKeyboardLayoutMode: resize` and SMS OTP autofill hints.
  - Both: haptics via `expo-haptics`.
- **Icon.** Original artwork (Midnight Luxe: ink background, violet-gradient parachute, coral parcel), generated from `assets/icon-src/*.svg` by `scripts/make-icons.sh` (needs `rsvg-convert`). The adaptive icon has separate foreground, background and monochrome layers.
- **Theme.** `src/theme/index.tsx` mirrors the web "Midnight Luxe" tokens in `apps/web/src/app/globals.css`: light and dark palettes, violet primary (`brand`), coral signal (`coral`), and per-store accents (Amazon violet, Flipkart pink, BigBasket green).
- **Font.** The brand font is Plus Jakarta Sans. The app uses the system font until the Google font package is added:
  1. `npx expo install @expo-google-fonts/plus-jakarta-sans` (`expo-font` is already installed).
  2. Load the weights with `useFonts` in `src/app/_layout.tsx` (keep the splash up until they load) and set `fontFamily` in the `T` text component in `src/components/ui.tsx`.

## Offers, points, alerts, payments

- **Offers.** Every product offer is shown on the product screen as "Offers on <source>" cards.
  - Each card is tagged "In your price" (coupons and deal prices we use when buying) or "On the source store" (bank, card and EMI offers in India).
  - Cards show countdowns and % claimed. Countdowns share one 1 s ticker that pauses while the app is in the background.
  - Product cards show the deal badge with a countdown, plus "₹X coupon already applied" or "N offers on the source store".
  - The Live Deals screen is reached from Home (header button and the "Live deals and offers" rail).
  - It loads `GET /offers?store=&limit=`. Demo: products with offers first, then the deepest discounts.
- **Skydrop Points.** `src/lib/points.ts` is identical to the web version.
  - Account > Skydrop Points (`GET /points`; demo ledger built from local orders).
  - The checkout review step has a "Use N Skydrop Points" ON/OFF toggle (capped by `maxRedeem`) and sends `redeemPoints` in `POST /orders`.
  - The summary shows the points discount and "You'll earn N points".
- **Alerts and recently viewed.**
  - The product screen has an "Earn N Skydrop Points" chip and an "Alert me if the price drops" / "Tell me when it's back" toggle. It calls `POST /watches` live and is stored on the device in demo.
  - "Recently viewed" on Home holds the last 20 products, stored locally.
- **Payments.**
  - `POST /orders` is sent with `returnUrl: "skydropretail://checkout/success"`; the API is expected to append `?order=<id>`.
  - When the response has `payment.redirectUrl`, the app opens it with `WebBrowser.openAuthSessionAsync`.
  - On return it refreshes the order and shows success.
  - On cancel or close it says the order is saved and opens the order page.
  - The deep-link route `checkout/success` handles returns that arrive outside the auth session.
- **Pending data.** When `/products/{id}` or `/search` return `status: "pending"` with a `jobId`, the app shows a "Fetching live details from <store>" shimmer.
  - It polls `GET /jobs/{id}` every 1.5 s for up to about 25 s, and stops when the screen loses focus.
  - It reloads when the job is done.
- `/offers`, `/points`, `/watches`, `redeemPoints` and `returnUrl` are not in `docs/API.md` yet. The app follows `apps/web/src/lib/apiClient.ts` for them.

## Notifications and push

- **Notification centre.** The bell on the Home header (hidden when signed out or when the `in_app_notifications` feature is off) shows the unread count
  and opens the Notifications screen (`src/app/notifications.tsx`): Today / Earlier groups, pull-to-refresh, infinite scroll, tap to open
  (deep link + mark read), long-press or × to delete, "Read all". State lives in `src/lib/notifications.ts`; it polls `GET /notifications`
  every 60 s while the app is in the foreground. Demo mode builds items from the local demo orders.
- **Feature switches.** `src/lib/features.ts` fetches `GET /api/v1/features` once at startup; `useFeature("push")` etc. gate the UI.
- **Cart sync.** Signed-in shoppers' carts are sent with `PUT /cart` (debounced 1.5 s) so the API can send abandoned-cart reminders.
- **Push notifications** (`expo-notifications` + `expo-device`, installed with `npx expo install`; plugin in `app.json`). `src/lib/push.ts`:
  - **No prompt on launch.** On launch and after sign-in the app only refreshes the Expo token when permission was already granted.
    The system prompt is offered (once per install) on the order success screen ("Get delivery updates on this phone?") and from
    Account → Notification settings → "Turn on push". A blocked permission shows "Open phone settings".
  - Tokens go to `POST /api/v1/push/expo-token { token, platform }` and are removed with `DELETE /push/expo-token` on sign-out.
  - Foreground pushes show as a banner. Taps (also a cold start) open the push's `data.route`, or map `data.link`:
    `/orders/{id}` → `/order/{id}`, `/orders/{id}/unboxing` → `/unboxing/{id}`, `/p/{id}` → `/product/{id}`, `/group/{code}`, `/cart`,
    `/account/points`, `/account/wallet`; anything else opens the Notifications screen.
  - Android channel `default` ("Order updates", high importance). The notification icon is `assets/images/android-icon-monochrome.png`.
  - Push does not work in Expo Go on Android (SDK 53+) or on simulators; use a development / preview build on a real phone.

  **Credentials (not in the repo; nothing secret is committed):**
  1. `npx eas-cli@latest init` writes `extra.eas.projectId` into `app.json` (the Expo push token needs it).
  2. Android: create a Firebase project with the package `online.skydrop.retail`, download `google-services.json` to
     `apps/mobile/google-services.json` (git-ignore it or use an EAS file secret), add `"googleServicesFile": "./google-services.json"`
     under `android` in `app.json`, then upload the FCM V1 service-account key in expo.dev → Project → Credentials → Android.
  3. iOS: `npx eas-cli@latest credentials -p ios` creates the APNs key (paid Apple Developer account). EAS sets `aps-environment`
     for development / production builds.
  4. Rebuild (`npx eas-cli@latest build -p android --profile preview` / `-p ios`), sign in on a phone, place an order or use
     Notification settings, then send a test with https://expo.dev/notifications or an order status change in the admin.

- **Notification settings** (`src/app/notification-settings.tsx`, Account → Notification settings and the gear on the Notifications
  screen): `GET /me/notifications`, `PATCH /me/notifications { marketing: { sms|email|push|inapp } }` (optimistic, rolls back on error).
  Channels whose feature switch is off are hidden; the whole "offers" block hides when `marketing_messages` is off. Order / payment /
  delivery messages are shown as "Always on". Demo mode keeps the choices on the device.

## Mobile-only strings (`src/i18n/mobile.ts`)

`en.ts`, `bn.ts` and `core.ts` are generated from the web dictionaries (`pnpm i18n`) and must not be edited here. Strings only the app
needs live in `src/i18n/mobile.ts` (`mxEn` + `mxBn`, keys start with `mx.`). `src/i18n/index.tsx` wraps the translator so the same
`t()` reads both: `mx.*` keys from the extension, everything else from the shared dictionaries (same `{name}` placeholders and
`one|other` plurals, Bangla digits). `MsgKey`, `TFn`, `Translator`, `makeT` and `hasKey` exported from `@/i18n` include the extension;
the ones in `@/i18n/core` don't. To share a string with the web later, add it to `apps/web/src/i18n/{en,bn}.ts`, run `pnpm i18n` and
delete it from `mobile.ts`.

## Money, group buy, bundles, unboxing, invite (added to match the website)

- **Checkout money** (`src/components/checkout/Money.tsx`): payment methods follow the `pay.bkash|pay.nagad|pay.card|pay.advance50`
  switches; 50% advance asks which gateway takes the advance (`paymentVia`). **Card EMI** (`pay.emi`, off by default) loads `GET /emi`,
  shows tenure + bank chips with monthly / bank charge / total and sends `payment: "card_emi", emiTenure, emiBank`. The review step asks the
  server for the quote (`POST /checkout/quote` with `useWallet`, EMI, `unboxing`, coupon, points) and shows Plus, bundle, unboxing and EMI
  lines and the server total. **Store-credit wallet** (`wallet` feature, shown when the balance > 0) pays part or all of an order
  (`useWallet: true`); "Pay now" is the gateway amount, and a fully covered order skips the gateway. A `409 price_changed` reprices the
  cart and asks the shopper to confirm again. **Paid unboxing** (`unboxing`) is a toggle on the delivery step (`unboxing: true`).
- **Order page:** "Pay now" for a pending / failed online payment (`POST /orders/{id}/pay`, same return deep link as checkout) and an
  unboxing card that opens `src/app/unboxing/[id].tsx` (hub photos, video, approve / report a problem).
- **Group buy** (`group_orders`): `src/app/group/index.tsx` (pick store, 24/48/72 h, items from the cart, open a group by code, your
  groups) and `src/app/group/[code].tsx` (countdown, each-saves / shipping / members, members list, share link via the share sheet,
  join / update / leave, organiser close / cancel; refreshes every 20 s while focused). Entry points: "Buy with friends" on the product
  screen, the group invite in the cart, Account → Group buys, and `skydropretail://group/CODE`.
- **Bundles** (`bundles`): "Frequently bought together" checklist with a live total and "Add all N to cart", curated bundle cards on the
  product screen, `src/app/bundles/index.tsx` and `src/app/bundles/[slug].tsx` ("Add bundle" adds every item; the saving shows in the
  checkout quote).
- **Invite friends** (`referral`): `src/app/invite.tsx` (`GET /referrals/me`, code, share sheet, stats, friends).
- **Help and policies:** Account lists How it works, Help, Contact, Shipping, Refunds, Terms and Privacy; they open the storefront
  pages (`EXPO_PUBLIC_WEB_URL`) in an in-app browser tab.

## QA in a browser (react-native-web)

`react-native-web` is already a dependency, so the app runs at phone size in a browser for flow testing:
`EXPO_PUBLIC_API_URL=http://localhost:<proxy> npx expo start --web`. The API's CORS only allows `WEB_URL` and doesn't accept the
`X-App-*` headers, so put the web bundle and `/api/v1` behind one local origin (a small proxy that adds `X-Internal-Key` for the catalog
guard in development). Push, haptics, secure storage and the native share sheet are native-only and are stubbed on web.
