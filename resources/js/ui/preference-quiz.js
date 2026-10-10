const draftKey = 'qammaris:preference:v1';
const form = document.querySelector('[data-preference-form]');
if (form) {
    const seed = JSON.parse(document.getElementById('preference-seed').textContent);
    const panels = [...form.querySelectorAll('[data-preference-step]')];
    const summary = form.querySelector('[data-preference-summary]');
    const error = form.querySelector('[data-preference-error]');
    let step = 0;
    let none = { likes: false, avoid: false };
    const favoriteInput = form.elements.favorite_product_id;
    const budget = form.elements.budget_max;
    const free = form.querySelector('[data-budget-free]');
    const fieldValue = (key) => form.querySelector(`input[name="${key}"]:checked`)?.value;
    const answers = () => ({
        budget_max: free.checked ? null : (budget.value ? Number(budget.value) : null),
        use: fieldValue('use'), environment: fieldValue('environment'),
        likes: [...form.querySelectorAll('input[name="likes"]:checked')].map(e => e.value),
        avoid: [...form.querySelectorAll('input[name="avoid"]:checked')].map(e => e.value),
        sweetness: fieldValue('sweetness'), projection: fieldValue('projection'), longevity: fieldValue('longevity'),
        gender: fieldValue('gender') || 'all', favorite_product_id: favoriteInput.value ? Number(favoriteInput.value) : null,
    });
    function save() {
        try { localStorage.setItem(draftKey, JSON.stringify({ timestamp: Date.now(), answers: answers(), step, none, free: free.checked })); }
        catch { form.querySelector('[data-draft-status]').textContent = 'Browser tidak dapat menyimpan jawaban sementara. Jangan tutup tab sebelum selesai.'; }
    }
    function restore(data, savedNone) {
        budget.value = data.budget_max ?? '';
        free.checked = data.budget_max === null;
        budget.disabled = free.checked;
        favoriteInput.value = data.favorite_product_id ?? '';
        for (const [key, value] of Object.entries(data)) {
            if (['budget_max', 'favorite_product_id'].includes(key)) continue;
            form.querySelectorAll(`input[name="${key}"]`).forEach(e => { e.checked = Array.isArray(value) ? value.includes(e.value) : e.value === value; });
        }
        for (const key of ['likes', 'avoid']) {
            none[key] = savedNone?.[key] ?? (Array.isArray(data[key]) && !data[key].length);
            form.querySelector(`[data-none="${key}"]`).checked = none[key];
        }
        selectedFavorite();
    }
    function selectedFavorite() {
        const selected = seed.favorites.find(p => p.id === Number(favoriteInput.value));
        form.querySelector('[data-favorite-selected]').textContent = selected ? `Dipilih: ${selected.label}` : 'Belum ada parfum dipilih.';
        if (!selected) favoriteInput.value = '';
    }
    function valid(index) {
        const key = panels[index]?.dataset.key;
        const data = answers();
        if (key === 'budget_max' && !free.checked && (!Number.isInteger(data.budget_max) || data.budget_max < 1 || data.budget_max > 99999999)) return 'Isi budget rupiah bulat yang valid atau pilih budget bebas.';
        if (key === 'likes' && ((!data.likes.length && !none.likes) || data.likes.length > 3)) return 'Pilih satu sampai tiga aroma atau belum tahu.';
        if (key === 'avoid' && !data.avoid.length && !none.avoid) return 'Pilih aroma yang dihindari atau tidak ada / belum tahu.';
        if (key === 'avoid' && data.avoid.some(v => data.likes.includes(v))) return 'Ada aroma yang sekaligus disukai dan dihindari. Ubah salah satunya.';
        if (panels[index]?.dataset.type === 'radio' && !['gender'].includes(key) && !data[key]) return 'Pilih satu jawaban sebelum lanjut.';
        return null;
    }
    function message(text) { error.textContent = text || ''; error.hidden = !text; }
    function summaryRows() {
        const target = form.querySelector('[data-summary-rows]'); target.replaceChildren();
        const data = answers();
        panels.forEach((panel, index) => {
            const key = panel.dataset.key;
            const line = document.createElement('div'); line.className = 'py-4 flex items-start justify-between gap-3';
            const info = document.createElement('div');
            const label = document.createElement('p'); label.className = 'text-sm text-brand-black/60'; label.textContent = panel.querySelector('legend').textContent;
            const value = document.createElement('p');
            if (key === 'budget_max') value.textContent = data[key] === null ? 'Bebas' : `Rp ${data[key].toLocaleString('id-ID')}`;
            else if (key === 'favorite_product_id') value.textContent = seed.favorites.find(p => p.id === data[key])?.label || 'Dilewati';
            else if (Array.isArray(data[key])) value.textContent = data[key].length ? [...panel.querySelectorAll('input:checked')].map(e => e.closest('label').textContent.trim()).join(', ') : 'Tidak ada / belum tahu';
            else value.textContent = [...panel.querySelectorAll('input:checked')].map(e => e.closest('label').textContent.trim()).join(', ') || 'Bebas / dilewati';
            const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'pref-link min-h-11 shrink-0'; edit.textContent = 'Ubah'; edit.setAttribute('aria-label', `Ubah ${label.textContent}`); edit.addEventListener('click', () => show(index));
            info.append(label, value); line.append(info, edit); target.append(line);
        });
    }
    function show(index, focus = true) {
        step = Math.max(0, Math.min(index, panels.length));
        panels.forEach((panel, i) => { panel.hidden = i !== step; panel.inert = i !== step; });
        summary.hidden = step !== panels.length;
        form.querySelector('[data-step-label]').textContent = step === panels.length ? 'Ringkasan jawaban' : `Pertanyaan ${step + 1} dari ${panels.length}${step >= 8 ? ' · opsional' : ''}`;
        form.querySelector('[data-progress]').value = step + 1;
        form.querySelector('[data-preference-back]').hidden = step === 0;
        form.querySelector('[data-preference-next]').hidden = step === panels.length;
        form.querySelector('[data-preference-next]').textContent = step >= 8 ? 'Lanjut / lewati' : 'Lanjut';
        form.querySelector('[data-preference-submit]').hidden = step !== panels.length;
        if (step === panels.length) summaryRows();
        message(null); save();
        if (focus) (step === panels.length ? summary.querySelector('h2') : panels[step].querySelector('legend')).focus({ preventScroll: true });
    }
    form.querySelectorAll('[data-budget]').forEach(button => button.addEventListener('click', () => { free.checked = false; budget.disabled = false; budget.value = button.dataset.budget; save(); }));
    free.addEventListener('change', () => { budget.disabled = free.checked; save(); });
    form.addEventListener('change', event => {
        if (event.target.dataset.none) {
            const key = event.target.dataset.none; none[key] = event.target.checked;
            if (none[key]) form.querySelectorAll(`input[name="${key}"]`).forEach(e => e.checked = false);
        } else if (['likes', 'avoid'].includes(event.target.name)) {
            const key = event.target.name; none[key] = false; form.querySelector(`[data-none="${key}"]`).checked = false;
            if (key === 'likes' && answers().likes.length > 3) { event.target.checked = false; message('Pilih maksimal tiga aroma.'); }
        }
        save();
    });
    form.addEventListener('input', save);
    form.querySelector('[data-favorite-clear]').addEventListener('click', () => { favoriteInput.value = ''; selectedFavorite(); save(); });
    form.querySelector('[data-favorite-search]').addEventListener('input', event => {
        const target = form.querySelector('[data-favorite-options]'); target.replaceChildren();
        const query = event.target.value.trim().toLocaleLowerCase('id-ID');
        if (query.length < 2) return;
        const matches = seed.favorites.filter(p => p.label.toLocaleLowerCase('id-ID').includes(query)).slice(0, 12);
        matches.forEach(product => {
            const button = document.createElement('button'); button.type = 'button'; button.className = 'pref-option block w-full text-left mb-2'; button.textContent = product.label;
            button.addEventListener('click', () => { favoriteInput.value = product.id; selectedFavorite(); target.replaceChildren(); save(); }); target.append(button);
        });
        if (!matches.length) { const note = document.createElement('p'); note.textContent = 'Tidak ditemukan. Coba nama lain atau lewati.'; target.append(note); }
    });
    form.querySelector('[data-preference-next]').addEventListener('click', () => { const problem = valid(step); if (problem) message(problem); else show(step + 1); });
    form.querySelector('[data-preference-back]').addEventListener('click', () => show(step - 1));
    form.addEventListener('submit', async event => {
        event.preventDefault();
        for (let i = 0; i < panels.length; i++) { const problem = valid(i); if (problem) { show(i); message(problem); return; } }
        const submit = form.querySelector('[data-preference-submit]'); submit.disabled = true; submit.textContent = 'Mencari rekomendasi…'; form.setAttribute('aria-busy', 'true'); save();
        try {
            const response = await fetch(form.action, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.elements._token.value }, body: JSON.stringify(answers()) });
            if (response.ok && response.redirected) { window.location.assign(response.url); return; }
            if (response.status === 422) { const data = await response.json(); message(Object.values(data.errors || {}).flat().join(' ') || 'Periksa jawabanmu.'); }
            else message(response.status === 429 ? 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.' : response.status === 419 ? 'Sesi berubah. Refresh halaman; jawaban sementara tetap tersimpan.' : 'Hasil belum berhasil disimpan. Coba lagi, jawabanmu tetap tersedia.');
        } catch { message('Koneksi terputus. Coba lagi, jawabanmu tetap tersedia.'); }
        finally { submit.disabled = false; submit.textContent = 'Lihat rekomendasi'; form.removeAttribute('aria-busy'); }
    });
    if (Object.keys(seed.answers).length) restore(seed.answers);
    else { try { const draft = JSON.parse(localStorage.getItem(draftKey)); if (draft && Date.now() - draft.timestamp < 86400000 && Date.now() >= draft.timestamp) { restore(draft.answers, draft.none); free.checked = draft.free === true; budget.disabled = free.checked; step = Number.isInteger(draft.step) ? draft.step : 0; } } catch { /* Storage is optional. */ } }
    show(step, false);
}
const feedback = document.querySelector('[data-preference-feedback]');
if (feedback) feedback.addEventListener('submit', async event => {
    event.preventDefault();
    const status = feedback.querySelector('[data-feedback-status]');
    const overall = feedback.querySelector('input[name="overall"]:checked')?.value;
    if (!overall) { status.textContent = 'Pilih penilaian keseluruhan dulu.'; return; }
    const products = [...feedback.querySelectorAll('[data-feedback-product]')].filter(panel => panel.querySelector('[data-rating]').value).map(panel => ({ product_id: Number(panel.dataset.feedbackProduct), rating: panel.querySelector('[data-rating]').value, reasons: [...panel.querySelectorAll('[data-reason]:checked')].map(e => e.value) }));
    const button = feedback.querySelector('button[type="submit"]'); button.disabled = true; status.textContent = 'Menyimpan feedback…';
    try {
        const response = await fetch(feedback.action, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': feedback.elements._token.value }, body: JSON.stringify({ overall, products }) });
        status.textContent = response.ok ? (await response.json()).message : response.status === 429 ? 'Tunggu sebentar lalu coba lagi.' : response.status === 410 ? 'Hasil sudah kedaluwarsa. Mulai tes baru.' : 'Feedback belum tersimpan. Periksa pilihanmu lalu coba lagi.';
    } catch { status.textContent = 'Koneksi terputus. Feedback belum dikonfirmasi tersimpan; coba lagi.'; }
    finally { button.disabled = false; }
});
document.querySelector('[data-preference-recalculate]')?.addEventListener('submit', event => { const button = event.target.querySelector('button'); button.disabled = true; button.textContent = 'Menghitung ulang…'; });
