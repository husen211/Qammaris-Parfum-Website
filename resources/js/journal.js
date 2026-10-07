import '../css/journal.css';

const narrow = window.matchMedia('(max-width: 1099px)');
const toc = document.querySelector('.journal-toc');
if (toc) {
    const adapt = () => { toc.open = !narrow.matches; };
    adapt();
    narrow.addEventListener('change', adapt);
}

const useImageFallback = image => {
    if (!(image instanceof HTMLImageElement) || !image.dataset.journalFallback) return;
    const fallback = image.dataset.journalFallback;
    delete image.dataset.journalFallback;
    image.src = fallback;
};
document.addEventListener('error', event => useImageFallback(event.target), true);
document.querySelectorAll('img[data-journal-fallback]').forEach(image => {
    if (image.complete && image.naturalWidth === 0) useImageFallback(image);
});

const share = document.querySelector('[data-journal-share]');
if (share) {
    const status = share.querySelector('[data-share-status]');
    const copy = share.querySelector('[data-journal-copy]');
    const native = share.querySelector('[data-journal-native]');
    const fallback = share.querySelector('[data-share-fallback]');
    copy.addEventListener('click', async () => {
        copy.disabled = true;
        status.textContent = 'Menyalin tautan…';
        try {
            await navigator.clipboard.writeText(share.dataset.shareUrl);
            status.textContent = 'Tautan berhasil disalin.';
        } catch {
            fallback.hidden = false;
            fallback.focus();
            fallback.select();
            status.textContent = 'Salin tautan dari kolom ini. Anda dapat mencoba tombol lagi.';
        } finally {
            copy.disabled = false;
        }
    });
    if (navigator.share) {
        native.hidden = false;
        native.addEventListener('click', async () => {
            native.disabled = true;
            try {
                await navigator.share({title: share.dataset.shareTitle, url: share.dataset.shareUrl});
                status.textContent = 'Pilihan berbagi sudah dibuka.';
            } catch (error) {
                status.textContent = error.name === 'AbortError' ? 'Berbagi dibatalkan.' : 'Belum dapat berbagi. Gunakan salin tautan atau WhatsApp.';
            } finally {
                native.disabled = false;
            }
        });
    }
}
