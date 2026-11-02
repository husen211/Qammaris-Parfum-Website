// CAT-MEDIA-PASTE-02: "Publish" with an empty size or price is stopped in the browser, before the page reloads,
// so the Owner fixes the field in place. The server keeps its own publish validation; this only saves a round trip.

/** First field still empty for a publish submit, or null. `fields` are [{ value, message }] in page order. */
export function missingForPublish(action, fields) {
    if (action !== 'published') return null;

    return fields.find((field) => String(field.value ?? '').trim() === '') ?? null;
}

if (typeof document !== 'undefined') {
    // Capture phase: runs before the photo keeper, which leaves an already stopped submit alone.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-publish-check]')) return;
        // A read-only App price cannot be fixed here; the server's publish check explains that case.
        const inputs = [...form.querySelectorAll('[data-required-for-publish]')].filter((input) => !input.readOnly && !input.disabled);
        const missing = missingForPublish(event.submitter?.value, inputs.map((input) => ({ input, value: input.value, message: input.dataset.requiredForPublish })));
        if (!missing) return;
        event.preventDefault();
        missing.input.setCustomValidity(missing.message);
        missing.input.reportValidity();
        const clear = () => missing.input.setCustomValidity('');
        missing.input.addEventListener('input', clear, { once: true });
        missing.input.addEventListener('change', clear, { once: true });
    }, true);
}
