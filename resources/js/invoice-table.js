export function invoiceTable(config) {
    return {
        endpoint: config.endpoint,
        ajax: config.ajax !== false,
        leaseId: config.leaseId || null,
        scope: config.leaseId ? 'lease' : 'all',
        invoicePage: config.initialPage || 1,
        invoices: config.initialRows || [], pagination: config.pagination || { last_page: 1, total: 0 },
        loading: false, invoiceError: '', requestSequence: 0,
        openPayment: false, shakePayment: false, paymentData: {},
        openReceipt: false, receiptImage: '', receiptUser: '', receiptDate: '', isPdf: false,
        get paginatedInvoices() { return this.invoices; },
        get totalInvoicePages() { return this.pagination.last_page || 1; },
        get paginationLinks() {
            const total = this.totalInvoicePages;
            if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
            if (this.invoicePage <= 4) return [1, 2, 3, 4, 5, '...', total];
            if (this.invoicePage >= total - 3) return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
            return [1, '...', this.invoicePage - 1, this.invoicePage, this.invoicePage + 1, '...', total];
        },
        init() {
            if (!this.ajax) return;
            this.$watch('invoicePage', () => this.refreshTable());
            this.refreshTable();
        },
        setEndpoint(url, leaseId = null) {
            if (url === this.endpoint) return;
            this.endpoint = url;
            this.leaseId = leaseId;
            this.invoices = [];
            this.pagination = { last_page: 1, total: 0 };
            if (this.invoicePage !== 1) this.invoicePage = 1;
            else this.refreshTable();
        },
        async refreshTable() {
            if (!this.ajax) { window.location.reload(); return; }
            const sequence = ++this.requestSequence;
            const endpoint = this.endpoint;
            this.loading = true;
            this.invoiceError = '';
            try {
                const separator = endpoint.includes('?') ? '&' : '?';
                const response = await fetch(`${endpoint}${separator}page=${this.invoicePage}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('Unable to refresh invoices');
                const data = await response.json();
                if (sequence !== this.requestSequence || endpoint !== this.endpoint) return;
                if (this.invoicePage > data.pagination.last_page) {
                    this.invoicePage = data.pagination.last_page;
                    return;
                }
                this.invoices = data.invoices;
                this.pagination = data.pagination;
                this.$dispatch('invoice-table-updated', data);
            } catch (error) {
                if (sequence === this.requestSequence) this.invoiceError = 'Unable to refresh invoices. Please try again.';
            } finally {
                if (sequence === this.requestSequence) this.loading = false;
            }
        },
        recordPayment(invoice) {
            this.$dispatch('open-payment', {
                id: invoice.id, asyncRefresh: this.ajax,
                invoiceNo: invoice.invoice_no, dueDate: invoice.due_date,
                totalAmount: invoice.amount_balance, invoiceTotal: invoice.total_amount,
                previouslyPaid: invoice.amount_paid, invoiceItems: invoice.invoice_items,
                walletBalance: invoice.wallet_balance,
                actionUrl: config.paymentUrl.replace('__INVOICE__', invoice.id)
            });
        },
        paymentRecorded(event) {
            if (!this.ajax) return;
            if (this.scope === 'lease' && event.detail.lease_id !== this.leaseId) return;
            event.detail.handled = true;
            this.refreshTable();
        },
        showProof(proof) {
            this.receiptImage = proof.url;
            this.receiptUser = proof.user;
            this.receiptDate = proof.date;
            this.isPdf = proof.is_pdf;
            this.openReceipt = true;
        }
    };
}
