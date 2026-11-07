"use client";

import { ErrorState } from "@/components/resilience/ErrorState";

export default function SearchError({ error, retry }: { error: unknown; retry: () => void }) {
  const digest = error instanceof Error ? (error as Error & { digest?: string }).digest : undefined;
  return <ErrorState title="Search hit a bump" body="We're reconnecting to the stores. Your search will be right back." retry={retry} digest={digest} />;
}
