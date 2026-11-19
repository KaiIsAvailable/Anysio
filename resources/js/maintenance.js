import { createPdfThumbnails } from './ticket-pdf-thumbnails';

export function initMaintenanceForm(form, { request = fetch, uuid = () => crypto.randomUUID(), success = () => {} } = {}) {
    let files = [], busy = false, key = uuid();
    const error = form.querySelector('[data-maintenance-error]');
    const picker = form.querySelector('[data-maintenance-files]');
    const list = form.querySelector('[data-maintenance-files-list]');
    let previewUrls = [];
    const clearPreviews = () => {
        previewUrls.forEach(url => URL.revokeObjectURL(url));
        previewUrls = [];
    };
    window.addEventListener('pagehide', clearPreviews, { once: true });
    const renderFiles = () => {
        if (!list) return;
        list.replaceChildren();
        clearPreviews();
        files.forEach((file, index) => {
            const row = document.createElement('div');
            row.className = 'relative flex min-w-0 flex-col gap-2 rounded-lg border border-gray-200 p-2 text-sm';
            const name = document.createElement('span'); name.textContent = file.name;
            name.className = file.type.startsWith('image/') ? 'break-words' : 'break-words pr-8';
            const remove = document.createElement('button');
            remove.type = 'button'; remove.textContent = '×';
            remove.ariaLabel = `Remove ${file.name}`;
            remove.title = 'Remove file';
            remove.className = 'absolute right-3 top-3 z-10 flex h-7 w-7 items-center justify-center rounded-full bg-white text-xl leading-none text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-red-50 hover:text-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 disabled:cursor-not-allowed disabled:opacity-50';
            remove.disabled = busy;
            remove.addEventListener('click', () => { if (!busy) { files.splice(index, 1); renderFiles(); } });
            row.append(name, remove); list.append(row);
            if (file.type.startsWith('image/')) {
                const preview = document.createElement('img');
                preview.src = URL.createObjectURL(file);
                previewUrls.push(preview.src);
                preview.alt = file.name;
                preview.className = 'order-first h-28 w-full rounded-md bg-gray-50 object-contain';
                row.append(preview);
            }
        });
    };
    picker?.addEventListener('change', () => {
        if (busy) return;
        files.push(...picker.files);
        picker.value = '';
        renderFiles();
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || form.dataset.blocked === 'true') return;
        const body = new FormData(form);
        body.set('request_key', key);
        files.forEach(file => body.append('attachments[]', file));
        busy = true; form.dataset.busy = 'true'; error.textContent = '';
        form.dispatchEvent?.(new CustomEvent('form-loading', { detail: true }));
        const controls = Array.from(form.querySelectorAll('button, input, textarea, select'));
        const disabled = controls.map(control => control.disabled);
        controls.forEach(control => { control.disabled = true; });
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 45000);
        try {
            const response = await request(form.action, { method: 'POST', body, signal: controller.signal,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(response.status === 413 ? 'Upload too large. Remove files or choose smaller files and retry.'
                : response.status === 503 ? (data.message === 'Maintenance database upgrade is required.' ? data.message : 'Maintenance is temporarily unavailable. Please try again later.')
                : response.status >= 500 ? (form.dataset.maintenanceForm === 'assign'
                    ? 'Unable to assign contractor. Your staff selection is retained. Please retry.'
                    : form.dataset.maintenanceForm === 'edit' ? 'Unable to update maintenance. Your changes are retained. Please retry.'
                    : 'Unable to save all changes. Your input and selected files are retained. Retry, or remove the files and confirm again.')
                : Object.values(data.errors || {}).flat().join('\n') || data.message || 'Unable to save. Please retry.');
            files = []; key = uuid(); renderFiles();
            success(data);
        } catch (failure) { error.textContent = failure.message; }
        finally {
            clearTimeout(timeout);
            busy = false; form.dataset.busy = 'false';
            form.dispatchEvent?.(new CustomEvent('form-loading', { detail: false }));
            controls.forEach((control, index) => { control.disabled = disabled[index]; });
            renderFiles();
        }
    });
    return { isDirty: () => files.length > 0 || ['title', 'fee_type_id', 'desc', 'location_detail'].some(name => !!form.querySelector(`[name="${name}"]`)?.value),
        reset: () => { if (busy) return; form.reset(); files = []; key = uuid(); error.textContent = ''; renderFiles(); } };
}

