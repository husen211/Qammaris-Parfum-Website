import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { Script } from 'node:vm';
import test from 'node:test';

const source = readFileSync(new URL('../../resources/js/ui/catalog-link-feedback.js', import.meta.url), 'utf8')
    .replaceAll('export function ', 'function ');

function browser() {
    const events = {};
    const timers = new Map();
    const links = [];
    const status = { hidden: true };
    let clock = 0;
    let id = 0;
    const context = {
        document: {
            addEventListener: (name, listener) => { events[name] = listener; },
            querySelector: () => status,
            querySelectorAll: () => links.filter((link) => link.attributes.has('data-catalog-pressed')),
        },
        window: {
            addEventListener: (name, listener) => { events[name] = listener; },
            setTimeout: (callback, delay) => { timers.set(++id, { callback, at: clock + delay }); return id; },
            clearTimeout: (key) => timers.delete(key),
        },
    };
    new Script(source).runInNewContext(context);
    const link = (catalog = true) => {
        const attributes = new Map();
        const element = {
            attributes, matches: () => catalog,
            setAttribute: (name, value) => attributes.set(name, value),
            removeAttribute: (name) => attributes.delete(name),
            contains: (target) => target === element,
        };
        links.push(element);
        return element;
    };
    const advance = (elapsed) => {
        clock += elapsed;
        for (const [key, timer] of timers) {
            if (timer.at <= clock) { timers.delete(key); timer.callback(); }
        }
    };
    return { ...context, events, link, advance, status };
}

test('click feedback starts immediately, ends after140ms, and loading remains independent', () => {
    const b = browser();
    const link = b.link();
    b.startCatalogLinkFeedback(link);
    assert.equal(link.attributes.has('data-catalog-pressed'), true);
    assert.equal(link.attributes.get('aria-busy'), 'true');
    assert.equal(b.status.hidden, false);
    b.advance(140);
    assert.equal(link.attributes.has('data-catalog-pressed'), false);
    assert.equal(link.attributes.has('data-catalog-activated'), true);
    assert.equal(link.attributes.has('data-navigation-pending'), true);
    assert.equal(b.status.hidden, false);
});

test('history restoration and cancelled navigation clear busy/pressed/loading for a retry', () => {
    for (const cancel of [false, true]) {
        const b = browser();
        const link = b.link();
        b.startCatalogLinkFeedback(link);
        if (cancel) b.advance(10000); else b.events.pageshow();
        for (const name of ['aria-busy', 'data-navigation-pending', 'data-catalog-pressed']) {
            assert.equal(link.attributes.has(name), false);
        }
        assert.equal(b.status.hidden, true);
        b.startCatalogLinkFeedback(link);
        assert.equal(b.status.hidden, false);
        assert.equal(link.attributes.has('data-catalog-pressed'), true);
    }
});

test('neutral pointer state persists inside the same link and releases when the cursor leaves', () => {
    const b = browser();
    const link = b.link();
    b.startCatalogLinkFeedback(link);
    const target = { closest: () => link };
    b.events.pointerout({ target, relatedTarget: link });
    assert.equal(link.attributes.has('data-catalog-activated'), true);
    b.events.pointerout({ target, relatedTarget: null });
    assert.equal(link.attributes.has('data-catalog-activated'), false);
});

test('a repeated or different click replaces feedback and ordinary links stay outside this feature', () => {
    const b = browser();
    const first = b.link();
    const second = b.link();
    b.startCatalogLinkFeedback(first);
    b.startCatalogLinkFeedback(second);
    assert.equal(first.attributes.has('aria-busy'), false);
    assert.equal(first.attributes.has('data-catalog-pressed'), false);
    assert.equal(second.attributes.has('data-catalog-pressed'), true);
    const ordinary = b.link(false);
    b.startCatalogLinkFeedback(ordinary);
    assert.equal(ordinary.attributes.size, 0);
    assert.equal(second.attributes.has('data-navigation-pending'), true);
});

test('native click navigation is never delayed; modified/download/external/same-page clicks have no feedback', () => {
    const navbar = readFileSync(new URL('../../resources/js/ui/navbar.js', import.meta.url), 'utf8')
        .replace(/^import[^\n]+\n/, '');
    let listener;
    let calls = 0;
    let prevented = 0;
    const location = { origin: 'https://qammarisparfum.id', pathname: '/products', search: '?page=2' };
    new Script(navbar).runInNewContext({
        URL,
        startCatalogLinkFeedback: () => { calls++; },
        document: { getElementById: () => null, addEventListener: (_, fn) => { listener = fn; } },
        window: { location, addEventListener: () => {} },
    });
    const link = { href: `${location.origin}/products/hawas?page=2`, target: '', hasAttribute: () => false, setAttribute: () => {} };
    const click = { button: 0, detail: 1, target: { closest: () => link }, preventDefault: () => { prevented++; } };
    listener(click);
    assert.equal(calls, 1);
    for (const changed of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }, { defaultPrevented: true }]) {
        listener({ ...click, ...changed });
    }
    for (const changed of [{ target: '_blank' }, { href: 'https://example.com' }, { href: `${location.origin}/products?page=2#filter` }, { hasAttribute: () => true }]) {
        listener({ ...click, target: { closest: () => ({ ...link, ...changed }) } });
    }
    assert.equal(calls, 1);
    assert.equal(prevented, 0);
});
