// CAT-COMPARE-PRICE-01 (Owner 2026-11-02): one-click struck-through price options above the selling price,
// rounded to tidy amounts. The field stays optional and editable; the server still requires it to be a whole rupiah.
import { formatPrice, priceDigits } from './price-input.js';

export const MARKUPS = [30, 50, 70];

/** Whole rupiah from a field: "409.000", "409000" or a stored "409000.00" (before the rupiah formatter ran). */
export function rupiahValue(value) {
    const text = String(value ?? '').trim();

    return Number(/^\d+\.\d{1,2}$/.test(text) ? text.split('.')[0] : priceDigits(text)) || 0;
}

/** Options above `price`, rounded up to Rp 10.000 below Rp 200.000 and Rp 50.000 from there; duplicates dropped. */
export function compareOptions(price, markups = MARKUPS) {
    if (!Number.isFinite(price) || price <= 0) return [];
    const step = price < 200000 ? 10000 : 50000;
    const seen = new Set();

    return markups
        .map((markup) => Math.ceil((price * (100 + markup)) / 100 / step) * step)
        .filter((value) => value > price && !seen.has(value) && seen.add(value))
        .map((value) => ({ value, percent: Math.round((value / price - 1) * 100) }));
}

export function bindCompareOptions(root) {
    if (root.dataset.compareBound) return;
    root.dataset.compareBound = '1';
    const form = root.closest('form');
    const target = form?.querySelector('[data-compare-input]');
    const source = form?.querySelector(root.dataset.priceInput);
    const buttons = root.querySelector('[data-compare-buttons]');
    const hint = root.querySelector('[data-compare-hint]');
    if (!target || !source) return;

    const render = () => {
        const price = rupiahValue(source.value);
        const current = rupiahValue(target.value);
        const options = compareOptions(price);
        hint.hidden = options.length > 0;
        buttons.replaceChildren(...options.map(({ value, percent }) => {
            const button = document.createElement('button');
            button.type = 'button';
            const chosen = value === current;
            button.className = `inline-flex min-h-11 items-center rounded-lg border px-3 text-sm tabular-nums focus-visible:outline-2 focus-visible:outline-offset-2 ${chosen ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-300 bg-white text-gray-800 hover:bg-gray-50'}`;
            button.setAttribute('aria-pressed', String(chosen));
            button.textContent = `Rp ${formatPrice(String(value))} (+${percent}%)`;
            button.addEventListener('click', () => {
                target.value = formatPrice(String(value));
                target.dispatchEvent(new Event('input', { bubbles: true }));
                render();
            });

            return button;
        }));
    };
    source.addEventListener('input', render);
    target.addEventListener('input', () => queueMicrotask(render));
    render();
}

if (typeof document !== 'undefined') {
    const bindAll = () => document.querySelectorAll('[data-compare-options]').forEach(bindCompareOptions);
    bindAll();
    document.addEventListener('DOMContentLoaded', bindAll);
}
