"use client";

import { catchError, type ErrorInfo } from "next/error";
import { ErrorState } from "./ErrorState";
import { useT } from "@/i18n/client";

/**
 * Wrap optional product-page sections (reviews, price history, compare, bundles, ...) so one failing
 * section never takes the page down: it collapses to a small retry card, or to nothing (quiet).
 */
function Fallback(props: { label?: string; quiet?: boolean }, { error, retry }: ErrorInfo) {
  if (typeof console !== "undefined") console.error("[SafeSection]", props.label, error);
  if (props.quiet) return null;
  return <SectionError label={props.label} retry={retry} />;
}

function SectionError({ label, retry }: { label?: string; retry: () => void }) {
  const { t } = useT();
  return <ErrorState compact title={t("err.section", { label: label ?? t("err.thisSection") })} retry={retry} />;
}

export const SafeSection = catchError(Fallback);
