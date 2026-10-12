// UX-02: catalog price fields show Indonesian thousands separators (200.000) while the form still sends digits.
export const PRICE_FIELDS = ['price_min', 'price_max'];
const MAX_DIGITS = 9;

export function priceDigits(value) {
    return String(value ?? '').replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, MAX_DIGITS);
}

export function formatPrice(value) {
    return priceDigits(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

/** Caret index in the formatted text that keeps the same number of digits before it. */
export function caretAfterDigits(formatted, digitsBefore) {
    if (digitsBefore <= 0) {
        return 0;
    }
    let seen = 0;
    for (let index = 0; index < formatted.length; index++) {
        if (/\d/.test(formatted[index]) && ++seen === digitsBefore) {
            return index + 1;
        }
    }

    return formatted.length;
}

export function bindPriceInput(input) {
    input.addEventListener('input', () => {
        const caret = input.selectionStart ?? input.value.length;
        const digitsBefore = priceDigits(input.value.slice(0, caret)).length;
        const formatted = formatPrice(input.value);
        if (formatted === input.value) {
            return;
        }
        input.value = formatted;
        const position = caretAfterDigits(formatted, digitsBefore);
        input.setSelectionRange?.(position, position);
    });
}
