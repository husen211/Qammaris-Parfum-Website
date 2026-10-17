// ORD-08 (Owner 2026-10-17): every rupiah field shows thousands separators (15.000) while typing.
// The form still submits plain digits, so server validation of integer prices is unchanged.
import { bindPriceInput, formatPrice, priceDigits } from './price-input.js';

export function bindRupiahInput(input) {
    if (input.dataset.rupiahBound) {
        return;
    }
    input.dataset.rupiahBound = '1';
    // A stored decimal (150000.00) keeps only its rupiah part; 150.000 is already a thousands-formatted value.
    const value = /^\d+\.\d{1,2}$/.test(input.value) ? input.value.split('.')[0] : input.value;
    input.value = formatPrice(value);
    bindPriceInput(input);
}

export function unformatRupiahFields(form) {
    form.querySelectorAll('[data-rupiah-input]').forEach((input) => {
        input.value = priceDigits(input.value);
    });
}

if (typeof document !== 'undefined') {
    const bindAll = () => document.querySelectorAll('[data-rupiah-input]').forEach(bindRupiahInput);
    bindAll();
    document.addEventListener('DOMContentLoaded', bindAll);
    // Capture phase: digits are restored before any other submit handler or the browser serializes the form.
    document.addEventListener('submit', (event) => {
        if (event.target instanceof HTMLFormElement) {
            unformatRupiahFields(event.target);
        }
    }, true);
}