export function initMaintenanceAction(host, {
    request = fetch,
    initialize = form => initMaintenanceForm(form, { success: () => window.location.reload() }),
    ready = () => window.Alpine.nextTick(),
    open = modal => window.dispatchEvent(new CustomEvent('open-modal', { detail: modal })),
    report = message => window.dispatchEvent(new CustomEvent('session-status', { detail: { type: 'error', message } })),
} = {}) {
    let sequence = 0;
    return async (trigger, formType = 'edit') => {
        if (trigger.dataset.loading === 'true' || host.querySelector('form')?.dataset.busy === 'true') return;
        const current = ++sequence;
        trigger.dataset.loading = 'true';
        trigger.setAttribute('aria-busy', 'true');
        const failureMessage = formType === 'assign' ? 'Unable to open contractor assignment. Please retry.'
            : formType === 'complete' ? 'Unable to open maintenance completion. Please retry.' : 'Unable to open maintenance editing. Please retry.';
        try {
            const response = await request(trigger.href, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const data = await response.json().catch(() => ({}));
            if (current !== sequence) return;
            if (!response.ok || typeof data.html !== 'string') throw new Error(response.status >= 500
                ? failureMessage : data.message || failureMessage);
            host.innerHTML = data.html;
            const content = host.querySelector(`[data-maintenance-form="${formType}"]`);
            if (!content) throw new Error(failureMessage);
            initialize(content);
            await ready();
            if (current === sequence) open(`${formType}-maintenance`);
        } catch (error) {
            if (current === sequence) report(error.message);
        } finally {
            delete trigger.dataset.loading;
            trigger.removeAttribute('aria-busy');
        }
    };
}

export function initMaintenanceHistory(host, loadingHtml, {
    request = fetch,
    open = () => window.dispatchEvent(new CustomEvent('open-modal', { detail: 'history-maintenance' })),
} = {}) {
    let sequence = 0;
    return async trigger => {
        const current = ++sequence;
        const url = trigger.href || trigger.dataset.historyUrl;
        host.innerHTML = loadingHtml;
        host.setAttribute('aria-busy', 'true');
        host.scrollTop = 0;
        open();
        try {
            const response = await request(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const data = await response.json().catch(() => ({}));
            if (current !== sequence) return;
            if (!response.ok || typeof data.html !== 'string') throw new Error(response.status >= 500
                ? 'Unable to load maintenance history. Please retry.' : data.message || 'Unable to load maintenance history. Please retry.');
            host.innerHTML = data.html;
        } catch (error) {
            if (current !== sequence) return;
            host.querySelector('[data-maintenance-history-skeleton]').hidden = true;
            host.querySelector('[data-maintenance-history-error]').hidden = false;
            host.querySelector('[data-maintenance-history-error-message]').textContent = error.message;
            host.querySelector('[data-maintenance-history]').dataset.historyUrl = url;
        } finally {
            if (current === sequence) host.setAttribute('aria-busy', 'false');
        }
    };
}

document.addEventListener('DOMContentLoaded', () => {
    const historyHost = document.querySelector('[data-maintenance-history-host]');
    if (historyHost) {
        const loadHistory = initMaintenanceHistory(historyHost, document.querySelector('[data-maintenance-history-loading]').innerHTML);
        document.addEventListener('click', event => {
            const trigger = event.target.closest('[data-maintenance-history], [data-maintenance-history-pagination] a');
            if (!trigger) return;
            event.preventDefault();
            loadHistory(trigger);
        }, true);
    }
    const actionHost = document.querySelector('[data-maintenance-action-host]');
    if (actionHost) {
        const openAction = initMaintenanceAction(actionHost);
        document.addEventListener('click', event => {
            const trigger = event.target.closest('[data-edit-maintenance], [data-assign-maintenance], [data-complete-maintenance]');
            if (!trigger) return;
            event.preventDefault();
            openAction(trigger, trigger.hasAttribute('data-assign-maintenance') ? 'assign'
                : trigger.hasAttribute('data-complete-maintenance') ? 'complete' : 'edit');
        }, true); // List action cells stop bubbling; capture action clicks before they reach the cell.
    }
    const forms = new Map();
    document.querySelectorAll('[data-maintenance-form]').forEach(form => {
        forms.set(form, initMaintenanceForm(form, { success: data => {
            if (form.dataset.reload === 'true') { location.reload(); return; }
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'create-maintenance' }));
            document.querySelector('[data-page-feedback]').textContent = data.message;
            form.reset();
            // The next existing chat poll picks up the notice/card; no extra polling loop.
            window.dispatchEvent(new CustomEvent('maintenance-created'));
        } }));
    });
    let contextSequence = 0, lastAction = '';
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-create-maintenance]');
        if (!button) return;
        const form = document.querySelector('[data-maintenance-form="create"]');
        if (!form || form.dataset.busy === 'true') return;
        const action = button.dataset.createUrl;
        if (!action) return;
        if (lastAction !== action) {
            if (forms.get(form).isDirty() && !window.confirm('Discard the unfinished maintenance form and use this conversation?')) return;
            forms.get(form).reset();
        }
        lastAction = action;
        form.action = action;
        const sequence = ++contextSequence;
        const error = form.querySelector('[data-maintenance-error]');
        error.textContent = 'Loading source details…';
        form.dataset.blocked = 'true';
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'create-maintenance' }));
        try {
            const response = await fetch(button.dataset.contextUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const data = await response.json();
            if (sequence !== contextSequence) return;
            if (!response.ok) throw new Error(data.message || 'Unable to load source.');
            error.textContent = data.reason || '';
            form.dataset.blocked = data.reason ? 'true' : 'false';
            form.querySelector('[data-maintenance-location]').textContent = data.location || '';
            form.querySelector('[data-maintenance-existing]').textContent = data.cards.length ? 'Already linked: ' + data.cards.map(c => c.title + ' (' + c.status + ')').join(', ') : '';
            const host = form.querySelector('[data-maintenance-source-files]');
            const selected = new Set(Array.from(host.querySelectorAll('input:checked')).map(input => input.value));
            host.replaceChildren();
            data.attachments.forEach(file => {
                const row = document.createElement('label'); row.className = 'flex gap-2 text-sm';
                const input = document.createElement('input'); input.type = 'checkbox'; input.name = 'source_message_ids[]'; input.value = file.id; input.checked = selected.has(file.id);
                const link = document.createElement('a'); link.href = file.url; link.target = '_blank'; link.rel = 'noopener'; link.textContent = file.name; link.className = 'break-all text-indigo-700 underline';
                row.append(input, link); host.append(row);
            });
        } catch (failure) { if (sequence === contextSequence) error.textContent = failure.message; }
    });
    const pdfs = createPdfThumbnails(document); pdfs.scan();
    window.addEventListener('pagehide', () => pdfs.destroy(), { once: true });
    // Opening details never opens an action modal; actions require an explicit click.

});
