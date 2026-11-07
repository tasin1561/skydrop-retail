import Link from "next/link";
import { Icon } from "@/components/Icon";

export default function ProductNotFound() {
  return (
    <div className="mx-auto flex max-w-md flex-col items-center px-4 py-16 text-center">
      <span className="pop grid h-20 w-20 place-items-center rounded-[24px] bg-[var(--accent-soft)] text-accent shadow-[var(--glow)]">
        <Icon name="package" size={38} stroke={1.5} />
      </span>
      <h1 className="mt-5 text-2xl font-extrabold tracking-tight">We couldn&apos;t find that product</h1>
      <p className="mt-2 text-sm text-ink-2">
        The link may be old, or the item was removed from the Indian store. Try searching for it, or browse what&apos;s popular today.
      </p>
      <div className="mt-6 flex w-full flex-col gap-2.5 sm:flex-row sm:justify-center">
        <Link href="/search?q=" className="btn btn-primary">
          <Icon name="search" size={18} /> Search products
        </Link>
        <Link href="/" className="btn btn-ghost">
          <Icon name="home" size={18} /> Back to home
        </Link>
      </div>
    </div>
  );
}
