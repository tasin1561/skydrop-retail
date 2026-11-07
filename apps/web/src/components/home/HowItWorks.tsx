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
          <h2 id="how" className="text-lg font-extrabold tracking-tight sm:text-xl">
            {t("how.title")}
          </h2>
          <p className="text-sm text-ink-2">{t("how.sub")}</p>
        </div>
      )}
      <ol className="relative grid gap-3 px-4 sm:grid-cols-2 sm:px-0 lg:grid-cols-4">
        {STEPS.map((s, i) => (
          <li key={s.title} className="card rise relative flex gap-3 p-4" style={{ ["--i" as string]: i }}>
            <span className="chip-icon is-active shrink-0">
              <Icon name={s.icon} size={18} />
            </span>
            <div>
              <p className="text-[11px] font-bold text-accent">{t("how.step", { n: digits(String(i + 1).padStart(2, "0")) })}</p>
              <h3 className="text-sm font-bold">{t(s.title)}</h3>
              <p className="mt-0.5 text-xs leading-relaxed text-ink-2">{t(s.text)}</p>
            </div>
          </li>
        ))}
      </ol>
    </section>
  );
}
