export function invoiceStatement(endpoint) {
    // Each opening starts at page one without remembered statement filters.
    const unfilteredUrl = () => {
        const url = new URL(endpoint, window.location.origin);
        for (const key of [...url.searchParams.keys()]) {
            if (key.startsWith('statement_')) url.searchParams.delete(key);
        }
        return url.href;
    };
    return {
        statementLoading: false, error: '', lastUrl: endpoint, request: null, opener: null,
        filtersReady: false, recordKind: '',
        openStatement() {
            this.opener = document.activeElement;
            this.recordKind = '';
            this.$refs.content.innerHTML = '';
            this.load(unfilteredUrl());
            const openingRequest = this.request;
            this.$nextTick(() => {
                if (this.request === openingRequest) this.$refs.title?.focus();
            });
        },
        closeStatement() {
            this.request?.abort();
            this.request = null;
            this.statementLoading = false;
            this.filtersReady = false;
            this.$refs.content.innerHTML = '';
            this.opener?.focus();
        },
        async load(url) {
            if (this.statementLoading && this.lastUrl === url) return;
            this.request?.abort();
            const request = new AbortController();
            this.request = request;
            this.lastUrl = url;
            this.statementLoading = true;
            this.error = '';
            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin', signal: request.signal,
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(response.status === 422
                        ? Object.values(data.errors || {}).flat().join(' ') || 'Please check the filters.'
                        : 'Unable to load statement. Please try again.');
                }
                if (typeof data.html !== 'string') throw new Error('Unable to load statement. Please try again.');
                if (this.request === request) {
                    this.$refs.content.innerHTML = data.html;
                    // Mount once per opening. The shared form owns Enter; autofocus is disabled.
                    // Later responses replace only results, preserving the filter controls.
                    this.filtersReady = true;
                }
            } catch (error) {
                if (this.request === request && error.name !== 'AbortError') {
                    this.error = error.message || 'Unable to load statement. Please try again.';
                }
            } finally {
                if (this.request === request) this.statementLoading = false;
            }
        },
        submit(event) {
            if (!(event.target instanceof HTMLFormElement)) return;
            const url = new URL(endpoint, window.location.origin);
            const fields = new FormData(event.target);
            const params = new URLSearchParams();
            for (const [key, value] of fields) {
                if (['statement_from', 'statement_to', 'statement_kind', 'statement_invoice'].includes(key)) params.set(key, value);
            }
            url.search = params.toString();
            this.load(url.href);
        },
        clearFilters(form) {
            form?.reset();
            form?.querySelectorAll('input').forEach(input => input._flatpickr?.clear());
            this.recordKind = '';
            this.load(endpoint);
        },
        navigate(event) {
            const row = event.target.closest('[data-statement-event]');
            if (row && this.$refs.content.contains(row)) {
                const arrow = event.target.closest('[data-statement-toggle]');
                // Preserve text selection, native links and other controls. Keyboard activation
                // of the arrow remains available even if a selection exists elsewhere.
                if (event.detail !== 0 && window.getSelection()?.toString()) return;
                if (!arrow && event.target.closest('a, button, input, select, textarea, label, summary, [role="button"], [role="link"], [contenteditable]:not([contenteditable="false"])')) return;
                const toggle = arrow || row.querySelector('[data-statement-toggle]');
                const details = toggle && document.getElementById(toggle.getAttribute('aria-controls'));
                if (details && this.$refs.content.contains(details)) {
                    details.hidden = !details.hidden;
                    toggle.setAttribute('aria-expanded', String(!details.hidden));
                }
                return;
            }
            const link = event.target.closest('a');
            if (!link || !this.$refs.content.contains(link) || !link.closest('[data-statement-pagination]')) return;
            event.preventDefault();
            const url = new URL(link.href);
            const base = new URL(endpoint, window.location.origin);
            if (url.origin === base.origin && url.pathname === base.pathname) this.load(url.href);
        },
    };
}
