export function advancePaymentNotice({ storageKey, loginKey }, storage = null) {
    return {
        visible: true,
        expanded: true,
        remember: false,
        init() {
            try {
                storage ??= window.localStorage;
                const preference = storage.getItem(storageKey);
                this.remember = preference === `${loginKey}:hidden`;
                this.visible = !this.remember;
                this.expanded = this.visible && preference !== `${loginKey}:minimized` && preference !== loginKey;
            } catch {
                // The notice still works when browser storage is unavailable.
            }
        },
        savePreference(value) {
            this.remember = value;
        },
        persist(mode) {
            try {
                storage?.setItem(storageKey, `${loginKey}:${mode}`);
            } catch {
                // Keep the choice for this page if storage is blocked or full.
            }
        },
        collapse() {
            this.persist('minimized');
            this.expanded = false;
        },
        close() {
            this.expanded = false;
            if (this.remember) {
                this.persist('hidden');
                this.visible = false;
            }
        },
        expand() {
            this.expanded = true;
        },
    };
}
