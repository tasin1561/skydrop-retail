"use client";

import { ErrorState } from "@/components/resilience/ErrorState";

export default function ProductError({ error, retry }: { error: unknown; retry: () => void }) {
  const digest = error instanceof Error ? (error as Error & { digest?: string }).digest : undefined;
  return <ErrorState title="This product didn't load" body="We're reconnecting to the store. It usually takes a few seconds." retry={retry} digest={digest} />;
}
