import test from 'node:test';
import assert from 'node:assert/strict';
import { copiedLinkOnly, encodeUnderLimit, fitWithin, imageFiles, keepsOriginal, MAX_BYTES, photoName } from '../../resources/js/ui/product-photo-paste.js';

const file = (type, size = 1000, name = 'x') => ({ type, size, name });
const transfer = ({ files = [], items = [], types = [], text = '' } = {}) => ({ files, items, types, getData: (type) => (type === 'text/plain' ? text : '') });

test('Copy image in Chrome gives an image file; text and other files are ignored', () => {
    const png = file('image/png');
    assert.deepEqual(imageFiles(transfer({ files: [png, file('application/pdf')], types: ['Files', 'text/html'] })), [png]);
    const fromItem = file('image/webp');
    const items = [{ kind: 'string', type: 'text/html' }, { kind: 'file', type: 'image/webp', getAsFile: () => fromItem }];
    assert.deepEqual(imageFiles(transfer({ items })), [fromItem]);
    assert.deepEqual(imageFiles(null), []);
});

test('a copied image address is recognised so the admin gets a hint instead of nothing', () => {
    assert.equal(copiedLinkOnly(transfer({ types: ['text/plain'], text: 'https://cdn.example.test/bottle.jpg' })), true);
    assert.equal(copiedLinkOnly(transfer({ types: ['text/html', 'text/plain'], text: 'Bottle' })), true);
    assert.equal(copiedLinkOnly(transfer({ types: ['text/plain'], text: 'Mykonos 100ml' })), false);
    assert.equal(copiedLinkOnly(transfer({ files: [file('image/png')], types: ['Files', 'text/html'] })), false);
});

test('photos shrink to 2000 px on the longest side and are never enlarged', () => {
    assert.deepEqual(fitWithin(4000, 3000), { width: 2000, height: 1500 });
    assert.deepEqual(fitWithin(1200, 2400), { width: 1000, height: 2000 });
    assert.deepEqual(fitWithin(800, 600), { width: 800, height: 600 });
});

test('a JPEG/PNG/WebP that already fits is uploaded untouched; anything else is re-encoded', () => {
    assert.equal(keepsOriginal(file('image/jpeg', 900_000), 1600, 1600), true);
    assert.equal(keepsOriginal(file('image/png', MAX_BYTES + 1), 1600, 1600), false);
    assert.equal(keepsOriginal(file('image/webp', 500_000), 2400, 1000), false);
    assert.equal(keepsOriginal(file('image/gif', 10_000), 400, 400), false);
});

test('quality steps down until the photo is under 2 MB', async () => {
    const calls = [];
    const blob = await encodeUnderLimit(async (type, quality) => {
        calls.push([type, quality]);
        return { type, size: quality > 0.75 ? MAX_BYTES + 1 : MAX_BYTES - 1 };
    });
    assert.deepEqual(calls.map(([, quality]) => quality), [0.9, 0.82, 0.74]);
    assert.equal(blob.type, 'image/webp');
});

test('a result far above 2 MB stops at once so the photo is shrunk instead of re-encoded six times', async () => {
    let calls = 0;
    const blob = await encodeUnderLimit(async (type) => {
        calls++;
        return { type, size: MAX_BYTES * 2 + 1 };
    });
    assert.equal(blob, null);
    assert.equal(calls, 1);
});

test('browsers that cannot write WebP fall back to JPEG; nothing fitting returns null', async () => {
    const jpeg = await encodeUnderLimit(async (type) => ({ type: type === 'image/webp' ? 'image/png' : type, size: 1000 }));
    assert.equal(jpeg.type, 'image/jpeg');
    assert.equal(await encodeUnderLimit(async (type) => ({ type, size: MAX_BYTES * 3 })), null);
    assert.equal(photoName(2, 'image/jpeg'), 'foto-tempel-2.jpg');
    assert.equal(photoName(1, 'image/webp'), 'foto-tempel-1.webp');
});
