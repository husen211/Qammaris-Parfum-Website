import './about-navigation.js';
import './about-hero.js';

// 0xUrvish/FluidExpandingGrid: expand by click, never crop/zoom the image.
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
const visiblePhotos = () => photos.filter((photo) => !photo.closest('[data-gallery-item]').hidden);

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
        const available = visiblePhotos();
        const photo = available[index];
        image.alt = photo.dataset.photoAlt;
        image.src = photo.href;
        caption.textContent = photo.dataset.photoCaption;
        position.textContent = `${index + 1} / ${available.length}`;
        previous.disabled = index === 0;
        next.disabled = index === visiblePhotos().length - 1;
    };
    photos.forEach((photo) => photo.addEventListener('click', (event) => {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        opener = photo;
        showPhoto(visiblePhotos().indexOf(photo));
        dialog.showModal();
        document.body.classList.add('about-photo-open');
    }));
    previous.addEventListener('click', () => { if (index > 0) showPhoto(index - 1); });
    next.addEventListener('click', () => { if (index < visiblePhotos().length - 1) showPhoto(index + 1); });
    dialog.querySelector('[data-close-photo]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft' && index > 0) { event.preventDefault(); showPhoto(index - 1); }
        if (event.key === 'ArrowRight' && index < visiblePhotos().length - 1) { event.preventDefault(); showPhoto(index + 1); }
    });
    image.addEventListener('error', () => { caption.textContent = 'Foto belum bisa dimuat. Tutup lalu pilih foto untuk mencoba lagi.'; });
    dialog.addEventListener('close', () => {
        document.body.classList.remove('about-photo-open');
        opener?.focus({preventScroll: true});
    });
    window.addEventListener('pagehide', () => { if (dialog.open) dialog.close(); });
}

// moumensoliman/GalleryGridBlock10596: filters apply to both grid and lightbox.
const filters = document.querySelector('[data-gallery-filters]');
if (fluidGallery && filters) {
    const buttons = [...filters.querySelectorAll('[data-gallery-filter]')];
    const items = [...fluidGallery.querySelectorAll('[data-gallery-item]')];
    const count = document.querySelector('[data-gallery-count]');
    const applyFilter = (category) => {
        let total = 0;
        items.forEach((item) => {
            item.hidden = category !== 'Semua' && item.dataset.photoCategory !== category;
            if (!item.hidden) total++;
            item.classList.remove('about-gallery-expanded');
            item.querySelector('[data-expand-photo]').setAttribute('aria-pressed', 'false');
            item.querySelector('[data-expand-label]').textContent = 'Perbesar di galeri';
        });
        buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.galleryFilter === category)));
        count.textContent = `${total} foto · ${category}`;
    };
    filters.hidden = false;
    buttons.forEach((button) => button.addEventListener('click', () => applyFilter(button.dataset.galleryFilter)));
    applyFilter('Semua');
}

// dillionverma/HeroVideoDialog1107: opt-in, native focus trap, close/unmount and reopen.
// Instagram owns playback controls; don't invent a video timeline or autoplay permission.
const reel = document.querySelector('[data-about-reel]');
const videoDialog = document.querySelector('[data-video-dialog]');
if (reel && videoDialog && typeof videoDialog.showModal === 'function') {
    const button = reel.querySelector('[data-load-reel]');
    const status = videoDialog.querySelector('[data-reel-status]');
    const frame = videoDialog.querySelector('[data-video-frame]');
    let timeout;
    const url = new URL(reel.dataset.embedUrl);
    if (url.origin === 'https://www.instagram.com' && url.pathname === '/reel/Dd0iMrDJURL/embed/') {
        button.hidden = false;
        button.addEventListener('click', () => {
            status.textContent = 'Memuat Reels dari Instagram…';
            const iframe = document.createElement('iframe');
            iframe.src = url.href;
            iframe.title = 'Reels Qammaris: suasana experience store';
            iframe.allow = 'fullscreen; picture-in-picture';
            iframe.referrerPolicy = 'strict-origin-when-cross-origin';
            timeout = window.setTimeout(() => { status.textContent = 'Jika video belum tampil, gunakan tautan Instagram di bawah.'; }, 15000);
            iframe.addEventListener('load', () => {
                window.clearTimeout(timeout);
                status.textContent = 'Gunakan tombol putar di video. Jika tidak tampil, buka di Instagram.';
            }, {once:true});
            iframe.addEventListener('error', () => {
                window.clearTimeout(timeout);
                status.textContent = 'Reels belum bisa dimuat. Anda tetap bisa menontonnya di Instagram.';
            }, {once:true});
            frame.replaceChildren(iframe);
            videoDialog.showModal();
            document.body.classList.add('about-video-open');
        });
        const cleanupVideo = () => {
            window.clearTimeout(timeout);
            frame.replaceChildren();
            status.textContent = '';
            document.body.classList.remove('about-video-open');
        };
        const closeVideo = () => {
            cleanupVideo();
            videoDialog.close();
            button.focus({preventScroll:true});
        };
        videoDialog.querySelector('[data-close-video]').addEventListener('click', closeVideo);
        videoDialog.addEventListener('click', (event) => { if (event.target === videoDialog) closeVideo(); });
        videoDialog.addEventListener('cancel', (event) => { event.preventDefault(); closeVideo(); });
        // A queued native close must not remove a newly reopened frame.
        videoDialog.addEventListener('close', () => { if (!videoDialog.open) cleanupVideo(); });
        window.addEventListener('pagehide', () => { if (videoDialog.open) closeVideo(); });
    }
}
