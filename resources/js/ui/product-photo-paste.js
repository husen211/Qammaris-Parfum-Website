// CAT-MEDIA-PASTE-01 (Owner 2026-11-02): product photos can be pasted with Ctrl+V, dropped or picked.
// Photos are tidied in the browser and written back into the form's own file input, so "Simpan"
// still uploads them through the unchanged server validation (JPEG/PNG/WebP, 2 MB, max 3).

export const MAX_BYTES = 2 * 1024 * 1024;
export const MAX_SIDE = 2000;
const KEEP_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const QUALITIES = [0.9, 0.82, 0.74, 0.66, 0.58, 0.5];

/** Image files carried by a paste or drop, in their original order. */
export function imageFiles(transfer) {
    if (!transfer) return [];
    const files = Array.from(transfer.files ?? []).filter((file) => file.type.startsWith('image/'));
    if (files.length) return files;

    return Array.from(transfer.items ?? [])
        .filter((item) => item.kind === 'file' && item.type.startsWith('image/'))
        .map((item) => item.getAsFile())
        .filter(Boolean);
}

/** A copied link or page fragment instead of the picture itself ("Copy image address"). */
export function copiedLinkOnly(transfer) {
    if (!transfer || imageFiles(transfer).length) return false;
    const types = Array.from(transfer.types ?? []);

    return types.includes('text/uri-list') || types.includes('text/html')
        || /^https?:\/\/\S+$/i.test((transfer.getData?.('text/plain') ?? '').trim());
}

/** Scale down so the longest side is at most `max`; never scale up. */
export function fitWithin(width, height, max = MAX_SIDE) {
    const scale = Math.min(1, max / Math.max(width, height));

    return { width: Math.max(1, Math.round(width * scale)), height: Math.max(1, Math.round(height * scale)) };
}

/** A file the server accepts as it is: no need to re-encode it. */
export function keepsOriginal(file, width, height) {
    return KEEP_TYPES.includes(file.type) && file.size <= MAX_BYTES && Math.max(width, height) <= MAX_SIDE;
}

/**
 * Encode WebP (JPEG when the browser cannot write WebP), lowering quality until it fits `limit`.
 * `encode(type, quality)` resolves to a Blob or null, like canvas.toBlob. Resolves null when nothing fits,
 * or at once when a result is more than twice the limit: shrinking the photo is then faster than more quality steps.
 */
export async function encodeUnderLimit(encode, limit = MAX_BYTES) {
    let type = 'image/webp';
    for (const quality of QUALITIES) {
        let blob = await encode(type, quality);
        if (blob && type === 'image/webp' && blob.type !== 'image/webp') {
            type = 'image/jpeg';
            blob = await encode(type, quality);
        }
        if (!blob) continue;
        if (blob.size <= limit) return blob;
        if (blob.size > limit * 2) return null;
    }

    return null;
}

export function photoName(index, type) {
    return `foto-tempel-${index}.${type === 'image/jpeg' ? 'jpg' : 'webp'}`;
}

// createImageBitmap does not wait for the page to paint (Image.decode can) and applies the photo's EXIF rotation.
async function decode(file) {
    if (typeof createImageBitmap === 'function') {
        return createImageBitmap(file, { imageOrientation: 'from-image' });
    }
    const url = URL.createObjectURL(file);
    try {
        const image = new Image();
        image.src = url;
        await image.decode();

        return image;
    } finally {
        URL.revokeObjectURL(url);
    }
}

/** Resize/re-encode only when needed; transparent areas become white like the catalog background. */
export async function tidy(file, index) {
    const image = await decode(file);
    const width = image.naturalWidth || image.width;
    const height = image.naturalHeight || image.height;
    if (keepsOriginal(file, width, height)) {
        image.close?.();
        return file;
    }
    let size = fitWithin(width, height);
    for (let attempt = 0; attempt < 5; attempt++) {
        const canvas = document.createElement('canvas');
        canvas.width = size.width;
        canvas.height = size.height;
        const context = canvas.getContext('2d');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, size.width, size.height);
        context.drawImage(image, 0, 0, size.width, size.height);
        const blob = await encodeUnderLimit((type, quality) => new Promise((resolve) => canvas.toBlob(resolve, type, quality)));
        if (blob) {
            image.close?.();
            return new File([blob], photoName(index, blob.type), { type: blob.type });
        }
        size = fitWithin(size.width, size.height, Math.round(Math.max(size.width, size.height) * 0.7));
    }
    image.close?.();
    throw new Error('too_large');
}

