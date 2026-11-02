import test from 'node:test';
import assert from 'node:assert/strict';
import { missingForPublish } from '../../resources/js/ui/product-publish-check.js';

const size = { value: '', message: 'Isi ukuran (ml) sebelum publish.' };
const price = { value: '409.000', message: 'Isi harga jual sebelum publish.' };

test('publishing with an empty size is stopped with the size message', () => {
    assert.equal(missingForPublish('published', [size, price]), size);
    assert.equal(missingForPublish('published', [{ ...size, value: '  ' }, price]).message, size.message);
});

test('saving a draft never blocks, and a complete publish passes', () => {
    assert.equal(missingForPublish('draft', [size, price]), null);
    assert.equal(missingForPublish('save', [size, price]), null);
    assert.equal(missingForPublish(undefined, [size, price]), null);
    assert.equal(missingForPublish('published', [{ ...size, value: '100' }, price]), null);
});

test('the first missing field in page order is reported', () => {
    assert.equal(missingForPublish('published', [{ ...size, value: '100' }, { ...price, value: '' }]).message, price.message);
});
