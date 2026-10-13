document.addEventListener('DOMContentLoaded', () => {
    const request = async (url, options = {}) => {
        const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...options.headers } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(response.status === 413 ? 'This upload exceeds the server POST limit. Use fewer or smaller files.'
                : data.errors ? Object.values(data.errors).flat().join('\n') : data.message || 'Unable to complete the request. Please try again.');
            error.status = response.status;
            throw error;
        }
        return data;
    };
    const toggleControls = (form, disabled) => form.querySelectorAll('button, input, textarea').forEach((control) => { control.disabled = disabled; });

    const selectionError = (attachments, incoming, limits) => {
        const all = [...attachments.map((item) => item.file), ...incoming];
        let error = '';
        if (all.length > limits.max_files || all.reduce((sum, file) => sum + file.size, 0) > limits.max_total_kb * 1024) error = 'The selection exceeds the file count or total size limit.';
        for (const file of incoming) {
            const type = limits.types[file.name.split('.').pop().toLowerCase()];
            if (!type || file.size > type.max_kb * 1024) error = 'A selected file has an unsupported format or exceeds its size limit.';
        }
        return error;
    };

    const index = document.querySelector('[data-customer-service]');
    if (index) initIndex(index);
    document.querySelectorAll('[data-complaint-create]').forEach((root) => initComplaint(root));
    document.querySelectorAll('[data-ticket-chat]').forEach(initChat);

    function initComplaint(root) {
        const find = (selector) => root.querySelector(selector);
        const complaint = find('[data-complaint-modal]');
        const createForm = find('[data-complaint-form]');
        const createError = find('[data-create-error]');
        const limits = JSON.parse(complaint.dataset.limits);
        const previews = find('[data-create-previews]');
        const submit = find('[data-create-submit]');
        const attachments = [];
        let creating = false;
        const clearAttachments = () => {
            attachments.forEach((item) => { if (item.url) URL.revokeObjectURL(item.url); });
            attachments.length = 0;
            previews.replaceChildren();
        };
        const renderPreviews = () => {
            previews.replaceChildren();
            attachments.forEach((item, index) => {
                const card = document.createElement('div');
                card.className = 'shrink-0 rounded-xl border p-2 w-40';
                const name = document.createElement('p');
                name.className = 'text-xs break-words';
                name.textContent = item.file.name;
                card.append(name);
                if (item.url) {
                    const media = document.createElement(item.file.type.startsWith('image/') ? 'img' : 'video');
                    media.src = item.url;
                    media.className = 'max-h-24 max-w-full my-2';
                    if (media.tagName === 'VIDEO') { media.controls = true; media.preload = 'metadata'; }
                    else media.alt = item.file.name;
                    card.append(media);
                }
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = 'Remove';
                remove.className = 'text-xs text-red-700 underline';
                remove.addEventListener('click', () => {
                    if (creating) return;
                    if (item.url) URL.revokeObjectURL(item.url);
                    attachments.splice(index, 1);
                    renderPreviews();
                });
                card.append(remove);
                previews.append(card);
            });
        };
        ['document', 'media', 'camera'].forEach((kind) => {
            const picker = find('[data-create-files="' + kind + '"]');
            find('[data-create-pick="' + kind + '"]').addEventListener('click', () => {
                if (!creating) picker.click();
            });
            picker.addEventListener('change', () => {
                if (creating) return;
                const incoming = Array.from(picker.files);
                const error = selectionError(attachments, incoming, limits);
                createError.textContent = error;
                if (!error) {
                    incoming.forEach((file) => attachments.push({ file, url: /^(image|video)\//.test(file.type) ? URL.createObjectURL(file) : null }));
                    renderPreviews();
                }
                picker.value = '';
            });
        });
        window.addEventListener('pagehide', clearAttachments);
        find('[data-open-complaint]').addEventListener('click', () => {
            createError.textContent = '';
            if (creating) return;
            createForm.reset();
            clearAttachments();
            complaint.showModal();
        });
        find('[data-cancel-complaint]').addEventListener('click', () => { if (!creating) complaint.close(); });
        find('[data-close-complaint]').addEventListener('click', () => { if (!creating) complaint.close(); });
        complaint.addEventListener('cancel', (event) => { if (creating) event.preventDefault(); });
        createForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (creating) return;
            createError.textContent = '';
            const body = new FormData(createForm);
            if (!String(body.get('subject') || '').trim()) { createError.textContent = 'Please enter a subject.'; return; }
            if (!String(body.get('details') || '').trim() && !attachments.length) { createError.textContent = 'Enter details or choose at least one attachment.'; return; }
            attachments.forEach((item) => body.append('attachments[]', item.file));
            creating = true;
            submit.textContent = 'Creating...';
            createForm.setAttribute('aria-busy', 'true');
            toggleControls(createForm, true);
            try {
                const data = await request(createForm.action, { method: 'POST', body });
                complaint.close();
                createForm.reset();
                clearAttachments();
                window.location.assign(data.ticket.show_url);
            } catch (error) { createError.textContent = error.message; }
            finally { creating = false; toggleControls(createForm, false); submit.textContent = 'Create'; createForm.setAttribute('aria-busy', 'false'); }
        });
    }

    function initIndex(root) {
        const find = (selector) => root.querySelector(selector);
        const list = find('[data-ticket-list]');
        const feedback = find('[data-page-feedback]');
        let listSequence = 0;
        const refreshList = async (url = window.location.href) => {
            const sequence = ++listSequence;
            const data = await request(url);
            if (sequence !== listSequence) return false;
            list.innerHTML = data.html;
            const reason = find('[data-creation-reason]');
            reason.textContent = data.creation_reason || '';
            reason.hidden = !data.creation_reason;
            find('[data-open-complaint]').disabled = !!data.creation_reason;
            return true;
        };
        initComplaint(root);
        find('[data-ticket-search]').addEventListener('submit', async (event) => {
            event.preventDefault();
            const url = new URL(root.dataset.indexUrl);
            const search = new FormData(event.currentTarget).get('search');
            if (search) url.searchParams.set('search', search);
            try { if (await refreshList(url)) { window.history.replaceState(null, '', url); feedback.textContent = ''; } }
            catch (error) { feedback.textContent = error.message; }
        });
        list.addEventListener('click', async (event) => {
            const row = event.target.closest('[data-ticket-url]');
            if (row) {
                if (!event.target.closest('a')) window.location.assign(row.dataset.ticketUrl);
                return;
            }
            const link = event.target.closest('a');
            if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
                event.preventDefault();
                try { if (await refreshList(link.href)) window.history.replaceState(null, '', link.href); }
                catch (error) { feedback.textContent = error.message; }
            }
        });
        list.addEventListener('keydown', (event) => {
            const row = event.target.closest('[data-ticket-url]');
            if (row && !event.target.closest('a') && ['Enter', ' '].includes(event.key)) {
                event.preventDefault();
                window.location.assign(row.dataset.ticketUrl);
            }
        });
    }

    function initChat(root) {
        const find = (selector) => root.querySelector(selector);
        const limits = JSON.parse(root.dataset.limits);
        const currentRole = root.dataset.currentRole;
        const permitted = root.dataset.canSend === 'true';
        const chatForm = find('[data-chat-form]');
        const chatError = find('[data-chat-error]');
        const history = find('[data-chat-messages]');
        const draft = find('[data-chat-draft]');
        const attachButton = find('[data-chat-attach]');
        const menu = find('[data-chat-attachment-menu]');
        const sendButton = find('[data-chat-send]');
        const pickers = ['document', 'media', 'camera'].map((kind) => find('[data-chat-files="' + kind + '"]'));
        const previews = find('[data-attachment-previews]');
        const attachments = [];
        let canSend = permitted;
        let sending = false;
        let readSequence = 0;
        let composing = false;
        const setMenu = (open, restoreFocus = false) => {
            menu.hidden = !open;
            attachButton.setAttribute('aria-expanded', String(open));
            if (restoreFocus) attachButton.focus();
        };
        const updateComposer = () => {
            sendButton.disabled = sending || !canSend || (!draft.value.trim() && !attachments.length);
            sendButton.setAttribute('aria-label', sending ? 'Sending message' : 'Send message');
            chatForm.setAttribute('aria-busy', String(sending));
            find('[data-chat-send-icon]').hidden = sending;
            find('[data-chat-loading]').hidden = !sending;
            if (!canSend) setMenu(false);
        };
        const resizeDraft = () => {
            draft.style.height = 'auto';
            draft.style.height = Math.min(draft.scrollHeight, 160) + 'px';
        };
        attachButton.addEventListener('click', () => {
            if (!sending && canSend) setMenu(menu.hidden);
        });
        document.addEventListener('pointerdown', (event) => {
            if (!menu.contains(event.target) && !attachButton.contains(event.target)) setMenu(false);
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !menu.hidden) {
                event.preventDefault();
                setMenu(false, true);
            }
        });
        document.addEventListener('focusin', (event) => {
            if (!menu.contains(event.target) && !attachButton.contains(event.target)) setMenu(false);
        });
        window.addEventListener('resize', resizeDraft);
        draft.addEventListener('input', () => { resizeDraft(); updateComposer(); });
        draft.addEventListener('compositionstart', () => { composing = true; });
        draft.addEventListener('compositionend', () => { composing = false; });
        draft.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || event.shiftKey || event.isComposing || composing || event.keyCode === 229) return;
            if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
            event.preventDefault();
            if (!sendButton.disabled) chatForm.requestSubmit();
        });
        const clearAttachments = () => {
            attachments.forEach((item) => { if (item.url) URL.revokeObjectURL(item.url); });
            attachments.length = 0;
            previews.replaceChildren();
            pickers.forEach((picker) => { picker.value = ''; });
            updateComposer();
        };
        const renderPreviews = () => {
            previews.replaceChildren();
            attachments.forEach((item, index) => {
                const card = document.createElement('div');
                card.className = 'shrink-0 rounded-xl border p-2 w-40';
                const name = document.createElement('p');
                name.className = 'text-xs break-words';
                name.textContent = item.file.name + ' (' + (item.file.size / 1024 / 1024).toFixed(2) + ' MB)';
                card.append(name);
                if (item.url) {
                    const media = document.createElement(item.file.type.startsWith('image/') ? 'img' : 'video');
                    media.src = item.url;
                    media.className = 'max-h-24 max-w-full my-2';
                    if (media.tagName === 'VIDEO') { media.controls = true; media.preload = 'metadata'; }
                    else media.alt = item.file.name;
                    card.append(media);
                }
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = 'Remove';
                remove.className = 'text-xs text-red-700 underline';
                remove.disabled = sending;
                remove.addEventListener('click', () => {
                    if (sending) return;
                    if (item.url) URL.revokeObjectURL(item.url);
                    attachments.splice(index, 1);
                    renderPreviews();
                    updateComposer();
                });
                card.append(remove);
                previews.append(card);
            });
        };
        const renderConversation = (data) => {
            const ticket = data.ticket;
            canSend = permitted && !ticket.closed && ticket.can_send !== false;
            find('[data-chat-subject]').textContent = ticket.subject;
            find('[data-chat-status]').textContent = ticket.status;
            chatForm.hidden = !canSend;
            updateComposer();
            find('[data-chat-closed]').hidden = canSend;
            history.replaceChildren();
            if (!data.messages.length) {
                const empty = document.createElement('p');
                empty.textContent = 'No messages yet. Start the conversation below.';
                empty.className = 'text-center text-sm text-gray-500';
                history.append(empty);
            }
            data.messages.forEach((message) => {
                const own = message.sender_type === currentRole;
                const row = document.createElement('div');
                row.className = own ? 'flex justify-end' : 'flex justify-start';
                const bubble = document.createElement('article');
                bubble.className = own ? 'max-w-[85%] rounded-2xl bg-indigo-100 px-4 py-3 text-slate-900' : 'max-w-[85%] rounded-2xl bg-white border px-4 py-3 text-slate-900';
                const label = document.createElement('p');
                label.className = 'mb-1 text-xs text-gray-500';
                label.textContent = (own ? 'You' : message.sender_type) + ' · ' + (message.created_at || '');
                bubble.append(label);
                if (message.attachment_url) {
                    const extension = message.extension;
                    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov'].includes(extension)) {
                        const image = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension);
                        const media = document.createElement(image ? 'img' : 'video');
                        media.src = message.attachment_url;
                        media.className = 'max-h-64 max-w-full rounded-lg';
                        if (image) { media.alt = 'Ticket attachment'; media.loading = 'lazy'; }
                        else { media.controls = true; media.preload = 'metadata'; }
                        bubble.append(media);
                    }
                    const download = document.createElement('a');
                    download.href = message.attachment_url + '?download=1';
                    download.textContent = 'Download ' + extension.toUpperCase() + ' attachment';
                    download.className = 'inline-block mt-2 text-sm text-indigo-700 underline';
                    bubble.append(download);
                } else {
                    const text = document.createElement('p');
                    text.className = 'text-sm whitespace-pre-wrap break-words';
                    text.textContent = message.text;
                    bubble.append(text);
                }
                row.append(bubble);
                history.append(row);
            });
            history.scrollTop = history.scrollHeight;
        };
        const loadConversation = async () => {
            const sequence = ++readSequence;
            const data = await request(root.dataset.readUrl);
            if (sequence === readSequence) renderConversation(data);
        };
        ['document', 'media', 'camera'].forEach((kind, index) => {
            const picker = pickers[index];
            find('[data-chat-pick="' + kind + '"]').addEventListener('click', () => {
                if (sending || !canSend) return;
                setMenu(false, true);
                picker.click();
            });
            picker.addEventListener('change', () => {
                if (sending || !canSend) return;
                const incoming = Array.from(picker.files);
                const error = selectionError(attachments, incoming, limits);
                chatError.textContent = error;
                if (!error) {
                    incoming.forEach((file) => attachments.push({ file, url: (file.type.startsWith('image/') || file.type.startsWith('video/')) ? URL.createObjectURL(file) : null }));
                    renderPreviews();
                    updateComposer();
                }
                picker.value = '';
            });
        });
        chatForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (sending || !canSend) return;
            chatError.textContent = '';
            const items = [];
            if (draft.value.trim()) items.push({ type: 'text', text: draft.value });
            attachments.forEach((item) => items.push({ type: 'file', file: item.file }));
            if (!items.length) { chatError.textContent = 'Enter a message or choose an attachment.'; return; }
            const body = new FormData(chatForm);
            items.forEach((item, index) => {
                body.append('items[' + index + '][type]', item.type);
                body.append('items[' + index + '][' + (item.type === 'text' ? 'text' : 'file') + ']', item.type === 'text' ? item.text : item.file);
            });
            sending = true;
            setMenu(false);
            ++readSequence;
            toggleControls(chatForm, true);
            updateComposer();
            try {
                const data = await request(root.dataset.sendUrl, { method: 'POST', body });
                renderConversation(data);
                draft.value = '';
                resizeDraft();
                clearAttachments();
            } catch (error) {
                chatError.textContent = error.status ? error.message : 'The connection failed. Refresh this conversation before retrying to avoid duplicate messages.';
                if (error.status === 403) {
                    try { await loadConversation(); } catch { /* Keep the error and draft. */ }
                }
            } finally { sending = false; toggleControls(chatForm, !canSend); updateComposer(); }
        });
        updateComposer();
        resizeDraft();
        window.addEventListener('pagehide', clearAttachments);
        loadConversation().catch((error) => { chatError.textContent = error.message; });
    }
});
