import test from 'node:test';
import assert from 'node:assert/strict';
import { whiteMarginBounds } from '../../resources/js/ui/catalog-image-bounds.js';

function pixels(width = 100, height = 100, background = 255) {
    const data = new Uint8ClampedArray(width * height * 4);
    for (let i = 0; i < data.length; i += 4) {
        data.set([background, background, background, 255], i);
    }
    return { data, width, height };
}

function rectangle(image, x, y, width, height, color = 40) {
    for (let row = y; row < y + height; row++) {
        for (let col = x; col < x + width; col++) {
            image.data.set([color, color, color, 255], (row * image.width + col) * 4);
        }
    }
}

test('white padding is balanced while retaining bottle, cap, box and safety margins', () => {
    const image = pixels();
    rectangle(image, 28, 35, 18, 30);
    rectangle(image, 57, 30, 17, 38);
    const bounds = whiteMarginBounds(image);
    assert.ok(bounds);
    assert.ok(bounds.x <= 23 && bounds.y <= 25);
    assert.ok(bounds.x + bounds.width >= 79 && bounds.y + bounds.height >= 73);
    assert.ok(bounds.width >= 50 && bounds.height >= 50);
});

test('off-centre packaging and faint glass edges remain inside the adjusted frame', () => {
    const image = pixels();
    rectangle(image, 5, 25, 35, 50);
    rectangle(image, 42, 28, 12, 47, 245);
    const bounds = whiteMarginBounds(image);
    assert.ok(bounds.x <= 5 && bounds.x + bounds.width >= 54);
    assert.ok(bounds.y <= 25 && bounds.y + bounds.height >= 75);
});

test('edge-touching bottles and coloured/editorial photography are not cropped', () => {
    const edge = pixels(); rectangle(edge, 0, 30, 35, 45);
    assert.equal(whiteMarginBounds(edge), null);
    const scene = pixels(100, 100, 220); rectangle(scene, 30, 30, 30, 50);
    assert.equal(whiteMarginBounds(scene), null);
});

test('blank, tiny and malformed images keep their original framing', () => {
    assert.equal(whiteMarginBounds(pixels()), null);
    const tiny = pixels(); rectangle(tiny, 40, 40, 5, 5);
    assert.equal(whiteMarginBounds(tiny), null);
    assert.equal(whiteMarginBounds({ data: [], width: 100, height: 100 }), null);
});

test('transparent margins and non-square source dimensions are supported', () => {
    const image = pixels(120, 80);
    for (let i = 3; i < image.data.length; i += 4) image.data[i] = 0;
    rectangle(image, 38, 16, 36, 42);
    const bounds = whiteMarginBounds(image);
    assert.ok(bounds.x <= 38 && bounds.y <= 16);
    assert.ok(bounds.x + bounds.width >= 74 && bounds.y + bounds.height >= 58);
    assert.ok(bounds.width >= 60 && bounds.height >= 40);
});
