// Calm, corporate motion tokens for the app (mirrors the web: one curve, three durations).
// Motion only explains a change: something opened, was added, moved or succeeded. No decorative
// loops, no bounce. Every animation honours the system Reduce Motion setting.
import { Easing, FadeIn, FadeOut, ReduceMotion } from "react-native-reanimated";

/** The single easing curve: cubic-bezier(0.2, 0, 0, 1). */
export const EASE = Easing.bezier(0.2, 0, 0, 1);

/** 120 press / small state, 180 fades and menus, 240 open-close (sheets, tabs, steppers). */
export const DUR = { fast: 120, base: 180, open: 240 } as const;

/** withTiming config: the one curve, a token duration, and Reduce Motion respected. */
export const timing = (duration: number = DUR.base) => ({ duration, easing: EASE, reduceMotion: ReduceMotion.System });

/** A quiet fade for content that appears after an action. */
export const enterFade = FadeIn.duration(DUR.base).easing(EASE).reduceMotion(ReduceMotion.System);
export const exitFade = FadeOut.duration(DUR.fast).easing(EASE).reduceMotion(ReduceMotion.System);

/** List items: a subtle fade, stagger capped so the whole cascade stays within ~120ms. */
export const listEnter = (i: number) =>
  FadeIn.duration(DUR.base)
    .delay(Math.min(Math.max(i, 0), 4) * 30)
    .easing(EASE)
    .reduceMotion(ReduceMotion.System);
