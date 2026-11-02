// CAT-TEXT-PASTE-01 (Owner 2026-11-02): a marketplace description pasted once into "Deskripsi" fills the notes,
// gender and size fields after the admin confirms, and leaves a description without seller boilerplate.
// Nothing is saved here; the admin still reviews and presses "Simpan".

const TIERS = [
    ['top', /^(?:top|head|opening)(?:\s*notes?)?\b|^aroma\s*(?:awal|pembuka)\b|^notes?\s*(?:atas|awal)\b/i],
    ['middle', /^(?:middle|heart|mid)(?:\s*notes?)?\b|^aroma\s*(?:tengah|inti)\b|^notes?\s*tengah\b/i],
    ['base', /^(?:base|bottom|dry\s*down)(?:\s*notes?)?\b|^aroma\s*(?:akhir|dasar)\b|^notes?\s*(?:dasar|akhir)\b/i],
];
const GENDER_LABEL = /^(?:gender|untuk|cocok\s*untuk|peruntukan|jenis\s*kelamin|for)\s*[:\-–]\s*(.+)$/i;
const SIZE_LABEL = /^(?:ukuran|size|isi|volume|netto|kemasan)\s*[:\-–]/i;
const HEADER_ONLY = /^(?:deskripsi|description|detail|spesifikasi|keterangan|informasi)(?:\s*produk)?\s*:?$/i;
const BOILERPLATE = [
    /^#/, /ready\s*stock/i, /100\s*%\s*ori|ori(?:ginal)?\s*100\s*%?|dijamin\s*ori|ori(?:ginal)?\s*guaranteed/i,
    /garansi|retur|refund|komplain|video\s*unboxing/i, /(?:gratis|free)\s*ongkir|\bcod\b|bayar\s*di\s*tempat/i,
    /dikirim|pengiriman|resi\b|ekspedisi|packing|bubble\s*wrap|kardus/i, /happy\s*shopping|selamat\s*berbelanja|terima\s*kasih|thank\s*you/i,
    /chat\s*(?:admin|kami|penjual|seller)|tanya\s*(?:stok|stock)|follow\s*(?:toko|kami|us)|jangan\s*lupa|klik\s*follow/i,
    /jam\s*operasional|order\s*sebelum|sebelum\s*jam/i,
];

const strip = (line) => line.replace(/^[\s\p{Extended_Pictographic}️‍•·▪●◆◇■□►▶➤>*~\-–—=+|✓✔]+/u, '').trim();
const decorationOnly = (line) => line !== '' && /^[\s\p{P}\p{S}\p{Extended_Pictographic}️‍]+$/u.test(line);

function tierOf(line) {
    for (const [tier, pattern] of TIERS) {
        const match = line.match(pattern);
        if (match) {
            const rest = line.slice(match[0].length);
            // "Top Notes: Bergamot" / "Top notes - Bergamot" / a bare "Top Notes" header followed by bullet lines.
            if (rest.trim() === '' || /^\s*[:\-–—=]/.test(rest)) return { tier, value: rest.replace(/^\s*[:\-–—=]\s*/, '') };
        }
    }

    return null;
}

