import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { Script } from 'node:vm';
import test from 'node:test';
import { catalogLocationKey, matchingPosition } from '../../resources/js/ui/catalog-navigation-state.js';

const source = readFileSync(new URL('../../resources/js/ui/catalog-navigation.js', import.meta.url), 'utf8')
    .replace(/^import[^\n]+\n/, '');
const url = 'https://qammarisparfum.id/products?page=2&search=hawas';

function browser(record = null, detail = false) {
    const handlers = {};
    const result = { focus: 0, blur: 0, scroll: null, record };
    const card = {
        dataset: { catalogProduct: '42' },
        focus: () => { result.focus++; },
        blur: () => { result.blur++; },
        setAttribute: (name) => { result[name] = true; },
    };
    const brands = { scrollTop: 0 };
    const sidebar = { scrollTop: 0, querySelector: () => brands };
    const catalog = {
        dataset: { catalogUrl: url }, querySelectorAll: () => [card],
        addEventListener: (name, listener) => { handlers.card = listener; },
    };
    const back = { href: url, addEventListener: (name, listener) => { handlers.back = listener; } };
    const document = {
        querySelector: (selector) => ({
            '[data-catalog-discovery]': detail ? null : catalog,
            '[data-catalog-return]': detail ? back : null,
            '.catalog-sidebar': sidebar,
        })[selector], querySelectorAll: () => [],
    };
    new Script(source).runInNewContext({
        catalogLocationKey, matchingPosition, document,
        window: {
            scrollY: 900, matchMedia: () => ({ matches: false }),
            addEventListener: (name, listener) => { handlers[name] = listener; },
            scrollTo: (position) => { result.scroll = position.top; },
        },
        sessionStorage: {
            getItem: () => JSON.stringify(result.record),
            setItem: (key, value) => { result.record = JSON.parse(value); },
            removeItem: () => { result.record = null; },
        },
        performance: { getEntriesByType: () => [{ type: 'navigate' }] },
        requestAnimationFrame: (callback) => { callback(); },
    });
    return { handlers, result, sidebar, brands, card, document };
}

const position = { url: catalogLocationKey(url), productId: '42', y: 900, sidebarY: 120, brandY: 75, pending: true };
const click = (detail, target) => ({ detail, button: 0, target: { closest: () => target } });

test('mouse return and browser Back restore all positions without forcing card focus', () => {
    for (const persisted of [false, true]) {
        const fixture = browser({ ...position, pending: !persisted, keyboard: false });
        fixture.document.activeElement = fixture.card;
        fixture.handlers.pageshow({ persisted });
        assert.equal(fixture.result.focus, 0);
        assert.equal(fixture.result.blur, 1);
        assert.equal(fixture.result['data-catalog-activated'], true);
        assert.equal(fixture.result.scroll, 900);
        assert.equal(fixture.sidebar.scrollTop, 120);
        assert.equal(fixture.brands.scrollTop, 75);
        assert.equal(fixture.result.record, null);
    }
    const keyboardDeparture = browser({ ...position, keyboard: true }, true);
    keyboardDeparture.handlers.back(click(1));
    assert.equal(keyboardDeparture.result.record.keyboard, false);
});

test('keyboard product activation or keyboard return preserves focus restoration', () => {
    const departure = browser();
    departure.handlers.card(click(0, departure.card));
    assert.equal(departure.result.record.keyboard, true);
    const history = browser(departure.result.record);
    history.handlers.pageshow({ persisted: true });
    assert.equal(history.result.focus, 1);
    assert.equal(history.result.blur, 0);
    for (const keyboard of [false, true]) {
        const detail = browser({ ...position, keyboard }, true);
        detail.handlers.back(click(0));
        const returned = browser(detail.result.record);
        returned.handlers.pageshow({ persisted: false });
        assert.equal(returned.result.focus, 1);
        assert.equal(returned.result.scroll, 900);
    }
});

test('pointer departure is saved as pointer; legacy or unrelated records do not force focus', () => {
    const departure = browser();
    departure.handlers.card(click(1, departure.card));
    assert.equal(departure.result.record.keyboard, false);
    const legacy = browser(position);
    legacy.handlers.pageshow({ persisted: false });
    assert.equal(legacy.result.focus, 0);
    assert.equal(legacy.result.scroll, 900);
    const unrelated = browser({ ...position, url: url.replace('page=2', 'page=3'), keyboard: true });
    unrelated.handlers.pageshow({ persisted: false });
    assert.equal(unrelated.result.focus, 0);
    assert.equal(unrelated.result.scroll, null);
});
