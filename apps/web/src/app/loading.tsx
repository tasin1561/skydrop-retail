"use client";

import { usePathname } from "next/navigation";
import { HomeSkeleton, PageSkeleton } from "@/components/motion/Skeletons";

// The root loading boundary covers every route without its own loading.tsx, so it only draws the
// home layout for "/" and a neutral page skeleton elsewhere. Both appear only after 150ms.
export default function Loading() {
  const path = usePathname();
  return path === "/" ? <HomeSkeleton /> : <PageSkeleton />;
}
