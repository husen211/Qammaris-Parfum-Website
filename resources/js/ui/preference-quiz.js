import { PREFERENCE_STAGES, preferenceStage, stageDestination, transitionPreferencePanel } from './preference-multistep.js';
import { activeQuestionKeys, effectiveAnswers, sweetnessBranch, validBudget } from './preference-flow.js';

const form = document.querySelector('[data-preference-form]');
if (form) {
    const seed = JSON.parse(document.getElementById('preference-seed').textContent);
    const draftKey = `qammaris:preference:${seed.questionVersion}`;
    const panels = [...form.querySelectorAll('[data-preference-step]')];
    const keys = panels.map(panel => panel.dataset.key);
    const summary = form.querySelector('[data-preference-summary]');
    const error = form.querySelector('[data-preference-error]');
    const favoriteInput = form.elements.favorite_product_id;
    const budget = form.elements.budget_max;
    const free = form.querySelector('[data-budget-free]');
    const avoidSweet = form.querySelector('[data-avoid-sweet]');
    const frame = form.querySelector('[data-step-frame]');
    const nextButton = form.querySelector('[data-preference-next]');
    const backButton = form.querySelector('[data-preference-back]');
    const submitButton = form.querySelector('[data-preference-submit]');
    const stageButtons = [...form.querySelectorAll('[data-preference-stage]')];
    let transitioning = false;
    let current = keys[0];
    let none = { likes: false, avoid: false };
    let minimum = 0;
    let openRange = false;
    const fieldValue = key => form.querySelector(`input[name="${key}"]:checked`)?.value;
    const answers = () => effectiveAnswers({
        budget_min: free.checked ? 0 : minimum,
        budget_max: free.checked || openRange ? null : (budget.value ? Number(budget.value) : null),
        use: fieldValue('use'), environment: fieldValue('environment'), time: fieldValue('time'),
        likes: [...form.querySelectorAll('input[name="likes"]:checked')].map(e => e.value),
        avoid: [...form.querySelectorAll('input[name="avoid"]:checked')].map(e => e.value),
        avoid_sweet: avoidSweet.checked,
        sweetness: fieldValue('sweetness'), projection: fieldValue('projection'), longevity: fieldValue('longevity'),
        gender: fieldValue('gender') || 'all', favorite_product_id: favoriteInput.value ? Number(favoriteInput.value) : null,
    });
    const active = () => activeQuestionKeys(keys, answers());
    function message(text) { error.textContent = text || ''; error.hidden = !text; }
    function budgetState() {
        budget.disabled = free.checked || openRange;
        budget.placeholder = free.checked ? 'Budget tidak dibatasi' : openRange ? 'Rp 1.000.000 ke atas' : 'Contoh: 350000';
        form.querySelector('[data-budget-status]').textContent = free.checked ? 'Budget tidak dibatasi. Kolom angka tidak perlu diisi.' : openRange ? 'Rentang Rp 1.000.000 ke atas dipilih. Untuk batas maksimal tertentu, pilih Isi angka sendiri.' : minimum > 0 ? 'Rentang harga dipilih. Mengubah angka akan memakai batas maksimal sendiri.' : 'Kamu bisa mengisi batas maksimal sendiri.';
        form.querySelectorAll('[data-budget]').forEach(button => {
            button.setAttribute('aria-pressed', String(!free.checked && minimum === Number(button.dataset.min) && (openRange ? !button.dataset.budget : budget.value === button.dataset.budget)));
        });
    }
    function syncBranch() {
        if (sweetnessBranch(answers()).skipped) form.querySelectorAll('input[name="sweetness"]').forEach(e => { e.checked = false; });
    }
    function save() {
        try { localStorage.setItem(draftKey, JSON.stringify({ timestamp: Date.now(), answers: answers(), current, none, free: free.checked, openRange })); }
        catch { form.querySelector('[data-draft-status]').textContent = 'Browser tidak dapat menyimpan jawaban sementara. Jangan tutup tab sebelum selesai.'; }
    }
    function selectedFavorite() {
        const selected = seed.favorites.find(p => p.id === Number(favoriteInput.value));
        form.querySelector('[data-favorite-selected]').textContent = selected ? `Dipilih: ${selected.label}` : 'Belum ada parfum dipilih.';
        if (!selected) favoriteInput.value = '';
    }
    function restore(data, savedNone, savedFree) {
        minimum = Number.isInteger(data.budget_min) ? data.budget_min : 0;
        budget.value = data.budget_max ?? '';
        free.checked = savedFree ?? (data.budget_max === null && minimum === 0);
        openRange = data.budget_max === null && minimum > 0;
        avoidSweet.checked = data.avoid_sweet === true;
        favoriteInput.value = data.favorite_product_id ?? '';
        for (const [key, value] of Object.entries(data)) {
            if (!keys.includes(key) || ['budget_max', 'favorite_product_id'].includes(key)) continue;
            form.querySelectorAll(`input[name="${key}"]`).forEach(e => { e.checked = Array.isArray(value) ? value.includes(e.value) : e.value === value; });
        }
        for (const key of ['likes', 'avoid']) {
            none[key] = savedNone?.[key] ?? (Array.isArray(data[key]) && !data[key].length && !(key === 'avoid' && avoidSweet.checked));
            form.querySelector(`[data-none="${key}"]`).checked = none[key];
        }
        selectedFavorite(); syncBranch(); budgetState();
    }
    function valid(key) {
        const panel = panels.find(p => p.dataset.key === key);
        const data = answers();
        if (key === 'budget_max' && !validBudget(data.budget_min, data.budget_max, free.checked || openRange)) return 'Pilih rentang, isi budget rupiah bulat, atau pilih budget tidak dibatasi.';
        if (key === 'likes' && ((!data.likes.length && !none.likes) || data.likes.length > 3)) return 'Pilih satu sampai tiga aroma atau belum tahu.';
        if (key === 'avoid' && !data.avoid.length && !data.avoid_sweet && !none.avoid) return 'Pilih aroma yang tidak kamu suka atau tidak ada / belum tahu.';
        if (key === 'avoid' && data.avoid.some(v => data.likes.includes(v))) return 'Ada aroma yang sekaligus disukai dan dihindari. Ubah salah satunya.';
        if (panel?.dataset.type === 'radio' && panel.dataset.optional !== 'true' && !data[key]) return 'Pilih satu jawaban sebelum lanjut.';
        return null;
    }
    function summaryRows() {
        const target = form.querySelector('[data-summary-rows]'); target.replaceChildren();
        const data = answers();
        for (const key of active()) {
            const panel = panels.find(p => p.dataset.key === key);
            const line = document.createElement('div'); line.className = 'pref-summary-row';
            const info = document.createElement('div');
            const label = document.createElement('p'); label.className = 'text-sm text-brand-black/60'; label.textContent = panel.querySelector('legend').textContent;
            const value = document.createElement('p');
            const rupiah = number => `Rp ${number.toLocaleString('id-ID')}`;
            if (key === 'budget_max') value.textContent = data.budget_max === null ? (data.budget_min ? `${rupiah(data.budget_min)} ke atas` : 'Tidak dibatasi') : data.budget_min ? `${rupiah(data.budget_min)}–${rupiah(data.budget_max)}` : `Maksimal ${rupiah(data.budget_max)}`;
            else if (key === 'favorite_product_id') value.textContent = seed.favorites.find(p => p.id === data[key])?.label || 'Dilewati';
            else value.textContent = [...panel.querySelectorAll('input:checked')].map(e => e.closest('label').textContent.trim()).join(', ') || 'Dilewati';
            const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'pref-link min-h-11 shrink-0'; edit.textContent = 'Ubah'; edit.setAttribute('aria-label', `Ubah ${label.textContent}`); edit.addEventListener('click', () => show(key));
            info.append(label, value); line.append(info, edit); target.append(line);
        }
        const branch = sweetnessBranch(data);
        form.querySelector('[data-branch-summary]').hidden = !branch.skipped;
        form.querySelector('[data-branch-summary]').textContent = data.avoid_sweet ? 'Pilihan tidak suka aroma manis sudah dipakai. Pertanyaan kemanisan dilewati.' : 'Pertanyaan kemanisan dilewati karena kamu menghindari dessert/gourmand. Pilih Aroma manis pada jawaban hindari jika kamu juga ingin menghindari manis dari buah atau bunga.';
    }
    function updateStages() {
        const stageIndex = preferenceStage(current);
        stageButtons.forEach((button, index) => {
            button.dataset.state = index < stageIndex ? 'complete' : index === stageIndex ? 'current' : 'pending';
            button.disabled = transitioning || form.getAttribute('aria-busy') === 'true' || index > stageIndex;
            if (index === stageIndex) button.setAttribute('aria-current', 'step');
            else button.removeAttribute('aria-current');
        });
        form.querySelector('[data-stage-label]').textContent = PREFERENCE_STAGES[stageIndex].label;
    }
    function focusCurrent() {
        const heading = current === 'summary' ? summary.querySelector('h2') : panels.find(p => p.dataset.key === current).querySelector('legend');
        heading.focus({ preventScroll: true });
        form.scrollIntoView({ block: 'start', behavior: 'instant' });
    }
    async function show(key, focus = true) {
        if (transitioning && (focus || key !== current)) return;
        const available = active();
        const target = key === 'summary' || available.includes(key) ? key : available[0];
        const previous = current;
        const outgoing = previous === 'summary' ? summary : panels.find(p => p.dataset.key === previous);
        const render = () => {
            current = target;
            const index = available.indexOf(current);
            panels.forEach(panel => { panel.hidden = panel.dataset.key !== current; panel.inert = panel.hidden; });
            summary.hidden = current !== 'summary'; summary.inert = summary.hidden;
            const panel = current === 'summary' ? summary : panels.find(p => p.dataset.key === current);
            const label = current === 'summary' ? 'Ringkasan jawaban' : `Pertanyaan ${index + 1} dari ${available.length}${panel.dataset.optional === 'true' ? ' · opsional' : ''}`;
            form.querySelector('[data-step-label]').textContent = label;
            const progress = form.querySelector('[data-progress]'); progress.max = available.length; progress.value = current === 'summary' ? available.length : index;
            progress.setAttribute('aria-valuetext', label);
            form.querySelector('[data-progress-fill]').style.width = `${progress.value / progress.max * 100}%`;
            backButton.hidden = index === 0;
            nextButton.hidden = current === 'summary';
            form.querySelector('[data-next-label]').textContent = panel.dataset.optional === 'true' ? 'Lanjut / lewati' : 'Lanjut';
            form.querySelector('[data-preference-submit]').hidden = current !== 'summary';
            if (current === 'summary') summaryRows();
            message(null); budgetState(); updateStages(); save();
            return panel;
        };
        if (previous === target || !focus) { render(); return; }
        transitioning = true; nextButton.disabled = true; backButton.disabled = true; submitButton.disabled = true; updateStages();
        const order = [...available, 'summary'];
        try {
            await transitionPreferencePanel({ frame, outgoing, render,
                direction: order.indexOf(target) < order.indexOf(previous) ? -1 : 1,
                reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches });
            focusCurrent();
        } finally {
            transitioning = false; nextButton.disabled = false; backButton.disabled = false; submitButton.disabled = false; updateStages();
        }
    }
    stageButtons.forEach((button, index) => button.addEventListener('click', () => {
        if (transitioning || index > preferenceStage(current)) return;
        const target = stageDestination(index, active());
        if (target) show(target);
    }));
    form.querySelectorAll('[data-budget]').forEach(button => button.addEventListener('click', () => {
        free.checked = false; minimum = Number(button.dataset.min); openRange = !button.dataset.budget; budget.value = button.dataset.budget || ''; budgetState(); save();
    }));
    form.querySelector('[data-budget-custom]').addEventListener('click', () => { free.checked = false; minimum = 0; openRange = false; budgetState(); budget.focus(); save(); });
    budget.addEventListener('input', () => { minimum = 0; openRange = false; budgetState(); });
    free.addEventListener('change', () => { minimum = 0; openRange = false; budgetState(); save(); });
    form.addEventListener('change', event => {
        if (event.target.dataset.none) {
            const key = event.target.dataset.none; none[key] = event.target.checked;
            if (none[key]) { form.querySelectorAll(`input[name="${key}"]`).forEach(e => { e.checked = false; }); if (key === 'avoid') avoidSweet.checked = false; }
        } else if (['likes', 'avoid'].includes(event.target.name) || event.target === avoidSweet) {
            const key = event.target === avoidSweet ? 'avoid' : event.target.name; none[key] = false; form.querySelector(`[data-none="${key}"]`).checked = false;
            if (key === 'likes' && answers().likes.length > 3) { event.target.checked = false; message('Pilih maksimal tiga aroma.'); }
        }
        syncBranch();
        if (event.target.name === 'avoid' || event.target === avoidSweet || event.target.dataset.none === 'avoid') show(current, false);
        else save();
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
    nextButton.addEventListener('click', () => { if (transitioning) return; const problem = valid(current); if (problem) message(problem); else { const available = active(); show(available[available.indexOf(current) + 1] || 'summary'); } });
    backButton.addEventListener('click', () => { if (transitioning) return; const available = active(); show(current === 'summary' ? available.at(-1) : available[available.indexOf(current) - 1]); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (transitioning || form.getAttribute('aria-busy') === 'true') return;
        for (const key of active()) { const problem = valid(key); if (problem) { await show(key); message(problem); return; } }
        const submit = form.querySelector('[data-preference-submit]'); submit.disabled = true; form.querySelector('[data-submit-label]').textContent = 'Mencari rekomendasi…'; form.setAttribute('aria-busy', 'true'); frame.inert = true; backButton.disabled = true; updateStages(); save();
        try {
            const response = await fetch(form.action, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.elements._token.value }, body: JSON.stringify(answers()) });
            if (response.ok && response.redirected) { window.location.assign(response.url); return; }
            if (response.status === 422) { const data = await response.json(); message(Object.values(data.errors || {}).flat().join(' ') || 'Periksa jawabanmu.'); }
            else message(response.status === 429 ? 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.' : response.status === 419 ? 'Sesi berubah. Refresh halaman; jawaban sementara tetap tersimpan.' : 'Hasil belum berhasil disimpan. Coba lagi, jawabanmu tetap tersedia.');
        } catch { message('Koneksi terputus. Coba lagi, jawabanmu tetap tersedia.'); }
        finally { submit.disabled = false; form.querySelector('[data-submit-label]').textContent = 'Lihat rekomendasi'; form.removeAttribute('aria-busy'); frame.inert = false; backButton.disabled = false; updateStages(); }
    });
    if (Object.keys(seed.answers).length) restore(seed.answers);
    else { try { const draft = JSON.parse(localStorage.getItem(draftKey)); if (draft && Date.now() - draft.timestamp < 86400000 && Date.now() >= draft.timestamp) { restore(draft.answers, draft.none, draft.free); current = typeof draft.current === 'string' ? draft.current : keys[0]; } } catch { /* Storage is optional. */ } }
    show(current, false);
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
