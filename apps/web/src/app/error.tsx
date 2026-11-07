"use client";

import { ErrorState } from "@/components/resilience/ErrorState";

export default function AppError({ error, retry }: { error: unknown; retry: () => void }) {
  const digest = error instanceof Error ? (error as Error & { digest?: string }).digest : undefined;
  return <ErrorState retry={retry} digest={digest} />;
}
