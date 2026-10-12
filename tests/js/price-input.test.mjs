import test from 'node:test';
import assert from 'node:assert/strict';
import { bindPriceInput, caretAfterDigits, formatPrice, priceDigits } from '../../resources/js/ui/price-input.js';

test('prices use Indonesian thousands separators and submit as digits', () => {
    assert.equal(formatPrice('200000'), '200.000');
    assert.equal(formatPrice('500000'), '500.000');
    assert.equal(formatPrice('1000000'), '1.000.000');
    assert.equal(formatPrice('950'), '950');
    assert.equal(formatPrice(''), '');
    assert.equal(formatPrice('Rp 1.250.000'), '1.250.000');
    assert.equal(formatPrice('007'), '7');
    assert.equal(priceDigits('1.000.000'), '1000000');
    assert.equal(priceDigits('12345678901'), '123456789');
});

test('caret stays after the same digit while typing in the middle', () => {
    assert.equal(caretAfterDigits('1.000.000', 1), 1);
    assert.equal(caretAfterDigits('1.000.000', 2), 3);
    assert.equal(caretAfterDigits('1.000.000', 7), 9);
    assert.equal(caretAfterDigits('200.000', 0), 0);
});

test('bound input reformats on input and keeps the caret', () => {
    const listeners = {};
    const input = {
        value: '', selectionStart: 0, selection: null,
        addEventListener: (type, fn) => { listeners[type] = fn; },
        setSelectionRange(start) { this.selection = start; },
    };
    bindPriceInput(input);
    input.value = '200000'; input.selectionStart = 6; listeners.input();
    assert.equal(input.value, '200.000');
    assert.equal(input.selection, 7);
    // Typing a 1 at the front of "200.000" -> "1200.000".
    input.value = '1200.000'; input.selectionStart = 1; listeners.input();
    assert.equal(input.value, '1.200.000');
    assert.equal(input.selection, 1);
});
