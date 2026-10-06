const gallery = document.querySelector('[data-product-gallery]');

if (gallery) {
    const image = gallery.querySelector('#mainImage');
    const frame = gallery.querySelector('[data-gallery-frame]');
    const thumbnails = [...gallery.querySelectorAll('[data-gallery-thumbnail]')];
    const steps = [...gallery.querySelectorAll('[data-gallery-step]')];
    const position = gallery.querySelector('[data-gallery-position]');
    const feedback = gallery.querySelector('[data-gallery-feedback]');
    const retry = gallery.querySelector('[data-gallery-retry]');
    let selected = 0;
    let gesture = null;

    const showPhoto = (index) => {
        if (!image || index < 0 || index >= thumbnails.length) return;
        selected = index;
        image.classList.add('opacity-40');
        feedback.textContent = 'Memuat foto…';
        retry.hidden = true;
        image.src = thumbnails[index].dataset.gallerySrc;
        image.alt = thumbnails[index].dataset.galleryAlt;

        thumbnails.forEach((thumbnail, i) => {
            thumbnail.setAttribute('aria-pressed', String(i === index));
            thumbnail.classList.toggle('border-brand-black', i === index);
            thumbnail.classList.toggle('border-gray-200', i !== index);
        });
        steps.forEach((button) => {
            button.disabled = Number(button.dataset.galleryStep) < 0
                ? index === 0 : index === thumbnails.length - 1;
        });
        if (position) position.textContent = `${index + 1} / ${thumbnails.length}`;
    };

    image?.addEventListener('load', () => {
        image.classList.remove('opacity-40');
        feedback.textContent = '';
        retry.hidden = true;
    });
    image?.addEventListener('error', () => {
        image.classList.remove('opacity-40');
        feedback.textContent = 'Foto gagal dimuat.';
        retry.hidden = false;
    });
    retry?.addEventListener('click', () => {
        if (thumbnails.length) showPhoto(selected);
        else if (image) image.src = image.src;
    });
    thumbnails.forEach((thumbnail, i) => thumbnail.addEventListener('click', () => showPhoto(i)));
    steps.forEach((button) => button.addEventListener('click', () => {
        showPhoto(selected + Number(button.dataset.galleryStep));
    }));

    frame?.addEventListener('pointerdown', (event) => {
        if (!event.isPrimary || event.button !== 0 || thumbnails.length < 2
            || !window.matchMedia('(max-width: 1023px)').matches) return;
        gesture = { id: event.pointerId, x: event.clientX, y: event.clientY };
        frame.setPointerCapture(event.pointerId);
    });
    frame?.addEventListener('pointercancel', () => { gesture = null; });
    frame?.addEventListener('pointerup', (event) => {
        if (!gesture || gesture.id !== event.pointerId) return;
        const dx = event.clientX - gesture.x;
        const dy = event.clientY - gesture.y;
        gesture = null;
        if (Math.abs(dx) >= 45 && Math.abs(dx) > Math.abs(dy)) {
            showPhoto(selected + (dx < 0 ? 1 : -1));
        }
    });
}
