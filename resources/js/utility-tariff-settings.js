const copy = value => JSON.parse(JSON.stringify(value));
import { tariffCalculation, scaledDecimal, formatDecimal } from './utility-decimal.js';

// Display conversion only; all charge arithmetic is performed with integer BigInt units.
export function calculateUtilityTariff(profile, usage) {
    const result = tariffCalculation({ ...profile, minimum_policy: profile.minimum_policy ?? 'monthly' }, usage);
    return result ? result.amount / 100 : null;
}

export function utilityTariffSettings(profiles, initialId, oldValues = null) {
    return {
        profiles,
        profileId: initialId,
        fields: copy(oldValues || profiles[initialId]),
        editingTariff: !!oldValues,
        savingTariff: false,
        previewUsage: '',
        selectProfile(id) {
            if (!Object.hasOwn(this.profiles, id) || id === this.profileId || this.savingTariff) return;
            this.profileId = id;
            this.fields = copy(this.profiles[id]);
            this.editingTariff = false;
        },
        get profile() { return this.profiles[this.profileId]; },
        get estimate() { return calculateUtilityTariff(this.fields, this.previewUsage); },
        addTier() {
            if (this.fields.tiers.length >= 12) return;
            const tiers = this.fields.tiers;
            tiers[tiers.length - 1].limit = Number(tiers[tiers.length - 2]?.limit || 0) + 20;
            tiers.push({ limit: null, rate: null });
        },
        removeTier(index) {
            if (this.fields.tiers.length <= 1) return;
            this.fields.tiers.splice(index, 1);
            this.fields.tiers[this.fields.tiers.length - 1].limit = null;
        },
        setCalculation(value) {
            if (!['blocks', 'whole_usage', 'flat'].includes(value)) return;
            if (value === this.fields.calculation) return;
            this.fields.calculation = value;
            if (value === 'flat') this.fields.tiers = [{ limit: null, rate: this.fields.tiers[0]?.rate ?? null }];
        },
    };
}

export function utilityTariffManager(catalog, today, initial = null) {
    const empty = () => ({ id: '', name: '', provider: '', state: '', customer_category: '', reference_key: '',
        effective_from: today, source_url: '', verified: false, reason: '', make_default: false,
        definition: { calculation: 'blocks', minimum_policy: 'none', minimum_charge: '0', fixed_amount: null,
            tiers: [{ limit: null, rate: '' }], capacity_rate: '0', network_rate: '0', fixed_charge: '0', fixed_waiver_up_to: null } });
    return {
        catalog, fields: initial || empty(), editing: !!initial, officialMode: !!initial?.officialMode,
        assignmentTariff: '', assignmentServices: [], assignmentProperties: [],
        previewId: Object.keys(catalog).find(id => catalog[id].is_default) || Object.keys(catalog).find(id => catalog[id].kind === 'custom') || Object.keys(catalog)[0] || '',
        previous: '', current: '', billAmount: '',
        editTariff(id, official = false) {
            const source = this.catalog[id];
            this.fields = { ...empty(), id, name: source.label, provider: source.provider || '', state: source.state || '',
                customer_category: source.customer_category || '', reference_key: source.profile_id || '', source_url: source.source || '', make_default: !!source.is_default,
                definition: copy({ ...empty().definition, ...source }) };
            this.officialMode = official; this.editing = true;
        },
        newTariff(official = false) { this.fields = empty(); this.officialMode = official; this.editing = true; },
        copyReference(id) {
            this.editTariff(id);
            this.fields.id = '';
            this.fields.name = 'My water rates — ' + this.catalog[id].label;
            this.fields.source_url = '';
            this.fields.make_default = true;
        },
        addTier() {
            const tiers = this.fields.definition.tiers;
            if (tiers.length >= 12) return;
            const prior = tiers.length > 1 ? scaledDecimal(tiers[tiers.length - 2].limit, 3) : 0n;
            tiers[tiers.length - 1].limit = formatDecimal((prior ?? 0n) + 20000n, 3);
            tiers.push({ limit: null, rate: '' });
        },
        removeTier(index) {
            const tiers = this.fields.definition.tiers;
            if (tiers.length > 1) tiers.splice(index, 1);
            tiers[tiers.length - 1].limit = null;
        },
        setMethod(value) {
            this.fields.definition.calculation = value;
            if (value === 'flat') this.fields.definition.tiers = [{ limit: null, rate: this.fields.definition.tiers[0]?.rate ?? '' }];
            if (['fixed', 'actual_bill'].includes(value)) this.fields.definition.tiers = [];
            else if (!this.fields.definition.tiers.length) this.fields.definition.tiers = [{ limit: null, rate: '' }];
        },
        get previewProfile() { return this.editing ? { ...this.fields.definition, kind: this.officialMode ? 'official' : 'custom', label: this.fields.name, billable: false } : this.catalog[this.previewId]; },
        get usage() {
            const previous = scaledDecimal(this.previous, 3), current = scaledDecimal(this.current, 3);
            return previous === null || current === null || current < previous ? null : formatDecimal(current - previous, 3);
        },
        get estimate() { return this.previewProfile ? tariffCalculation(this.previewProfile, this.usage, this.billAmount) : null; },
        get deviation() {
            const reference = Object.values(this.catalog).find(p => p.kind === 'official' && p.profile_id === this.fields.reference_key);
            if (!reference || reference.verification_status !== 'verified') return 'No verified official tariff is available for comparison. These are contractual landlord charges.';
            const canonical = (value, places) => scaledDecimal(value, places)?.toString() ?? 'unknown';
            const normalize = p => JSON.stringify([p.calculation, p.minimum_policy ?? 'monthly', canonical(p.minimum_charge, 6),
                (p.tiers || []).map(t => [t.limit == null ? null : canonical(t.limit, 3), canonical(t.rate, 6)])]);
            return normalize(reference) !== normalize(this.fields.definition) ? 'These custom rates differ from the official reference tariff. Confirm the tenancy billing policy.' : 'These are landlord-configured charges, even when the numbers match the reference tariff.';
        },
        money(cents) { return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(cents / 100); },
    };
}
