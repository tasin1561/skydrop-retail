This is a [Next.js](https://nextjs.org) project bootstrapped with [`create-next-app`](https://nextjs.org/docs/app/api-reference/cli/create-next-app).

## Getting Started

First, run the development server:

```bash
npm run dev
# or
yarn dev
# or
pnpm dev
# or
bun dev
```

Open [http://localhost:3000](http://localhost:3000) with your browser to see the result.

You can start editing the page by modifying `app/page.tsx`. The page auto-updates as you edit the file.

This project uses [`next/font`](https://nextjs.org/docs/app/building-your-application/optimizing/fonts) to automatically optimize and load [Geist](https://vercel.com/font), a new font family for Vercel.

## Learn More

To learn more about Next.js, take a look at the following resources:

- [Next.js Documentation](https://nextjs.org/docs) - learn about Next.js features and API.
- [Learn Next.js](https://nextjs.org/learn) - an interactive Next.js tutorial.

You can check out [the Next.js GitHub repository](https://github.com/vercel/next.js) - your feedback and contributions are welcome!

## Deploy on Vercel

The easiest way to deploy your Next.js app is to use the [Vercel Platform](https://vercel.com/new?utm_medium=default-template&filter=next.js&utm_source=create-next-app&utm_campaign=create-next-app-readme) from the creators of Next.js.

Check out our [Next.js deployment documentation](https://nextjs.org/docs/app/building-your-application/deploying) for more details.

## Notifications

- **Bell.** `src/components/notifications/NotificationBell.tsx` sits in the Header for signed-in shoppers (hidden when the
  `in_app_notifications` feature is off). It shows an animated unread badge, a dropdown panel on desktop and `/notifications` on mobile.
- **Behaviour.** Items are grouped Today / Earlier with an icon chip per type. On touch you swipe left to mark read, or further to delete. There is a
  "Mark all read" button and an empty state.
- **Data.** State is in `useNotifications.ts`. It polls `GET /notifications` every 60 s while the tab is visible, pauses when hidden and refetches on
  focus. Demo mode (no `NEXT_PUBLIC_API_URL`) builds sample items from the local orders.
- **Cart sync.** `src/state/store.tsx` sends `PUT /cart` (debounced 1.5 s) when the API is on and the shopper is signed in. The API uses it for
  abandoned-cart reminders.
- **Web Push (TODO).** `api.pushSubscribe()` and `POST /push/subscribe` store a browser subscription, but delivery needs `minishlink/web-push` on the
  API. A service worker plus `VAPID_PUBLIC_KEY` are also still to be added.
