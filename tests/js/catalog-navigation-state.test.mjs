import assert from 'node:assert/strict';
import test from 'node:test';
import { catalogLocationKey, matchingPosition } from '../../resources/js/ui/catalog-navigation-state.js';

const url = 'https://qammarisparfum.id/products?search=afnan&sort=price_low&page=2';
const record = { url: catalogLocationKey(url), y: 920, sidebarY: 180 };

test('the same catalog context restores despite query order or fragment', () => {
    assert.equal(matchingPosition(record,
        'https://qammarisparfum.id/products?page=2&sort=price_low&search=afnan#main-content'), record);
});

test('another search, page, filter or origin cannot borrow the previous position', () => {
    for (const target of [url.replace('afnan', 'lattafa'), url.replace('page=2', 'page=3'),
        `${url}&availability=sold_out`, url.replace('qammarisparfum.id', 'other.example')]) {
        assert.equal(matchingPosition(record, target), null);
    }
});

test('missing or corrupt positions do not restore', () => {
    for (const value of [null, {}, { ...record, y: -1 }, { ...record, y: '900' },
        { ...record, y: NaN }, { ...record, sidebarY: Infinity }]) {
        assert.equal(matchingPosition(value, url), null);
    }
});
