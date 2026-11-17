import assert from 'node:assert/strict';
import test from 'node:test';
import { INTERVAL_MS, createRotation, swipeDirection, wrapIndex } from '../../resources/js/ui/new-arrival.js';

test('next and previous wrap around the five arrivals', () => {
    assert.equal(INTERVAL_MS, 4000);
    assert.equal(wrapIndex(5, 5), 0);
    assert.equal(wrapIndex(-1, 5), 4);
    assert.equal(wrapIndex(7, 5), 2);
    assert.equal(wrapIndex(3, 0), 0);

    const rotation = createRotation(5);
    assert.equal(rotation.previous(), 4);
    assert.equal(rotation.next(), 0);
    rotation.show(4);
    assert.equal(rotation.next(), 0);
});

test('hovering a row shows it at once and holds autoplay until the pointer leaves', () => {
    const rotation = createRotation(5);
    assert.equal(rotation.running, true);

    rotation.hold('pointer');
    rotation.show(3);
    assert.equal(rotation.index, 3);
    assert.equal(rotation.running, false);

    rotation.hold('focus');
    assert.equal(rotation.release('pointer'), true);
    assert.equal(rotation.running, false, 'keyboard focus still holds');
    rotation.release('focus');
    assert.equal(rotation.running, true);
    assert.equal(rotation.index, 3, 'autoplay resumes from the shown product');
    assert.equal(rotation.release('focus'), false);
});

test('the pause button stops autoplay until pressed again, while manual switches still work', () => {
    const rotation = createRotation(5);
    assert.equal(rotation.toggle(), false);
    assert.equal(rotation.running, false);
    assert.equal(rotation.next(), 1);
    assert.equal(rotation.toggle(), true);
    assert.equal(rotation.running, true);
});

test('reduced motion never autoplays but hover, focus and swipe still switch', () => {
    const rotation = createRotation(5, { reducedMotion: true });
    assert.equal(rotation.autoplay, false);
    assert.equal(rotation.running, false);
    rotation.show(2);
    assert.equal(rotation.index, 2);
    assert.equal(createRotation(1).autoplay, false, 'a single product never rotates');
});

test('a horizontal swipe moves next or previous; short or vertical moves do nothing', () => {
    assert.equal(swipeDirection(-60, 5), 1);
    assert.equal(swipeDirection(60, -5), -1);
    assert.equal(swipeDirection(-20, 0), 0);
    assert.equal(swipeDirection(-60, 80), 0);
});
