"use client";

import { ErrorState } from "@/components/resilience/ErrorState";
import { useT } from "@/i18n/client";

export default function SearchError({ error, retry }: { error: unknown; retry: () => void }) {
  const { t } = useT();
  const digest = error instanceof Error ? (error as Error & { digest?: string }).digest : undefined;
  return <ErrorState title={t("se.title")} body={t("se.body")} retry={retry} digest={digest} />;
}
