import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist/legacy/build/pdf.mjs';
import workerUrl from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?url';

// Both imports resolve from the same locked package; no CDN or public attachment copy.
GlobalWorkerOptions.workerSrc = workerUrl;
export { getDocument };
