import test from 'node:test';
import assert from 'node:assert/strict';
import { compareOptions, rupiahValue } from '../../resources/js/ui/compare-price-options.js';

test('Rp 409.000 offers +30/50/70% rounded up to Rp 50.000', () => {
    assert.deepEqual(compareOptions(409000), [
        { value: 550000, percent: 34 },
        { value: 650000, percent: 59 },
        { value: 700000, percent: 71 },
    ]);
});

test('below Rp 200.000 the options round up to Rp 10.000', () => {
    assert.deepEqual(compareOptions(89000).map((option) => option.value), [120000, 140000, 160000]);
    assert.deepEqual(compareOptions(150000).map((option) => option.value), [200000, 230000, 260000]);
});

test('options that round to the same amount appear once; no price means no options', () => {
    assert.deepEqual(compareOptions(1000, [30, 31]).map((option) => option.value), [10000]);
    assert.deepEqual(compareOptions(0), []);
    assert.deepEqual(compareOptions(Number.NaN), []);
});

test('field values are read as whole rupiah, including a stored decimal', () => {
    assert.equal(rupiahValue('409.000'), 409000);
    assert.equal(rupiahValue('409000'), 409000);
    assert.equal(rupiahValue('425000.00'), 425000);
    assert.equal(rupiahValue(''), 0);
});
