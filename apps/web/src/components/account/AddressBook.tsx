"use client";

import { useCallback, useState } from "react";
import type { Address } from "@/lib/types";
import { useStore } from "@/state/store";
import { useToast } from "@/components/Toast";
import { Icon } from "@/components/Icon";
import { ConfirmDialog, EmptyState, LoadingBlock, PageHead, Sheet, UndoToast, type UndoToastState } from "./ui";
import { SignedOut } from "./SignedOut";
import { AddressForm } from "./AddressForm";
import { prettyPhone } from "./phone";
import { useT } from "@/i18n/client";
import { bdName } from "@/lib/bd";

export function AddressBook() {
  const { ready, user, addresses, saveAddress, removeAddress } = useStore();
  const toast = useToast();
  const { t, lang } = useT();
  const [editing, setEditing] = useState<Address | "new" | null>(null);
  const [deleting, setDeleting] = useState<Address | null>(null);
  const [undo, setUndo] = useState<UndoToastState>(null);
  const clearUndo = useCallback(() => setUndo(null), []);

  if (!ready)
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
        <LoadingBlock rows={2} />
      </div>
    );
  if (!user)
    return (
      <div className="mx-auto max-w-3xl px-4 pb-24 pt-10 md:pb-12">
        <SignedOut next="/account/addresses" title={t("ab.signIn")} body={t("ab.signInB")} />
      </div>
    );

  // Default first, then newest.
  const list = [...addresses].sort((a, b) => Number(!!b.isDefault) - Number(!!a.isDefault));

  function makeDefault(a: Address) {
    saveAddress({ ...a, isDefault: true });
    toast({ kind: "success", title: t("ab.defaultUpdated"), body: `${a.area}, ${bdName(a.district, lang)}` });
  }

  function doDelete(a: Address) {
    const wasDefault = !!a.isDefault;
    const others = addresses.filter((x) => x.id !== a.id);
    removeAddress(a.id);
    // Keep exactly one default: promote the next address if we removed the default.
    if (wasDefault && others.length) saveAddress({ ...others[0], isDefault: true });
    setUndo({
      id: Date.now(),
      title: t("ab.deleted"),
      body: `${a.name}, ${a.area}`,
      undo: () => saveAddress({ ...a }),
    });
  }

  return (
    <div className="mx-auto max-w-3xl px-4 pb-24 pt-6 md:pb-12">
      <PageHead title={t("acc.addresses")} sub={t("ab.sub")} icon="pin" back={{ href: "/account", label: t("acc.account") }} />

      {list.length === 0 ? (
        <EmptyState
          title={t("ab.none")}
          body={t("ab.noneB")}
          action={{ label: t("ab.add"), onClick: () => setEditing("new") }}
        />
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2" aria-live="polite">
          {list.map((a, i) => (
            <li key={a.id} className={"card rise relative flex flex-col p-4 " + (a.isDefault ? "is-active" : "")} style={{ ["--i" as string]: i, ...(a.isDefault ? { boxShadow: "var(--shadow-1), 0 0 0 2px var(--accent-soft), var(--glow)" } : {}) }}>
              <div className="flex items-start gap-3">
                <span className="chip-icon shrink-0">
                  <Icon name={a.isDefault ? "home" : "pin"} size={18} />
                </span>
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="font-bold">{a.name}</p>
                    {a.isDefault && (
                      <span className="ac-chip">
                        <Icon name="check" size={12} stroke={2.6} /> {t("addr.default")}
                      </span>
                    )}
                  </div>
                  <p className="text-sm tabular-nums text-ink-2">{prettyPhone(a.phone)}</p>
                  <p className="mt-1 text-sm">{a.line}</p>
                  <p className="text-sm text-ink-2">
                    {a.area}, {bdName(a.district, lang)}, {bdName(a.division, lang)}
                  </p>
                </div>
              </div>
              <div className="mt-3 flex flex-wrap gap-2 pt-1">
                <button type="button" className="btn btn-ghost h-11 px-4 text-sm" onClick={() => setEditing(a)}>
                  <Icon name="settings" size={16} /> {t("ab.edit")}
                </button>
                {!a.isDefault && (
                  <button type="button" className="btn btn-ghost h-11 px-4 text-sm" onClick={() => makeDefault(a)}>
                    <Icon name="star" size={16} /> {t("ab.setDefault")}
                  </button>
                )}
                <button
                  type="button"
                  className="btn ml-auto h-11 w-11 px-0 text-bad hover:bg-surface-2"
                  aria-label={t("ab.deleteFor", { name: a.name, area: a.area })}
                  onClick={() => setDeleting(a)}
                >
                  <Icon name="trash" size={18} />
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}

      <button
        id="add"
        type="button"
        onClick={() => setEditing("new")}
        className="group mt-4 flex min-h-16 w-full items-center gap-3 rounded-[12px] p-4 text-left transition hover:-translate-y-0.5"
        style={{ background: "color-mix(in srgb, var(--accent) 6%, var(--surface))", boxShadow: "inset 0 0 0 1.5px var(--accent-soft)" }}
      >
        <span className="chip-icon transition group-hover:bg-accent group-hover:text-white">
          <Icon name="plus" size={18} />
        </span>
        <span className="flex-1">
          <span className="block font-semibold text-accent">{t("addr.addNew")}</span>
          <span className="block text-xs text-ink-2">{t("ab.addSub")}</span>
        </span>
        <Icon name="right" size={18} className="ac-chev" />
      </button>

      {editing && (
        <Sheet
          title={editing === "new" ? t("ab.new") : t("ab.editTitle")}
          description={t("ab.riders")}
          icon="pin"
          onClose={() => setEditing(null)}
        >
          {(close) => (
            <AddressForm
              initial={editing === "new" ? undefined : editing}
              defaultName={addresses.length ? undefined : user.name}
              defaultPhone={addresses.length ? undefined : user.phone}
              canUnsetDefault={editing === "new" ? addresses.length > 0 : !editing.isDefault}
              onCancel={close}
              onSave={(a) => {
                saveAddress(a);
                toast({ kind: "success", title: editing === "new" ? t("ab.saved") : t("ab.updated"), body: `${a.area}, ${bdName(a.district, lang)}` });
                setTimeout(close, 500);
              }}
            />
          )}
        </Sheet>
      )}

      {deleting && (
        <ConfirmDialog
          title={t("ab.deleteQ")}
          body={t("ab.deleteB", { address: `${deleting.name}, ${deleting.line}, ${deleting.area}` })}
          confirmLabel={t("ab.delete")}
          icon="trash"
          danger
          onClose={() => setDeleting(null)}
          onConfirm={() => doDelete(deleting)}
        />
      )}

      <UndoToast toast={undo} onDone={clearUndo} />
    </div>
  );
}
