import { Icon } from "../Icon";
import { getT } from "@/i18n/server";
import type { MsgKey } from "@/i18n/core";

const STEPS: { icon: string; title: MsgKey; text: MsgKey }[] = [
  { icon: "search", title: "how.s1.title", text: "how.s1.text" },
  { icon: "shield", title: "how.s2.title", text: "how.s2.text" },
  { icon: "package", title: "how.s3.title", text: "how.s3.text" },
  { icon: "truck", title: "how.s4.title", text: "how.s4.text" },
];

export async function HowItWorks({ compact }: { compact?: boolean }) {
  const { t, digits } = await getT();
  return (
    <section className={compact ? "" : "mt-10"} aria-labelledby="how">
      {!compact && (
        <div className="mb-4 px-4 sm:px-0">
          <h2 id="how" className="text-lg font-semibold tracking-[-0.01em] sm:text-xl">
            {t("how.title")}
          </h2>
          <p className="text-sm text-ink-2">{t("how.sub")}</p>
        </div>
      )}
      <ol className="relative grid gap-3 px-4 sm:grid-cols-2 sm:px-0 lg:grid-cols-4">
        {STEPS.map((s, i) => (
          <li key={s.title} className="card rise relative flex flex-col gap-3 p-5" style={{ ["--i" as string]: i }}>
            <div className="flex items-center justify-between">
              <span className="text-[13px] font-medium tabular-nums text-ink-3" aria-hidden>
                {digits(String(i + 1).padStart(2, "0"))}
              </span>
              <Icon name={s.icon} size={18} className="text-ink-3" />
            </div>
            <div>
              <p className="sr-only">{t("how.step", { n: digits(String(i + 1).padStart(2, "0")) })}</p>
              <h3 className="text-sm font-semibold">{t(s.title)}</h3>
              <p className="mt-1 text-[13px] leading-relaxed text-ink-2">{t(s.text)}</p>
            </div>
          </li>
        ))}
      </ol>
    </section>
  );
}
