// CAT-MEDIA-PASTE-02 / CAT-PUBLISH-CHECK-01: a publish with missing requirements is stopped in the browser, before
// photos upload and the page reloads, and every missing item is named at once next to the buttons. The server keeps
// its own publish validation; this only saves a slow round trip that looked like a stuck page.

/** Fields still empty for this submit, in page order. `always` covers saving a product that is already published. */
export function missingForPublish(action, fields, always = false) {
    if (action !== 'published' && !always) return [];

    return fields.filter((field) => String(field.value ?? '').trim() === '');
}

export function summaryText(missing) {
    return missing.length ? `Belum bisa publish. Lengkapi: ${missing.map((field) => field.label).join(', ')}.` : '';
}

function photoField(form) {
    const box = form.querySelector('[data-photo-paste]');
    if (!box) return null;
    const count = Number(box.dataset.existing || 0) + (box.querySelector('[data-photo-input]')?.files.length ?? 0);

    return { input: box.querySelector('[data-photo-dropzone]'), value: count > 0 ? String(count) : '', label: 'foto', photo: true };
}

if (typeof document !== 'undefined') {
    // Capture phase: runs before the photo keeper and the page-loading overlay, which both skip a stopped submit.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-publish-check]')) return;
        // A read-only App price cannot be fixed here; the server's publish check explains that case.
        const fields = [...form.querySelectorAll('[data-required-for-publish]')]
            .filter((input) => !input.readOnly && !input.disabled)
            .map((input) => ({ input, value: input.value, label: input.dataset.requiredForPublish }));
        const photo = photoField(form);
        if (photo) fields.push(photo);
        const missing = missingForPublish(event.submitter?.value, fields, form.hasAttribute('data-publish-check-always'));
        const summary = form.querySelector('[data-publish-check-summary]');
        if (summary) {
            summary.textContent = summaryText(missing);
            summary.hidden = missing.length === 0;
        }
        if (!missing.length) return;
        event.preventDefault();
        const first = missing[0];
        if (first.photo) {
            first.input.scrollIntoView({ block: 'center' });
            first.input.focus({ preventScroll: true });

            return;
        }
        first.input.setCustomValidity(`${first.input.tagName === 'SELECT' ? 'Pilih' : 'Isi'} ${first.label} sebelum publish.`);
        first.input.reportValidity();
        const clear = () => first.input.setCustomValidity('');
        first.input.addEventListener('input', clear, { once: true });
        first.input.addEventListener('change', clear, { once: true });
    }, true);
}
