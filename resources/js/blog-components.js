import { Node } from '@tiptap/core';

const definitions = {
    media: { attribute: 'mediaId', label: 'Gambar media artikel' },
    gallery: { attribute: 'mediaIds', label: 'Galeri artikel' },
    article: { attribute: 'articleId', label: 'Artikel terkait' },
    youtube: { attribute: 'videoId', label: 'Video YouTube' },
    callout: { attribute: 'kind', label: 'Callout' },
    cta: { attribute: 'url', label: 'Tombol CTA' },
};

export const journalNodes = Object.entries(definitions).map(([kind, definition]) => Node.create({
    name: `journal${kind}`, group: 'block', atom: true,
    addAttributes() {
        const attributes = { [definition.attribute]: { default: '', parseHTML: el => el.getAttribute(`data-qammaris-${kind}`) } };
        for (const field of kind === 'callout' ? ['title', 'text'] : kind === 'cta' ? ['label'] : []) {
            attributes[field] = { default: '', parseHTML: el => el.getAttribute(`data-${field}`) };
        }
        return attributes;
    },
    parseHTML() { return [{ tag: `div[data-qammaris-${kind}]` }]; },
    renderHTML({ node }) {
        const attrs = { [`data-qammaris-${kind}`]: node.attrs[definition.attribute] };
        for (const field of kind === 'callout' ? ['title', 'text'] : kind === 'cta' ? ['label'] : []) attrs[`data-${field}`] = node.attrs[field];
        return ['div', attrs, `${definition.label}: ${node.attrs.title || node.attrs.label || node.attrs[definition.attribute]}`];
    },
}));

export function installJournalInserts(form, editor, feedback, getMode) {
    form.querySelectorAll('[data-block-insert]').forEach(button => button.addEventListener('click', () => {
        if (getMode() !== 'visual') { feedback.textContent = 'Pilih mode Visual untuk menambahkan komponen.'; return; }
        const kind = button.dataset.blockInsert;
        const value = id => form.querySelector(`#editor-${id}`)?.value.trim() || '';
        let attrs;
        if (kind === 'media') attrs = { mediaId: value('media') };
        else if (kind === 'article') attrs = { articleId: value('article') };
        else if (kind === 'gallery') {
            const ids = [...form.querySelectorAll('[data-repeat="gallery"] [data-repeat-row] select')].map(select => select.value);
            if (ids.length < 2 || ids.length > 8 || ids.some(id => !id) || new Set(ids).size !== ids.length) { feedback.textContent = 'Pilih 2–8 gambar berbeda untuk galeri.'; return; }
            attrs = { mediaIds: ids.join(',') };
        } else if (kind === 'callout') attrs = { kind: value('callout-type'), title: value('callout-title'), text: value('callout-text') };
        else if (kind === 'cta') {
            const url = value('cta-url');
            if (!/^(https:\/\/|\/(?!\/))/.test(url)) { feedback.textContent = 'CTA membutuhkan URL HTTPS atau path website.'; return; }
            attrs = { url, label: value('cta-label') };
        } else if (kind === 'youtube') {
            let videoId = value('youtube');
            if (!/^[\w-]{11}$/.test(videoId)) {
                try {
                    const url = new URL(videoId);
                    if (url.protocol !== 'https:' || !['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'].includes(url.hostname)) throw new Error();
                    videoId = url.hostname === 'youtu.be' ? url.pathname.slice(1) : (url.searchParams.get('v') || url.pathname.split('/').pop());
                } catch { videoId = ''; }
            }
            if (!/^[\w-]{11}$/.test(videoId)) { feedback.textContent = 'Masukkan ID 11 karakter atau URL video YouTube yang valid.'; return; }
            attrs = { videoId };
        }
        if (!attrs || Object.values(attrs).some(item => !item)) { feedback.textContent = 'Lengkapi pilihan dan teks komponen dahulu.'; return; }
        editor.chain().focus().insertContent({type: `journal${kind}`, attrs}).run();
        feedback.textContent = 'Komponen ditambahkan. Periksa tampilan melalui Preview sebelum menyimpan.';
    }));
}

