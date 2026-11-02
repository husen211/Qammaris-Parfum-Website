import test from 'node:test';
import assert from 'node:assert/strict';
import { missingForPublish, summaryText } from '../../resources/js/ui/product-publish-check.js';

const field = (label, value) => ({ label, value });
const form = (overrides = {}) => [
    field('nama', 'Black Opera'), field('brand', '3'), field('kategori', overrides.kategori ?? ''),
    field('deskripsi', 'Hangat'), field('gender', overrides.gender ?? ''), field('ukuran (ml)', '100'),
    field('harga jual', '409.000'), field('foto', overrides.foto ?? '1'),
];

test('publish with gender and category empty names both at once, in page order', () => {
    const missing = missingForPublish('published', form());
    assert.deepEqual(missing.map((item) => item.label), ['kategori', 'gender']);
    assert.equal(summaryText(missing), 'Belum bisa publish. Lengkapi: kategori, gender.');
});

test('a product without any photo cannot be published', () => {
    const missing = missingForPublish('published', form({ kategori: '2', gender: 'Unisex', foto: '' }));
    assert.deepEqual(missing.map((item) => item.label), ['foto']);
});

test('saving a draft never blocks; saving a published product checks like publish', () => {
    assert.deepEqual(missingForPublish('draft', form()), []);
    assert.deepEqual(missingForPublish('save', form()), []);
    assert.deepEqual(missingForPublish(undefined, form()), []);
    assert.deepEqual(missingForPublish('save', form(), true).map((item) => item.label), ['kategori', 'gender']);
});

test('a complete form passes and whitespace counts as empty', () => {
    assert.deepEqual(missingForPublish('published', form({ kategori: '2', gender: 'Pria' })), []);
    assert.deepEqual(missingForPublish('published', [field('deskripsi', '   ')]).map((item) => item.label), ['deskripsi']);
    assert.equal(summaryText([]), '');
});