// CAT-MEDIA-PASTE-02: a browser cannot refill a file input after the server sends the form back with an error,
// so pasted photos wait in this browser's IndexedDB while the form is submitted and come back on that page.
export const KEEP_FOR_MS = 60 * 60 * 1000;

export function photoStoreKey(action, base = 'http://localhost') {
    return `photos:${new URL(action, base).pathname}`;
}

export function isFresh(savedAt, now = Date.now()) {
    return Number.isFinite(savedAt) && now - savedAt >= 0 && now - savedAt < KEEP_FOR_MS;
}

function photoStore(mode, run) {
    return new Promise((resolve, reject) => {
        const open = indexedDB.open('qammaris-admin-photos', 1);
        open.onupgradeneeded = () => open.result.createObjectStore('pending');
        open.onerror = () => reject(open.error);
        open.onsuccess = () => {
            const db = open.result;
            const transaction = db.transaction('pending', mode);
            const request = run(transaction.objectStore('pending'));
            transaction.oncomplete = () => { db.close(); resolve(request?.result); };
            transaction.onerror = transaction.onabort = () => { db.close(); reject(transaction.error); };
        };
    });
}

const keepPhotos = (key, files) => photoStore('readwrite', (store) => store.put({ savedAt: Date.now(), files }, key));
const takePhotos = (key) => photoStore('readonly', (store) => store.get(key));
const forgetPhotos = (key) => photoStore('readwrite', (store) => store.delete(key));
const forgetStalePhotos = () => photoStore('readwrite', (store) => {
    const cursor = store.openCursor();
    cursor.onsuccess = () => {
        if (!cursor.result) return;
        if (!isFresh(cursor.result.value?.savedAt)) cursor.result.delete();
        cursor.result.continue();
    };
    return cursor;
});

