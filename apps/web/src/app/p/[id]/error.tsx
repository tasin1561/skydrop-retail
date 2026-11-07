"use client";

import { useT } from "@/i18n/client";
import { ErrorState } from "@/components/resilience/ErrorState";

export default function ProductError({ error, retry }: { error: unknown; retry: () => void }) {
  const { t } = useT();
  const digest = error instanceof Error ? (error as Error & { digest?: string }).digest : undefined;
  return <ErrorState title={t("pd.err.title")} body={t("pd.err.body")} retry={retry} digest={digest} />;
}
