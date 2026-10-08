// Fluid Expanding Grid: preserve all nine items, expand by click, never crop/zoom the image.
const fluidGallery = document.querySelector('[data-fluid-gallery]');
if (fluidGallery) {
    const controls = [...fluidGallery.querySelectorAll('[data-expand-photo]')];
    controls.forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', () => {
            const selected = button.getAttribute('aria-pressed') !== 'true';
            controls.forEach((control) => {
                const active = control === button && selected;
                control.setAttribute('aria-pressed', String(active));
                control.closest('[data-gallery-item]').classList.toggle('about-gallery-expanded', active);
                control.querySelector('[data-expand-label]').textContent = active ? 'Kembalikan ukuran' : 'Perbesar di galeri';
            });
        });
    });
}

const dialog = document.querySelector('[data-about-dialog]');
const photos = [...document.querySelectorAll('[data-about-photo]')];

if (dialog && typeof dialog.showModal === 'function') {
    const image = dialog.querySelector('[data-dialog-photo]');
    const caption = dialog.querySelector('figcaption');
    const position = dialog.querySelector('[data-photo-position]');
    const previous = dialog.querySelector('[data-photo-prev]');
    const next = dialog.querySelector('[data-photo-next]');
    let index = 0;
    let opener;
    const showPhoto = (number) => {
        index = number;
        const photo = photos[index];
        image.alt = photo.dataset.photoAlt;
        image.src = photo.href;
        caption.textContent = photo.dataset.photoCaption;
        position.textContent = `${index + 1} / ${photos.length}`;
        previous.disabled = index === 0;
        next.disabled = index === photos.length - 1;
    };
    photos.forEach((photo, number) => photo.addEventListener('click', (event) => {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        opener = photo;
        showPhoto(number);
        dialog.showModal();
        document.body.classList.add('about-photo-open');
    }));
    previous.addEventListener('click', () => { if (index > 0) showPhoto(index - 1); });
    next.addEventListener('click', () => { if (index < photos.length - 1) showPhoto(index + 1); });
    dialog.querySelector('[data-close-photo]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft' && index > 0) { event.preventDefault(); showPhoto(index - 1); }
        if (event.key === 'ArrowRight' && index < photos.length - 1) { event.preventDefault(); showPhoto(index + 1); }
    });
    image.addEventListener('error', () => { caption.textContent = 'Foto belum bisa dimuat. Tutup lalu pilih foto untuk mencoba lagi.'; });
    dialog.addEventListener('close', () => {
        document.body.classList.remove('about-photo-open');
        opener?.focus({preventScroll: true});
    });
    window.addEventListener('pagehide', () => { if (dialog.open) dialog.close(); });
}

const reel = document.querySelector('[data-about-reel]');
if (reel) {
    const button = reel.querySelector('[data-load-reel]');
    const status = reel.querySelector('[data-reel-status]');
    const url = new URL(reel.dataset.embedUrl);
    if (url.origin === 'https://www.instagram.com' && url.pathname === '/reel/Dd0iMrDJURL/embed/') {
        button.hidden = false;
        button.addEventListener('click', () => {
            button.disabled = true;
            status.textContent = 'Memuat Reels dari Instagram…';
            const iframe = document.createElement('iframe');
            iframe.src = url.href;
            iframe.title = 'Reels Qammaris: suasana experience store';
            iframe.allow = 'fullscreen; picture-in-picture';
            iframe.referrerPolicy = 'strict-origin-when-cross-origin';
            const timeout = window.setTimeout(() => { status.textContent = 'Jika video belum tampil, gunakan tautan Instagram di bawah.'; }, 15000);
            iframe.addEventListener('load', () => {
                window.clearTimeout(timeout);
                status.textContent = 'Untuk menonton, gunakan tombol putar di video. Jika tidak tampil, buka di Instagram.';
            }, {once:true});
            iframe.addEventListener('error', () => {
                window.clearTimeout(timeout);
                status.textContent = 'Reels belum bisa dimuat. Anda tetap bisa menontonnya di Instagram.';
            }, {once:true});
            reel.querySelector('.about-reel-preview').replaceWith(iframe);
        }, {once:true});
    }
}
