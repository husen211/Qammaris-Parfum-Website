import test from 'node:test';
import assert from 'node:assert/strict';
import { genderOf, parseProductText, sizeOf, splitNotes } from '../../resources/js/ui/product-text-paste.js';

const shopee = `✨ MYKONOS BLACK OPERA EDP 50ML ✨
READY STOCK!! 100% ORIGINAL
━━━━━━━━━━━━━━
Deskripsi Produk:
Black Opera adalah parfum malam yang hangat dan elegan.
Cocok untuk acara formal.

🌸 Top Notes : Bergamot, Pink Pepper
🌹 Middle Notes : Rose, Jasmine
🌲 Base Notes : Vanilla, Musk & Amber
Gender : Unisex
Ukuran : 50ml
Longevity: 6-8 jam

Pengiriman setiap hari kecuali Minggu
Happy shopping!
#parfum #mykonos #parfumoriginal`;

test('a typical Shopee description is split into notes, gender, size and a clean description', () => {
    const result = parseProductText(shopee);
    assert.deepEqual(result.notes, { top: ['Bergamot', 'Pink Pepper'], middle: ['Rose', 'Jasmine'], base: ['Vanilla', 'Musk', 'Amber'] });
    assert.equal(result.gender, 'Unisex');
    assert.equal(result.sizeMl, 50);
    assert.equal(result.found, true);
    assert.equal(result.description, '✨ MYKONOS BLACK OPERA EDP 50ML ✨\nBlack Opera adalah parfum malam yang hangat dan elegan.\nCocok untuk acara formal.\n\nLongevity: 6-8 jam');
    assert.ok(result.removed >= 10);
});

test('bare headers with bullet or plain lines below, Indonesian labels and capitalisation', () => {
    const result = parseProductText(`TOP NOTES:
- BERGAMOT
- lemon
Aroma Tengah:
iris, violet
BASE:
• Sandalwood
• Tonka Bean

Parfum segar untuk pria.`);
    assert.deepEqual(result.notes, { top: ['Bergamot', 'Lemon'], middle: ['Iris', 'Violet'], base: ['Sandalwood', 'Tonka Bean'] });
    assert.equal(result.gender, 'Pria');
    assert.equal(result.description, 'Parfum segar untuk pria.');
});

test('notes are split on the usual separators, cleaned and de-duplicated', () => {
    assert.deepEqual(splitNotes('Oud / Rose - Saffron; amber, Amber.'), ['Oud', 'Rose', 'Saffron', 'Amber', 'Amber']);
    assert.deepEqual(parseProductText('Base notes: Oud, oud, Amber').notes.base, ['Oud', 'Amber']);
    assert.deepEqual(splitNotes('Lily of the Valley, dll'), ['Lily of the Valley']);
    assert.deepEqual(splitNotes('lily of the valley'), ['Lily Of The Valley']);
    assert.deepEqual(splitNotes('Ylang-Ylang dan Heliotrope'), ['Ylang-Ylang', 'Heliotrope']);
});

test('gender reads explicit words only and stays empty when unsure', () => {
    assert.equal(genderOf('Gender: Women'), 'Wanita');
    assert.equal(genderOf('cocok untuk pria maupun wanita'), 'Unisex');
    assert.equal(genderOf('aroma manis dan mengandung melati'), null);
    assert.equal(genderOf('female'), 'Wanita');
});

test('size is taken only when the text names exactly one size', () => {
    assert.equal(sizeOf('EDP 100 ml original'), 100);
    assert.equal(sizeOf('Tersedia 30ml / 50ml / 100ml'), null);
    assert.equal(sizeOf('tanpa ukuran'), null);
    assert.equal(sizeOf('1.5ml decant'), null);
});

test('plain prose without notes, gender or size is left alone', () => {
    const result = parseProductText('Parfum ini lembut dan hangat.\nCocok dipakai sehari-hari.');
    assert.equal(result.found, false);
    assert.equal(result.description, 'Parfum ini lembut dan hangat.\nCocok dipakai sehari-hari.');
});
