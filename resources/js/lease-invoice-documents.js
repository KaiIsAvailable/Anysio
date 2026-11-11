let previewSequence = 0;

window.addEventListener('lease-document-preview-closed', () => { previewSequence++; });

window.openLeaseInvoiceDocument = async function (url, title) {
    if (!url) return;
    const sequence = ++previewSequence;
    const requestId = `invoice-document-${sequence}`;
    window.dispatchEvent(new CustomEvent('open-lease-preview-loading', { detail: { requestId, title } }));
    try {
        const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Document unavailable');
        const data = await response.json();
        if (sequence !== previewSequence) return;
        if (!data.content) throw new Error('Document unavailable');
        window.openDocumentPreview(data.type, data.content, data.variables, data.items, data.title, {
            ...data.extra, previewTarget: 'agreement', previewRequestId: requestId
        });
    } catch (error) {
        if (sequence === previewSequence) window.dispatchEvent(new CustomEvent('lease-preview-error', {
            detail: { requestId, message: 'Unable to load this document. Close the preview and try again.' }
        }));
    }
};