function titleCase(note) {
    const upper = note === note.toUpperCase();
    const lower = note === note.toLowerCase();

    return upper || lower ? note.toLowerCase().replace(/(^|[\s'-])(\p{L})/gu, (all, edge, letter) => edge + letter.toUpperCase()) : note;
}

/** Note names from one line of a notes section. */
export function splitNotes(value) {
    return value.split(/\s*(?:,|;|\/|\||•|·|\s[-–]\s|\s&\s|\sdan\s|\sand\s)\s*/i)
        .map((note) => strip(note).replace(/[.\s]+$/, '').replace(/\s+/g, ' '))
        .filter((note) => note !== '' && note.length <= 40 && !/^(?:dll|etc)\.?$/i.test(note))
        .map(titleCase);
}

/** Pria / Wanita / Unisex from a label value or from the whole text; null when absent or contradictory. */
export function genderOf(text) {
    const unisex = /\bunisex\b/i.test(text);
    const male = /\b(?:pria|laki[-\s]?laki|cowok|men|man|male|homme)\b/i.test(text);
    const female = /\b(?:wanita|perempuan|cewek|women|woman|female|femme|ladies)\b/i.test(text);
    if (unisex || (male && female)) return 'Unisex';
    if (male) return 'Pria';
    if (female) return 'Wanita';

    return null;
}

/** One size in ml when the text mentions exactly one, otherwise null (variants like "30ml / 50ml" stay manual). */
export function sizeOf(text) {
    const sizes = new Set([...text.matchAll(/(?<![\d.,])(\d{1,4})\s*ml\b/gi)].map((match) => Number(match[1])));

    return sizes.size === 1 ? [...sizes][0] : null;
}

export function parseProductText(text) {
    const notes = { top: [], middle: [], base: [] };
    const kept = [];
    let tier = null;
    let gender = null;
    let removed = 0;
    for (const raw of String(text ?? '').replace(/\r\n?/g, '\n').split('\n')) {
        const line = strip(raw);
        const header = tierOf(line);
        if (header) {
            // A bare "Top Notes" header lists its notes on the following lines.
            tier = header.value.trim() === '' ? header.tier : null;
            notes[header.tier].push(...splitNotes(header.value));
            removed++;
            continue;
        }
        if (line === '') {
            tier = null;
            kept.push('');
            continue;
        }
        if (tier && !line.includes(':') && line.length <= 80) {
            notes[tier].push(...splitNotes(line));
            removed++;
            continue;
        }
        tier = null;
        const genderLabel = line.match(GENDER_LABEL);
        if (genderLabel && genderOf(genderLabel[1])) {
            gender = genderOf(genderLabel[1]);
            removed++;
            continue;
        }
        if (SIZE_LABEL.test(line) || HEADER_ONLY.test(line) || decorationOnly(line) || BOILERPLATE.some((pattern) => pattern.test(line))) {
            removed++;
            continue;
        }
        kept.push(raw.trim());
    }
    for (const key of Object.keys(notes)) {
        notes[key] = notes[key].filter((note, index, list) => list.findIndex((other) => other.toLowerCase() === note.toLowerCase()) === index);
    }
    const description = kept.join('\n').replace(/\n{3,}/g, '\n\n').trim();
    gender ??= genderOf(text ?? '');
    const sizeMl = sizeOf(text ?? '');

    return {
        description, notes, gender, sizeMl, removed,
        found: notes.top.length + notes.middle.length + notes.base.length > 0 || gender !== null || sizeMl !== null,
    };
}

export function bindProductTextPaste(root) {
    if (root.dataset.textPasteBound) return;
    root.dataset.textPasteBound = '1';
    const form = root.closest('form');
    const description = root.querySelector('textarea[name="description"]');
    const panel = root.querySelector('[data-text-paste-panel]');
    const found = root.querySelector('[data-text-paste-found]');
    const status = root.querySelector('[data-text-paste-status]');
    const field = (name) => form?.querySelector(`[name="${name}"]`);
    let parsed = null;
    let undo = null;

    const set = (element, value) => {
        if (!element || element.value === value) return;
        element.value = value;
        element.dispatchEvent(new Event('input', { bubbles: true }));
        element.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const row = (label, value) => {
        const item = document.createElement('div');
        item.className = 'flex gap-2';
        const term = document.createElement('dt');
        term.className = 'w-16 shrink-0 text-gray-500';
        term.textContent = label;
        const detail = document.createElement('dd');
        detail.className = 'min-w-0 break-words text-gray-900';
        detail.textContent = value;
        item.append(term, detail);

        return item;
    };
    const show = (result) => {
        parsed = result;
        const volume = field('variants[0][volume]');
        const rows = [];
        if (result.notes.top.length) rows.push(row('Top', result.notes.top.join(', ')));
        if (result.notes.middle.length) rows.push(row('Middle', result.notes.middle.join(', ')));
        if (result.notes.base.length) rows.push(row('Base', result.notes.base.join(', ')));
        if (result.gender) rows.push(row('Gender', result.gender));
        if (result.sizeMl) rows.push(row('Ukuran', volume && volume.value.trim() !== '' && !volume.readOnly ? `${result.sizeMl} ml (diabaikan, ukuran sudah diisi)` : `${result.sizeMl} ml`));
        if (result.removed) rows.push(row('Deskripsi', `${result.removed} baris notes/jualan dibuang`));
        found.replaceChildren(...rows);
        panel.hidden = false;
        status.textContent = '';
    };
    const check = (manual) => {
        const result = parseProductText(description.value);
        if (result.found) {
            show(result);
        } else if (manual) {
            panel.hidden = true;
            status.textContent = 'Tidak ditemukan notes, gender, atau ukuran di deskripsi ini.';
        }
    };

    description.addEventListener('paste', () => setTimeout(() => check(false), 0));
    root.querySelector('[data-text-paste-run]')?.addEventListener('click', () => check(true));
    root.querySelector('[data-text-paste-dismiss]')?.addEventListener('click', () => {
        panel.hidden = true;
        description.focus();
    });
    root.querySelector('[data-text-paste-apply]')?.addEventListener('click', () => {
        if (!parsed) return;
        const names = ['description', 'top_notes', 'middle_notes', 'base_notes', 'gender', 'variants[0][volume]'];
        undo = Object.fromEntries(names.map((name) => [name, field(name)?.value ?? null]));
        set(description, parsed.description);
        ['top', 'middle', 'base'].forEach((tier) => {
            if (parsed.notes[tier].length) set(field(`${tier}_notes`), parsed.notes[tier].join(', '));
        });
        if (parsed.gender) set(field('gender'), parsed.gender);
        const volume = field('variants[0][volume]');
        if (parsed.sizeMl && volume && !volume.readOnly && volume.value.trim() === '') set(volume, String(parsed.sizeMl));
        panel.hidden = true;
        status.replaceChildren('Kolom sudah diisi. Periksa lagi sebelum menyimpan. ');
        const restore = document.createElement('button');
        restore.type = 'button';
        restore.className = 'inline-flex min-h-11 items-center font-semibold underline';
        restore.textContent = 'Kembalikan teks asli';
        restore.addEventListener('click', () => {
            Object.entries(undo ?? {}).forEach(([name, value]) => { if (value !== null) set(field(name), value); });
            status.textContent = 'Teks asli dan isian sebelumnya dikembalikan.';
            description.focus();
        });
        status.append(restore);
    });
}

if (typeof document !== 'undefined') {
    const bindAll = () => document.querySelectorAll('[data-text-paste]').forEach(bindProductTextPaste);
    bindAll();
    document.addEventListener('DOMContentLoaded', bindAll);
}
