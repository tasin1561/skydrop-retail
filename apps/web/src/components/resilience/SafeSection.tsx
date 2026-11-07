"use client";

import { catchError, type ErrorInfo } from "next/error";
import { ErrorState } from "./ErrorState";

/**
 * Wrap optional product-page sections (reviews, price history, compare, bundles, ...) so one failing
 * section never takes the page down: it collapses to a small retry card, or to nothing (quiet).
 */
function Fallback(props: { label?: string; quiet?: boolean }, { error, retry }: ErrorInfo) {
  if (typeof console !== "undefined") console.error("[SafeSection]", props.label, error);
  if (props.quiet) return null;
  return <ErrorState compact title={`${props.label ?? "This section"} couldn't load right now.`} retry={retry} />;
}

export const SafeSection = catchError(Fallback);
