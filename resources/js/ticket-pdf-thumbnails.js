const loadEngine = () => import('./ticket-pdf-engine');

export function createPdfThumbnails(root, {
    load = loadEngine,
    Observer = globalThis.IntersectionObserver,
    baseUrl = globalThis.location?.href,
    pixelRatio = globalThis.devicePixelRatio || 1,
} = {}) {
    const jobs = new Map();
    let active = 0;
    let disposed = false;
    const release = (job) => {
        if (!job.cleanup) job.cleanup = Promise.resolve(job.task?.destroy()).catch(() => {});
        return job.cleanup;
    };
    const cancel = (job) => {
        job.cancelled = true;
        job.render?.cancel();
        if (job.task) void release(job);
    };
    const run = async (job) => {
        active++;
        job.started = true;
        const timer = setTimeout(() => cancel(job), 20000);
        try {
            const url = new URL(job.card.dataset.pdfUrl, baseUrl);
            if (url.origin !== new URL(baseUrl).origin) throw new Error('Private attachment must be same origin');
            const pdfjs = await load();
            if (job.cancelled) return;
            job.task = pdfjs.getDocument({ url: url.href, withCredentials: true,
                disableAutoFetch: true, disableStream: true, isEvalSupported: false });
            job.task.onPassword = () => cancel(job);
            const pdf = await job.task.promise;
            if (job.cancelled) return;
            const page = await pdf.getPage(1);
            if (job.cancelled) return;
            const original = page.getViewport({ scale: 1 });
            const scale = Math.min(144 / original.width, 160 / original.height);
            const ratio = Math.min(2, Math.max(1, pixelRatio));
            const viewport = page.getViewport({ scale: scale * ratio });
            const canvas = job.canvas;
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            canvas.style.width = `${viewport.width / ratio}px`;
            canvas.style.height = `${viewport.height / ratio}px`;
            job.render = page.render({ canvasContext: canvas.getContext('2d'), viewport });
            await job.render.promise;
            if (job.cancelled) return;
            canvas.hidden = false;
            job.fallback.hidden = true;
            job.complete = true;
        } catch {
            // Unauthorized, malformed, encrypted or unsupported PDFs keep their working links and icon.
        } finally {
            clearTimeout(timer);
            if (job.task) await release(job);
            if (!job.complete && (!jobs.has(job.card) || jobs.get(job.card) === job)) {
                job.canvas.width = 0; job.canvas.height = 0;
            }
            active--;
            pump();
        }
    };
    const pump = () => {
        if (disposed) return;
        for (const job of jobs.values()) {
            if (active >= 2) break;
            if (job.near && !job.started && !job.cancelled) void run(job);
        }
    };
    const observer = Observer ? new Observer((entries) => {
        entries.forEach(({ target, isIntersecting }) => {
            const job = jobs.get(target);
            if (job) job.near = isIntersecting;
        });
        pump();
    }, { root, rootMargin: '200px 0px' }) : null;
    const reset = () => {
        observer?.disconnect();
        jobs.forEach((job) => {
            cancel(job);
            job.canvas.hidden = true;
            job.fallback.hidden = false;
            // A running render owns its canvas until cancellation settles.
            if (job.complete || !job.started) { job.canvas.width = 0; job.canvas.height = 0; }
        });
        jobs.clear();
    };
    return {
        scan() {
            if (disposed || !observer) return; // Older browsers retain the icon; never eagerly fetch every PDF.
            root.querySelectorAll('[data-pdf-url]').forEach((card) => {
                if (jobs.has(card)) return;
                const canvas = card.querySelector('[data-pdf-canvas]');
                const fallback = card.querySelector('[data-pdf-fallback]');
                if (!canvas || !fallback) return;
                jobs.set(card, { card, canvas, fallback });
                observer.observe(card);
            });
        },
        reset,
        destroy() { disposed = true; reset(); },
    };
}
