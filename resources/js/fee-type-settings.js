const copy = value => JSON.parse(JSON.stringify(value));
const cents = value => value === '' || value === null ? null : Math.round(Number(value) * 100);
const amount = value => value === null || value === undefined ? '' : (Number(value) / 100).toFixed(2);

export function feeTypeSettings(config) {
    return {
        rows: copy(config.rows), originals: copy(config.rows),
        fee: config.fee, feeCategories: [config.fee.category],
        editingFees: config.editing, savingFees: false, savingFee: false, deletingFee: false, deleting: null,
        get changes() {
            return Object.entries(this.rows).flatMap(([id, row]) => {
                if (row.deleted) return [];
                const original = this.originals[id];
                const entry = { id };
                if (row.editable) {
                    if (row.name !== original.name) entry.name = row.name;
                    if (row.category === 'item') {
                        for (const field of ['original_price', 'sell_price']) {
                            if (cents(row[field]) !== cents(original[field])) entry[field] = cents(row[field]);
                        }
                    }
                }
                if (row.can_toggle && row.is_active !== original.is_active) entry.is_active = row.is_active;
                return Object.keys(entry).length > 1 ? [entry] : [];
            });
        },
        resetFees() { if (!this.savingFees && !this.deletingFee) this.rows = copy(this.originals); },
        resetLoading() { this.savingFees = false; this.savingFee = false; this.deletingFee = false; },
        openEditor() {
            const category = config.defaultCategory ?? 'invoice';
            this.fee = { id: null, name: '', category, original_price: '', sell_price: '', used: false };
            this.feeCategories = [category];
            this.$dispatch('open-fee-type-editor');
        },
        openDelete(id) {
            const row = this.rows[id];
            if (!this.editingFees || this.savingFees || this.deletingFee || row.used || !row.delete_url) return;
            this.deleting = { id, name: this.originals[id].name, url: row.delete_url };
            this.$dispatch('open-delete-fee-type');
        },
        reportError(error, fallback) {
            const data = error.response?.data;
            this.$dispatch('session-status', { type: 'error', message: data?.message || fallback });
        },
        async saveFees() {
            const fees = this.changes;
            if (!this.editingFees || !fees.length || this.savingFees || this.deletingFee) return;
            this.savingFees = true;
            try {
                const { data } = await window.axios.put(config.saveUrl, { fees }, { headers: { Accept: 'application/json' } });
                if (!data.success) throw new Error('Invalid response');
                for (const fee of data.fees) {
                    Object.assign(this.rows[fee.id], { name: fee.name, original_price: amount(fee.original_price),
                        sell_price: amount(fee.sell_price), is_active: fee.effective_active });
                }
                this.originals = copy(this.rows);
                this.editingFees = false;
                this.$dispatch('session-status', data.session_status);
            } catch (error) {
                this.reportError(error, config.saveError);
            } finally {
                this.savingFees = false;
            }
        },
        async confirmDeleteFee() {
            if (!this.deleting?.url || this.deletingFee || this.savingFees) return;
            this.deletingFee = true;
            try {
                const target = this.deleting;
                const { data } = await window.axios.delete(target.url, { headers: { Accept: 'application/json' } });
                if (!data.success) throw new Error('Invalid response');
                this.rows[target.id].deleted = true;
                this.originals[target.id].deleted = true;
                this.$dispatch('close-delete-fee-type');
                this.deleting = null;
                this.$dispatch('session-status', data.session_status);
            } catch (error) {
                this.reportError(error, config.deleteError);
            } finally {
                this.deletingFee = false;
            }
        },
    };
}
