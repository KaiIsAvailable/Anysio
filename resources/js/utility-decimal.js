export function scaledDecimal(value, places, signed = false) {
    const text = String(value ?? '').trim();
    if (!new RegExp(`^${signed ? '-?' : ''}\\d+(?:\\.\\d{1,${places}})?$`).test(text)) return null;
    const negative = text.startsWith('-');
    const [whole, fraction = ''] = text.replace(/^-/, '').split('.');
    const amount = BigInt(whole) * 10n ** BigInt(places) + BigInt(fraction.padEnd(places, '0'));
    return negative ? -amount : amount;
}

export function formatDecimal(value, places) {
    const sign = value < 0n ? '-' : '';
    if (value < 0n) value = -value;
    const scale = 10n ** BigInt(places);
    return `${sign}${value / scale}.${String(value % scale).padStart(places, '0')}`;
}

export function tariffCalculation(profile, usage, billAmount = null) {
    const used = usage === null || usage === '' ? null : scaledDecimal(usage, 3);
    const method = profile.calculation;
    let total = 0n;
    const blocks = [];
    if (method === 'fixed' || method === 'actual_bill') {
        const cents = scaledDecimal(method === 'fixed' ? profile.fixed_amount : billAmount, 2);
        if (cents === null) return null;
        total = cents * 10000000n;
    } else {
        if (used === null || !['blocks', 'flat', 'whole_usage'].includes(method)) return null;
        const tiers = profile.tiers;
        if (!Array.isArray(tiers) || !tiers.length || (method === 'flat' && tiers.length !== 1)) return null;
        let lower = 0n;
        for (let index = 0; index < tiers.length; index++) {
            const tier = tiers[index], unlimited = tier.limit === null || tier.limit === '';
            const upper = unlimited ? null : scaledDecimal(tier.limit, 3), rate = scaledDecimal(tier.rate, 6);
            if (rate === null || (!unlimited && (upper === null || upper <= lower)) || (index === tiers.length - 1 ? !unlimited : unlimited)) return null;
            let quantity = method === 'blocks' ? (upper === null || used < upper ? used : upper) - lower : (upper === null || used <= upper ? used : 0n);
            if (quantity < 0n) quantity = 0n;
            const raw = quantity * rate;
            total += raw;
            if (quantity > 0n) blocks.push({ quantity: formatDecimal(quantity, 3), rate: formatDecimal(rate, 6), amount: Number((raw + 5000000n) / 10000000n) });
            if (method !== 'blocks' && (upper === null || used <= upper)) break;
            if (upper !== null) lower = upper;
        }
        for (const field of ['capacity_rate', 'network_rate']) {
            const rate = scaledDecimal(profile[field] ?? '0', 6);
            if (rate === null) return null;
            total += used * rate;
        }
        const waiver = profile.fixed_waiver_up_to === null || profile.fixed_waiver_up_to === '' || profile.fixed_waiver_up_to === undefined ? null : scaledDecimal(profile.fixed_waiver_up_to, 3);
        const fixed = scaledDecimal(profile.fixed_charge ?? '0', 6);
        if (fixed === null) return null;
        if (waiver === null || used > waiver) total += fixed * 1000n;
    }
    const minimum = scaledDecimal(profile.minimum_charge ?? '0', 6);
    if (minimum === null) return null;
    const policy = profile.minimum_policy ?? (['blocks', 'whole_usage'].includes(method) ? 'monthly' : 'none');
    const minimumApplied = (policy === 'monthly' || (policy === 'positive_usage' && used !== null && used > 0n)) && total < minimum * 1000n;
    if (minimumApplied) total = minimum * 1000n;
    const amount = (total + 5000000n) / 10000000n;
    if (amount > 2147483647n) return null;
    return { amount: Number(amount), blocks, minimumApplied, usage };
}