document.querySelectorAll('[data-repeat]').forEach(list => {
    const container = list.querySelector('[data-repeat-rows]');
    const add = list.querySelector('[data-repeat-add]');
    const status = document.querySelector('[data-repeat-status]');
    const refresh = () => {
        const rows = [...container.children];
        rows.forEach((row, index) => {
            row.querySelectorAll('[data-field]').forEach(input => {
                if (list.dataset.repeat !== 'gallery') input.name = `${list.dataset.repeat}[${index}]${input.dataset.field ? `[${input.dataset.field}]` : ''}`;
            });
            row.querySelector('[data-repeat-up]').disabled = index === 0;
            row.querySelector('[data-repeat-down]').disabled = index === rows.length - 1;
        });
        add.disabled = rows.length >= Number(list.dataset.limit);
    };
    add.addEventListener('click', () => {
        if (add.disabled) return;
        container.append(list.querySelector('template').content.cloneNode(true));
        refresh();
        container.lastElementChild.querySelector('input,textarea,select')?.focus();
        if (status) status.textContent = 'Baris ditambahkan. Lengkapi isian sebelum menyimpan.';
    });
    container.addEventListener('click', event => {
        const button = event.target.closest('button');
        const row = button?.closest('[data-repeat-row]');
        if (!row) return;
        if (button.hasAttribute('data-repeat-remove')) { row.remove(); add.focus(); }
        if (button.hasAttribute('data-repeat-up') && row.previousElementSibling) container.insertBefore(row, row.previousElementSibling);
        if (button.hasAttribute('data-repeat-down') && row.nextElementSibling) container.insertBefore(row.nextElementSibling, row);
        refresh();
        if (status) status.textContent = 'Daftar diperbarui; perubahan disimpan bersama artikel.';
    });
    refresh();
});

document.querySelectorAll('[data-media-form]').forEach(form => {
    const preview = form.querySelector('[data-crop-preview]');
    const image = form.querySelector('[data-crop-image]');
    const crop = form.querySelector('[data-crop-select]');
    const x = form.querySelector('[data-crop-x]');
    const y = form.querySelector('[data-crop-y]');
    let objectUrl;
    const redraw = () => {
        if (!image.naturalWidth || !image.naturalHeight) return;
        const width = image.naturalWidth, height = image.naturalHeight;
        const ratio = crop.value === 'original' ? width / height : crop.value.split(':').map(Number).reduce((a, b) => a / b);
        const sw = crop.value === 'original' ? width : Math.max(1, Math.min(width, Math.floor(height * ratio)));
        const sh = crop.value === 'original' ? height : Math.max(1, Math.min(height, Math.floor(width / ratio)));
        const sx = Math.max(0, Math.min(width - sw, Math.round(width * Number(x.value) / 100 - sw / 2)));
        const sy = Math.max(0, Math.min(height - sh, Math.round(height * Number(y.value) / 100 - sh / 2)));
        preview.style.aspectRatio = `${sw}/${sh}`;
        const scale = preview.clientWidth / sw;
        Object.assign(image.style, {width: `${width * scale}px`, height: `${height * scale}px`, left: `${-sx * scale}px`, top: `${-sy * scale}px`});
    };
    image.addEventListener('load', redraw);
    [crop, x, y].forEach(control => control.addEventListener('input', redraw));
    new ResizeObserver(redraw).observe(preview);
    form.querySelector('[data-crop-file]')?.addEventListener('change', event => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        const file = event.target.files[0];
        if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) { image.hidden = true; preview.hidden = true; return; }
        objectUrl = URL.createObjectURL(file);
        image.src = objectUrl;
        image.hidden = false;
        preview.hidden = false;
    });
    form.addEventListener('submit', event => {
        if (!event.submitter) return;
        event.submitter.disabled = true;
        form.querySelector('[data-media-status]').textContent = 'Menyimpan dan memproses media…';
        window.setTimeout(() => { event.submitter.disabled = false; form.querySelector('[data-media-status]').textContent = 'Belum mendapat respons. Periksa koneksi dan coba lagi.'; }, 15000);
    });
    window.addEventListener('pageshow', () => { form.querySelectorAll('button[type=submit]').forEach(button => { button.disabled = false; }); redraw(); });
    window.addEventListener('pagehide', () => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
    redraw();
});
