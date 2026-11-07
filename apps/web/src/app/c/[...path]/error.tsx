"use client";

import { ErrorState } from "@/components/resilience/ErrorState";
import { useT } from "@/i18n/client";

export default function CategoryError({ error, retry }: { error: unknown; retry: () => void }) {
  const { t } = useT();
  const digest = error instanceof Error ? (error as Error & { digest?: string }).digest : undefined;
  return <ErrorState title={t("c.err.title")} body={t("c.err.body")} retry={retry} digest={digest} />;
}
