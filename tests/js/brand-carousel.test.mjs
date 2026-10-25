import assert from 'node:assert/strict';
import test from 'node:test';
import { SECONDS_PER_BRAND, loopDuration } from '../../resources/js/ui/brand-carousel.js';

test('the row moves about one brand per SECONDS_PER_BRAND, whatever the brand count', () => {
    assert.ok(SECONDS_PER_BRAND >= 1 && SECONDS_PER_BRAND <= 2.5);
    assert.equal(loopDuration(30), 30 * SECONDS_PER_BRAND * 1000);
    assert.equal(loopDuration(0), SECONDS_PER_BRAND * 1000);
});
