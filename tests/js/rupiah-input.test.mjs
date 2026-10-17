import assert from 'node:assert/strict';
import test from 'node:test';
import { bindRupiahInput, unformatRupiahFields } from '../../resources/js/ui/rupiah-input.js';

const fakeInput = (value) => ({ value, dataset: {}, listeners: {}, addEventListener(type, listener) { this.listeners[type] = listener; } });

test('rupiah fields show thousands separators, including stored decimals and typed digits', () => {
    for (const [stored, shown] of [['15000', '15.000'], ['150000.00', '150.000'], ['150.000', '150.000'], ['', ''], ['Rp 1.250.000', '1.250.000']]) {
        const input = fakeInput(stored);
        bindRupiahInput(input);
        assert.equal(input.value, shown, stored);
    }
    const typed = fakeInput('');
    bindRupiahInput(typed);
    typed.value = '27000';
    typed.listeners.input();
    assert.equal(typed.value, '27.000');
});

test('the form submits plain digits', () => {
    const inputs = [{ value: '15.000' }, { value: '1.250.000' }];
    unformatRupiahFields({ querySelectorAll: () => inputs });
    assert.deepEqual(inputs.map((input) => input.value), ['15000', '1250000']);
});
