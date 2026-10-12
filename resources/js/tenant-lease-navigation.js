document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tenant-lease-url]').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            const control = event.target.closest('a, button, input, select, textarea, label, summary, [role="button"], [role="link"], [tabindex], [contenteditable]:not([contenteditable="false"]), [data-no-row-navigation]');
            if (control && control !== row) return;
            if (window.getSelection()?.toString()) return;
            window.location.assign(row.dataset.tenantLeaseUrl);
        });
        row.addEventListener('keydown', (event) => {
            if (event.target !== row || event.defaultPrevented || event.repeat || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            if (!['Enter', ' '].includes(event.key)) return;
            event.preventDefault();
            window.location.assign(row.dataset.tenantLeaseUrl);
        });
    });
});
