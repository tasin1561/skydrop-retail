import Link from "next/link";
import { Icon } from "@/components/Icon";

/** Shown when a growth feature is switched off in Admin → System → Features. */
export function NotAvailable({ title }: { title: string }) {
  return (
    <div className="card rise mx-auto flex max-w-md flex-col items-center px-6 py-10 text-center">
      <span className="chip-icon h-12 w-12 rounded-2xl">
        <Icon name="info" size={22} />
      </span>
      <h2 className="mt-3 text-lg font-extrabold tracking-tight">{title} is not available right now</h2>
      <p className="mt-1 text-sm text-ink-2">Please check back later.</p>
      <Link href="/" className="btn btn-primary mt-5">
        Continue shopping <Icon name="arrow" size={18} className="arrow" />
      </Link>
    </div>
  );
}
