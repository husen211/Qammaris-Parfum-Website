import { whiteMarginBounds } from './catalog-image-bounds';

const sample = document.createElement('canvas');
const sampleContext = sample.getContext('2d', { willReadFrequently: true });

document.querySelectorAll('[data-catalog-image]').forEach((image) => {
    const frame = image.closest('[data-catalog-media]');
    const originalAlt = image.alt;
    const fallback = () => {
        frame.querySelector('canvas')?.remove();
        frame.dataset.imageFraming = 'fallback';
        frame.querySelector('[data-image-fallback]').hidden = false;
        image.alt = `Foto ${originalAlt} sedang dilengkapi`;
        if (image.getAttribute('src') !== image.dataset.fallbackSrc) image.src = image.dataset.fallbackSrc;
    };
    const balance = () => {
        if (!image.naturalWidth || frame.dataset.imageFraming) return;
        frame.dataset.imageFraming = 'original';
        if (!sampleContext) return;
        try {
            const ratio = Math.min(1, 128 / Math.max(image.naturalWidth, image.naturalHeight));
            sample.width = Math.max(1, Math.round(image.naturalWidth * ratio));
            sample.height = Math.max(1, Math.round(image.naturalHeight * ratio));
            sampleContext.drawImage(image, 0, 0, sample.width, sample.height);
            const bounds = whiteMarginBounds(sampleContext.getImageData(0, 0, sample.width, sample.height));
            if (!bounds) return;
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 640;
            canvas.className = 'catalog-media__balanced';
            canvas.setAttribute('aria-hidden', 'true');
            const context = canvas.getContext('2d');
            if (!context) return;
            const xRatio = image.naturalWidth / sample.width;
            const yRatio = image.naturalHeight / sample.height;
            const width = bounds.width * xRatio;
            const height = bounds.height * yRatio;
            const scale = 640 * .88 / Math.max(width, height);
            context.drawImage(image, bounds.x * xRatio, bounds.y * yRatio, width, height,
                (640 - width * scale) / 2, (640 - height * scale) / 2, width * scale, height * scale);
            frame.append(canvas);
            frame.dataset.imageFraming = 'balanced';
        } catch {
            // A remote disk without CORS or unavailable canvas uses the safe contain view.
        }
    };
    image.addEventListener('load', balance);
    image.addEventListener('error', fallback);
    if (image.complete) {
        if (image.naturalWidth) balance(); else fallback();
    }
});
