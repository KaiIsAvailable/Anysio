import { createPdfThumbnails } from './ticket-pdf-thumbnails';

// No timer: cards are delivered by the existing chat synchronization loop.
export function renderMaintenanceCards(root, cards) {
    const host = root?.querySelector('[data-maintenance-cards]');
    if (!host || !Array.isArray(cards)) return;
    const signature = JSON.stringify(cards);
    if (host.dataset.signature === signature) return;
    host.dataset.signature = signature;
    const top = host.scrollTop;
    host.replaceChildren();
    host.hidden = cards.length === 0;
    for (const card of cards) {
        const article = document.createElement('article');
        article.className = 'rounded-xl border border-indigo-100 bg-white p-3 text-sm';
        const title = document.createElement('p');
        title.className = 'font-semibold';
        title.textContent = card.title;
        const status = document.createElement('p');
        status.className = 'text-xs text-indigo-700 break-all';
        status.textContent = card.number + ' · ' + card.status.toUpperCase();
        article.append(title, status);
        for (const file of card.attachments || []) {
            const link = document.createElement('a');
            link.href = file.url;
            link.target = '_blank';
            link.rel = 'noopener';
            link.className = 'mt-2 block break-words text-indigo-700 underline';
            link.textContent = 'View evidence: ' + file.filename;
            if (file.mime.startsWith('image/')) {
                const image = document.createElement('img');
                image.src = file.url;
                image.alt = 'Completion evidence';
                image.loading = 'lazy';
                image.className = 'mt-2 max-h-32 rounded-lg';
                link.append(image);
            }
            article.append(link);
            const download = document.createElement('a');
            download.href = file.url + '?download=1';
            download.textContent = 'Download';
            download.className = 'block text-xs text-indigo-700 underline';
            article.append(download);
        }
        host.append(article);
    }
    host.scrollTop = top;
}

export function initMaintenanceForm(form, { request = fetch, uuid = () => crypto.randomUUID(), success = () => {} } = {}) {
    let files = [], busy = false, key = uuid();
    const error = form.querySelector('[data-maintenance-error]');
    const picker = form.querySelector('[data-maintenance-files]');
    const list = form.querySelector('[data-maintenance-files-list]');
    const renderFiles = () => {
        if (!list) return;
        list.replaceChildren();
        files.forEach((file, index) => {
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between gap-2 text-sm';
            const name = document.createElement('span'); name.textContent = file.name;
            const remove = document.createElement('button');
            remove.type = 'button'; remove.textContent = 'Remove'; remove.className = 'text-red-700 underline';
            remove.disabled = busy;
            remove.addEventListener('click', () => { if (!busy) { files.splice(index, 1); renderFiles(); } });
            row.append(name, remove); list.append(row);
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
                : response.status >= 500 ? 'Unable to save all changes. Your input and selected files are retained. Retry, or remove the files and confirm again.'
                : Object.values(data.errors || {}).flat().join('\n') || data.message || 'Unable to save. Please retry.');
            files = []; key = uuid(); renderFiles();
            success(data);
        } catch (failure) { error.textContent = failure.message; }
        finally {
            clearTimeout(timeout);
            busy = false; form.dataset.busy = 'false';
            controls.forEach((control, index) => { control.disabled = disabled[index]; });
            renderFiles();
        }
    });
    return { isDirty: () => files.length > 0 || !!form.querySelector('[name="title"]')?.value,
        reset: () => { if (busy) return; form.reset(); files = []; key = uuid(); error.textContent = ''; renderFiles(); } };
}

document.addEventListener('DOMContentLoaded', () => {
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
    const cards = document.querySelector('[data-maintenance-cards]');
    if (cards?.dataset.initial) renderMaintenanceCards(cards.parentElement, JSON.parse(cards.dataset.initial));
    const pdfs = createPdfThumbnails(document); pdfs.scan();
    window.addEventListener('pagehide', () => pdfs.destroy(), { once: true });
    // Opening details never opens an action modal; actions require an explicit click.

});
