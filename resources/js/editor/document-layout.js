export const documentBaseCss = `
    html, body { width: 100%; margin: 0; padding: 0; }
    body { box-sizing: border-box; min-height: 297mm; display: flow-root; padding: 20mm; font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.6; color: #0f172a; }
    *, *::before, *::after { box-sizing: border-box; }
`;

// Match the editor's missing-image graphic instead of native broken-image text.
const imageFallback = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="100" viewBox="0 0 24 24" style="fill: rgba(0,0,0,0.15); transform: scale(0.75)"><path d="M2.28 3L1 4.27l2 2V19c0 1.1.9 2 2 2h12.73l2 2L21 21.72 2.28 3m2.55 0L21 19.17V5a2 2 0 0 0-2-2H4.83M8.5 13.5l2.5 3 1-1.25L14.73 18H5l3.5-4.5z"/></svg>'
);

export function applyDocumentImageFallbacks(doc) {
    for (const image of Array.from(doc.images || [])) {
        const fallback = () => {
            if (image.getAttribute('src') === imageFallback) return;
            image.removeAttribute('srcset');
            image.src = imageFallback;
        };
        image.addEventListener('error', fallback, { once: true });
        if (image.complete && !image.naturalWidth) fallback();
    }
}

export function documentHtml(content) {
    const parsed = new DOMParser().parseFromString(content || '', 'text/html');
    const styles = Array.from(parsed.querySelectorAll('style'));
    const css = styles.map(style => style.textContent || '').join('\n');
    styles.forEach(style => style.remove());
    return `<!DOCTYPE html><html><head><title>Document Preview</title>
        <style>${documentBaseCss}</style><style>${css}</style>
        <style>
            @page { size: A4 portrait; margin: 20mm; }
            @media screen { html { overflow: hidden !important; } }
            @media print {
                html, body { width: auto !important; min-height: 0 !important; height: auto !important; overflow: visible !important; }
                body { margin: 0 !important; padding: 0 !important; }
                body > :last-child { margin-bottom: 0 !important; break-after: auto !important; page-break-after: auto !important; }
                ol:empty, ul:empty, p:empty { display: none !important; }
                .pdf-page-break:last-child { display: none !important; }
                * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .pdf-page-break {
                    display: block !important; break-before: page !important; page-break-before: always !important;
                    border: 0 !important; margin: 0 !important; padding: 0 !important;
                    height: 0 !important; min-height: 0 !important; line-height: 0 !important;
                    font-size: 0 !important; box-shadow: none !important; background: transparent !important;
                }
                .pdf-page-break > *, .pdf-page-break::before, .pdf-page-break::after { display: none !important; }
            }
        </style></head><body>${parsed.body.innerHTML}</body></html>`;
}

export async function printDocument(content, title = 'Document Preview') {
    const popup = window.open('', '_blank', 'height=600,width=800');
    if (!popup) return;
    popup.document.write(documentHtml(content));
    popup.document.close();
    popup.document.title = title;
    if (popup.document.readyState !== 'complete') {
        await new Promise(resolve => popup.addEventListener('load', resolve, { once: true }));
    }
    await popup.document.fonts.ready;
    applyDocumentImageFallbacks(popup.document);
    await Promise.all(Array.from(popup.document.images, image => image.decode().catch(() => {})));
    popup.focus();
    popup.print();
    popup.close();
}

export function resizeDocumentPaper(frame) {
    frame.paperObserver?.disconnect();
    const body = frame.contentDocument?.body;
    if (!body) return;
    applyDocumentImageFallbacks(frame.contentDocument);
    const resize = () => {
        frame.style.height = `${Math.ceil(Math.max(body.getBoundingClientRect().height, body.scrollHeight))}px`;
    };
    frame.paperObserver = new ResizeObserver(resize);
    frame.paperObserver.observe(body);
    resize();
}
