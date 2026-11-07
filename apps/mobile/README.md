# Skydrop Retail: Android and iOS app

Expo (SDK 58) + React Native + TypeScript + expo-router. One codebase ships to Android and iOS.
Shoppers in Bangladesh buy from Indian stores. The app names the source of every item in plain text:

| Store | Source |
| --- | --- |
| Mega Store | amazon.in |
| Style Bazaar | flipkart.com |
| Daily Basket | bigbasket.com |
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
- **Theme.** `src/theme/index.tsx` mirrors the web "Midnight Luxe" tokens in `apps/web/src/app/globals.css`: light and dark palettes, violet primary (`brand`), coral signal (`coral`), and per-store accents (Mega Store violet, Style Bazaar pink, Daily Basket green).
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
- **Push notifications (needs one install).** `expo-notifications` is not in `package.json`, and Metro cannot bundle an import of a missing
  package, so `src/lib/push.ts` is written against a small interface and stays inactive until the module is provided:
  1. `npx expo install expo-notifications expo-device`
  2. In `app.json` add `"expo-notifications"` to `plugins`, and make sure the EAS project id is set (`eas init` writes `extra.eas.projectId`).
     Android needs a Firebase project: add `google-services.json` and `"android": { "googleServicesFile": "./google-services.json" }`, then upload
     the FCM V1 service-account key in Expo (Project → Credentials). iOS push keys are created by `eas credentials`.
  3. At the top of `src/app/_layout.tsx`:
     ```ts
     import * as Notifications from "expo-notifications";
     import { setNotificationsModule } from "@/lib/push";
     setNotificationsModule(Notifications);
     ```
  4. Rebuild the dev client / APK (push does not work in Expo Go on Android since SDK 53).

  After that the app asks for permission after sign-in, sends the token with `POST /api/v1/push/expo-token`, removes it on sign-out, shows
  pushes in the foreground, and opens `/order/{id}` or `/product/{id}` when a push is tapped (also from a cold start). The API sends a
  `route` in the push data; `routeFor()` also maps web paths (`/orders/{id}`, `/p/{id}`).
