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

- **Pricing.** `src/lib/pricing.ts` is a verbatim copy of `apps/web/src/lib/pricing.ts`; keep them in sync. When the API returns `priceBdt`/`mrpBdt`, the app shows the server figures.
- **Video.** Product videos use `expo-video`: AVPlayer on iOS, ExoPlayer on Android. They play HLS natively, with `contentType: "hls"` set for iOS.
- **Platform details.**
  - iOS: native stack with full-screen swipe-back, `oneTimeCode` SMS autofill on the OTP field, keyboard avoidance on login, checkout, addresses and profile.
  - Android: `softwareKeyboardLayoutMode: resize` and SMS OTP autofill hints.
  - Both: haptics via `expo-haptics`.
- **Icon.** Original artwork, generated from `assets/icon-src/*.svg` with `rsvg-convert`. The adaptive icon has separate foreground, background and monochrome layers.

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
