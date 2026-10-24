export function invoiceStatement(endpoint) {
    return {
        statementLoading: false, error: '', lastUrl: endpoint, request: null, opener: null,
        filtersReady: false, recordKind: '',
        openStatement() {
            this.opener = document.activeElement;
            this.recordKind = '';
            this.$refs.content.innerHTML = '';
            this.load(endpoint);
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
            this.opener?.focus();
        },
        async load(url) {
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
            form.reset();
            form.querySelectorAll('input').forEach(input => input._flatpickr?.clear());
            this.recordKind = '';
            this.load(endpoint);
        },
        navigate(event) {
            const link = event.target.closest('a');
            if (!link || !this.$refs.content.contains(link)) return;
            event.preventDefault();
            const url = new URL(link.href);
            const base = new URL(endpoint, window.location.origin);
            if (url.origin === base.origin && url.pathname === base.pathname) this.load(url.href);
        },
    };
}
