import Link from "next/link";
import { Icon } from "@/components/Icon";

export default function NotFound() {
  return (
    <div className="mx-auto max-w-md px-4 py-20 text-center">
      <div className="parachute mx-auto w-fit text-accent">
        <svg width="96" height="96" viewBox="0 0 96 96" aria-hidden="true">
          <path d="M14 40a34 26 0 0 1 68 0z" fill="currentColor" opacity=".2" />
          <path d="M14 40a34 26 0 0 1 68 0" fill="none" stroke="currentColor" strokeWidth="3" />
          <path d="M16 40l24 24M80 40L56 64M48 40v24" stroke="currentColor" strokeWidth="2" />
          <rect x="36" y="62" width="24" height="20" rx="4" fill="var(--brand-2)" />
        </svg>
      </div>
      <h1 className="mt-4 text-2xl font-extrabold">This page drifted off</h1>
      <p className="mt-2 text-sm text-ink-2">The link may be old, or the product is no longer sold in India.</p>
      <Link href="/" className="btn btn-primary mt-6">
        Back to shopping <Icon name="arrow" size={18} className="arrow" />
      </Link>
    </div>
  );
}
