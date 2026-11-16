const pendingForms = new WeakSet();

export function focusFormErrors(form, errorFields = null) {
    const names = errorFields && new Set(errorFields);
    const controls = Array.from(form.querySelectorAll('input, select, textarea'));
    for (const field of controls) {
        const name = (field.name || '').replace(/\[([^\]]*)\]/g, '.$1').replace(/\.$/, '');
        if (field.disabled || (names ? !names.has(name) : field.validity.valid)) continue;
        const target = field.type === 'hidden'
            ? field.closest('[x-data]')?.querySelector('input:not([type="hidden"]):not(:disabled), button:not(:disabled)')
            : field;
        if (!target || !target.getClientRects().length) continue;
        target.setAttribute('aria-invalid', 'true');
        target.addEventListener('input', () => target.removeAttribute('aria-invalid'), { once: true });
        target.addEventListener('change', () => target.removeAttribute('aria-invalid'), { once: true });
        target.scrollIntoView({ block: 'center', inline: 'nearest' });
        target.focus({ preventScroll: true });
        return target;
    }
    return null;
}

export function focusInvalidForm(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (pendingForms.has(form)) return;
    pendingForms.add(form);
    queueMicrotask(() => {
        pendingForms.delete(form);
        focusFormErrors(form);
    });
}