export function bindPhotoPaste(root) {
    if (root.dataset.photoPasteBound) return;
    root.dataset.photoPasteBound = '1';
    const input = root.querySelector('[data-photo-input]');
    const dropzone = root.querySelector('[data-photo-dropzone]');
    const previews = root.querySelector('[data-photo-previews]');
    const status = root.querySelector('[data-photo-status]');
    const slots = root.querySelector('[data-photo-slots]');
    const capacity = Number(root.dataset.maxNew || 0);
    const existing = Number(root.dataset.existing || 0);
    const pending = [];
    let counter = 0;
    let submitting = false;

    const say = (message, tone = 'info') => {
        status.textContent = message;
        status.classList.toggle('text-red-700', tone === 'error');
        status.classList.toggle('text-gray-600', tone !== 'error');
    };
    const sync = () => {
        const transfer = new DataTransfer();
        pending.forEach(({ file }) => transfer.items.add(file));
        input.files = transfer.files;
        slots.textContent = String(capacity - pending.length);
        previews.replaceChildren(...pending.map(({ file, url }, index) => {
            const item = document.createElement('li');
            item.className = 'min-w-0';
            const frame = document.createElement('div');
            frame.className = 'relative aspect-square overflow-hidden rounded-lg border border-gray-200 bg-white';
            const image = document.createElement('img');
            image.src = url;
            image.alt = `Foto baru ${index + 1}`;
            image.className = 'h-full w-full object-contain';
            const label = document.createElement('p');
            label.className = 'mt-1 text-[11px] leading-tight text-amber-800';
            label.textContent = existing === 0 && index === 0 ? 'Foto utama, belum disimpan' : 'Belum disimpan';
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'absolute right-1 top-1 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/95 text-base font-bold text-gray-800 shadow-sm hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-1';
            remove.textContent = '×';
            remove.setAttribute('aria-label', `Hapus foto baru ${index + 1}`);
            remove.addEventListener('click', () => {
                URL.revokeObjectURL(pending[index].url);
                pending.splice(index, 1);
                sync();
                say(`Foto baru ${index + 1} dihapus.`);
                dropzone.focus();
            });
            frame.append(image, remove);
            item.append(frame, label);

            return item;
        }));
    };
    const add = async (files) => {
        const room = capacity - pending.length;
        if (room <= 0) {
            say(`Galeri penuh: maksimal 3 foto per produk. Hapus salah satu foto dulu.`, 'error');
            return;
        }
        const chosen = files.slice(0, room);
        say('Merapikan foto…');
        let added = 0;
        let unreadable = 0;
        let tooLarge = 0;
        for (const file of chosen) {
            try {
                const tidied = await tidy(file, ++counter);
                pending.push({ file: tidied, url: URL.createObjectURL(tidied) });
                added++;
            } catch (error) {
                if (error?.message === 'too_large') tooLarge++;
                else unreadable++;
            }
        }
        sync();
        const notes = [];
        if (added) notes.push(`${added} foto ditambahkan. Tekan Simpan untuk mengunggah.`);
        if (unreadable) notes.push(`${unreadable} foto tidak bisa dibaca; coba simpan dulu sebagai JPG/PNG.`);
        if (tooLarge) notes.push(`${tooLarge} foto tetap di atas 2 MB setelah dirapikan; pakai foto lain.`);
        if (files.length > chosen.length) notes.push(`Hanya ${chosen.length} foto yang muat (maksimal 3 per produk).`);
        say(notes.join(' '), added ? 'info' : 'error');
    };

    input.addEventListener('change', () => {
        const picked = Array.from(input.files);
        sync();
        if (picked.length) add(picked);
    });
    document.addEventListener('paste', (event) => {
        const files = imageFiles(event.clipboardData);
        if (files.length) {
            event.preventDefault();
            add(files);
            return;
        }
        const target = event.target;
        const typing = target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
        if (!typing && copiedLinkOnly(event.clipboardData)) {
            say('Yang ter-copy alamat gambar, bukan gambarnya. Klik kanan foto → Copy image, lalu tempel lagi.', 'error');
        }
    });
    dropzone.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropzone.dataset.dragging = '1';
    });
    dropzone.addEventListener('dragleave', () => delete dropzone.dataset.dragging);
    dropzone.addEventListener('drop', (event) => {
        event.preventDefault();
        delete dropzone.dataset.dragging;
        const files = imageFiles(event.dataTransfer);
        if (files.length) add(files);
        else say('Yang diseret bukan file foto.', 'error');
    });
    const form = input.form;
    const storeKey = form ? photoStoreKey(form.getAttribute('action') || location.href, location.href) : null;
    let resubmitting = false;
    form?.addEventListener('submit', async (event) => {
        // Another check (e.g. publish requirements) already stopped this submit.
        if (event.defaultPrevented) return;
        if (resubmitting || !pending.length || typeof indexedDB === 'undefined') {
            submitting = true;
            if (!resubmitting && storeKey && typeof indexedDB !== 'undefined') forgetPhotos(storeKey).catch(() => {});
            return;
        }
        event.preventDefault();
        try {
            await keepPhotos(storeKey, pending.map(({ file }) => file));
        } catch {
            // Storage blocked or full: the upload still goes ahead, only the safety copy is missing.
        }
        resubmitting = true;
        submitting = true;
        form.requestSubmit(event.submitter ?? undefined);
    });
    if (storeKey && typeof indexedDB !== 'undefined') {
        // The page came back with validation errors: put the photos from the failed submit back in the box.
        const restore = root.hasAttribute('data-restore')
            ? takePhotos(storeKey).then((saved) => {
                if (!saved || !isFresh(saved.savedAt) || !Array.isArray(saved.files)) return;
                const files = saved.files.filter((file) => file instanceof Blob).slice(0, capacity - pending.length);
                files.forEach((file) => {
                    pending.push({ file, url: URL.createObjectURL(file) });
                    counter++;
                });
                if (files.length) {
                    sync();
                    say(`${files.length} foto dikembalikan, belum disimpan. Lengkapi kolom yang ditandai, lalu simpan lagi.`);
                }
            })
            : Promise.resolve();
        restore.catch(() => {}).finally(() => {
            forgetPhotos(storeKey).catch(() => {});
            forgetStalePhotos().catch(() => {});
        });
    }
    window.addEventListener('beforeunload', (event) => {
        if (pending.length && !submitting) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}

if (typeof document !== 'undefined') {
    const bindAll = () => document.querySelectorAll('[data-photo-paste]').forEach(bindPhotoPaste);
    bindAll();
    document.addEventListener('DOMContentLoaded', bindAll);
}
