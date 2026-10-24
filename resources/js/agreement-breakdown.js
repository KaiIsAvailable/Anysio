// Ephemeral presentation state only. The original rendered template is never mutated.
export function moneyCents(value) {
    const text = String(value ?? '').trim();
    if (!/^\d+(?:\.\d{1,2})?$/.test(text)) return null;
    const [whole, fraction = ''] = text.split('.');
    const cents = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    return Number.isSafeInteger(cents) ? cents : null;
}

export function rentalCell(root) {
    const matches = [];
    for (const row of root.querySelectorAll('tr')) {
        const cells = [...row.children];
        const label = cells.findIndex(cell => /^Rental per (?:Day|Month|Year)(?:\s*Ringgit Malaysia)?$/i.test(
            cell.textContent.replace(/[\u200B-\u200D\uFEFF]/g, '').replace(/\s+/g, ' ').trim()
        ));
        if (label >= 0 && cells[label + 1]) matches.push(cells[label + 1]);
    }
    return matches.length === 1 ? matches[0] : null;
}

export function formatRentalCents(cents) {
    const whole = Math.floor(cents / 100);
    const fraction = cents % 100;
    return String(whole) + (fraction ? `.${String(fraction).padStart(2, '0')}` : '');
}

// Keep the existing amount's ancestor elements, including font, weight and alignment.
export function setRentalLines(cell, lines) {
    const walker = document.createTreeWalker(cell, NodeFilter.SHOW_TEXT);
    let node;
    while ((node = walker.nextNode())) {
        if (/\d/.test(node.textContent)) break;
    }
    if (!node) return false;
    let inner = document.createDocumentFragment();
    lines.forEach((line, index) => {
        if (index) inner.append(document.createElement('br'));
        inner.append(document.createTextNode(line));
    });
    for (let parent = node.parentElement; parent !== cell; parent = parent.parentElement) {
        const wrapper = parent.cloneNode(false);
        wrapper.append(inner);
        inner = wrapper;
    }
    cell.replaceChildren(inner);
    return true;
}

export function agreementBreakdown() {
    return {
        originalAgreement: '', rentalTotal: null, rentalFrequency: null, rentalBasisError: '', breakdownAllowed: false,
        formatRentalCents,
        appliedBreakdown: null, basicLease: '', serviceCharge: '', breakdownError: '',
        resetBreakdown(detail = {}) {
            this.$dispatch('close-rental-breakdown');
            this.originalAgreement = detail.content || '';
            const basis = detail.rentalBasis;
            const valid = Number.isSafeInteger(basis?.cents) && basis.cents >= 0
                && ['daily', 'monthly', 'yearly'].includes(basis?.frequency) && !basis.error;
            this.rentalTotal = valid ? basis.cents : null;
            this.rentalFrequency = valid ? basis.frequency : null;
            this.rentalBasisError = valid ? '' : (basis?.error || 'Rental amount or frequency is missing. No unique rental amount can be determined.');
            this.breakdownAllowed = detail.allowBreakdown === true;
            this.appliedBreakdown = null;
            this.basicLease = ''; this.serviceCharge = ''; this.breakdownError = '';
        },
        editBreakdown() {
            this.basicLease = this.appliedBreakdown?.basic ?? '';
            this.serviceCharge = this.appliedBreakdown?.service ?? '';
            this.breakdownError = '';
            this.$dispatch('open-rental-breakdown');
        },
        applyBreakdown() {
            if (this.rentalBasisError) {
                this.breakdownError = this.rentalBasisError;
                return;
            }
            const basic = moneyCents(this.basicLease), service = moneyCents(this.serviceCharge);
            if (this.rentalTotal === null || basic === null || service === null || basic + service !== this.rentalTotal) {
                this.breakdownError = `Enter non-negative amounts with at most two decimals. Basic Lease + Service Charge must equal the original ${this.rentalFrequency} rental.`;
                return;
            }
            const copy = document.createElement('template');
            copy.innerHTML = this.originalAgreement;
            const cell = rentalCell(copy.content);
            if (!cell) {
                this.breakdownError = 'This template does not have a unique Rental per Day/Month/Year row. The agreement has not been changed.';
                return;
            }
            if (!setRentalLines(cell, [formatRentalCents(this.rentalTotal),
                `BASIC LEASE (${formatRentalCents(basic)})`, `SERVICE CHARGE (${formatRentalCents(service)})`])) {
                this.breakdownError = 'The rental display could not be identified. The agreement has not been changed.';
                return;
            }
            document.getElementById('invoice-modal-content').innerHTML = copy.innerHTML;
            this.appliedBreakdown = { basic: String(this.basicLease).trim(), service: String(this.serviceCharge).trim() };
            this.$dispatch('close-rental-breakdown');
        },
        removeBreakdown() {
            document.getElementById('invoice-modal-content').innerHTML = this.originalAgreement;
            this.appliedBreakdown = null;
            this.$dispatch('close-rental-breakdown');
        },
    };
}
