// CAT-SIZE-OPTIONS-01 (Owner 2026-11-02): the catalog's most used sizes as one-click buttons under "Ukuran (ml)".
// The buttons only fill the number field; any other size can still be typed.

export function bindSizeOptions(root) {
    if (root.dataset.sizeBound) return;
    root.dataset.sizeBound = '1';
    const input = root.closest('form')?.querySelector(root.dataset.sizeInput);
    const buttons = [...root.querySelectorAll('[data-size]')];
    if (!input) return;

    const mark = () => buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.size === input.value.trim())));
    buttons.forEach((button) => button.addEventListener('click', () => {
        input.value = button.dataset.size;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        mark();
    }));
    input.addEventListener('input', mark);
    mark();
}

if (typeof document !== 'undefined') {
    const bindAll = () => document.querySelectorAll('[data-size-options]').forEach(bindSizeOptions);
    bindAll();
    document.addEventListener('DOMContentLoaded', bindAll);
}
