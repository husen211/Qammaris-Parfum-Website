import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { clearPublicCaches, syncPublicServiceWorker } from '../../resources/js/public-pwa.js';

// PWA-01: the public worker caches only static public files and never touches admin traffic or caches.
const ORIGIN = 'https://qammaris.test';
const SOURCE = readFileSync(new URL('../../resources/js/public-sw.js', import.meta.url), 'utf8');

class FakeResponse {
    constructor(body = '', { status = 200, type = 'basic', headers = {} } = {}) {
        Object.assign(this, { body, status, type, headers, ok: status >= 200 && status < 300 });
    }

    clone() { return new FakeResponse(this.body, this); }

    async text() { return this.body; }

    static error() { return new FakeResponse('', { status: 0, type: 'error' }); }
}

function fakeCaches(seed = {}) {
    const stores = new Map(Object.entries(seed).map(([name, entries]) => [name, new Map(entries)]));
    const key = (request) => (typeof request === 'string' ? new URL(request, ORIGIN).href : request.url);
    const open = async (name) => {
        if (!stores.has(name)) stores.set(name, new Map());
        const store = stores.get(name);
        return {
            match: async (request) => store.get(key(request)),
            put: async (request, response) => { store.set(key(request), response); },
            addAll: async (urls) => {
                for (const url of urls) store.set(key(url), new FakeResponse(`static ${url}`));
            },
        };
    };
    return {
        stores,
        open,
        keys: async () => [...stores.keys()],
        delete: async (name) => stores.delete(name),
        match: async (request) => {
            for (const store of stores.values()) {
                const hit = store.get(key(request));
                if (hit) return hit;
            }
            return undefined;
        },
    };
}

function loadWorker({ enabled = true, version = 'v2', network = async () => new FakeResponse('net'), caches = fakeCaches() } = {}) {
    const listeners = {};
    const calls = { fetch: [], unregistered: 0, claimed: 0, skipped: 0 };
    const self = {
        location: new URL(`${ORIGIN}/sw.js`),
        addEventListener: (type, fn) => { listeners[type] = fn; },
        skipWaiting: async () => { calls.skipped++; },
        clients: { claim: async () => { calls.claimed++; } },
        registration: { unregister: async () => { calls.unregistered++; return true; } },
    };
    const context = vm.createContext({
        self, caches, URL, Response: FakeResponse,
        fetch: async (request) => {
            calls.fetch.push(request);
            return network(request);
        },
    });
    vm.runInContext(`const PUBLIC_SW = ${JSON.stringify({ version, enabled, offlineUrl: '/offline' })};\n${SOURCE}`, context);

    const dispatch = async (type, data = {}) => {
        let waited; let responded;
        const event = { ...data, waitUntil: (p) => { waited = p; }, respondWith: (p) => { responded = p; } };
        listeners[type]?.(event);
        await waited;
        return { responded: responded === undefined ? undefined : await responded, intercepted: responded !== undefined };
    };
    return { dispatch, calls, caches, listeners };
}

const request = (path, { method = 'GET', mode = 'cors' } = {}) => ({ url: path.startsWith('http') ? path : `${ORIGIN}${path}`, method, mode });
const cachedUrls = (caches) => [...caches.stores.values()].flatMap((store) => [...store.keys()]);

test('install precaches only the offline page and public icon; activation keeps admin caches', async () => {
    const caches = fakeCaches({
        'qammaris-public-static-v1': [[`${ORIGIN}/old.js`, new FakeResponse('old')]],
        'qammaris-admin-static-a1': [[`${ORIGIN}/admin/offline`, new FakeResponse('admin')]],
        'other-app': [],
    });
    const worker = loadWorker({ caches });
    await worker.dispatch('install');
    await worker.dispatch('activate');

    assert.deepEqual([...caches.stores.keys()].sort(), ['other-app', 'qammaris-admin-static-a1', 'qammaris-public-static-v2']);
    assert.deepEqual([...caches.stores.get('qammaris-public-static-v2').keys()].sort(), [`${ORIGIN}/images/pwa/public-icon-192.png`, `${ORIGIN}/offline`]);
    assert.equal(worker.calls.claimed, 1);
});

test('public pages, cart, checkout and order links are network-only and never cached', async () => {
    const worker = loadWorker({ network: async () => new FakeResponse('<html>Nama penerima, alamat</html>') });
    for (const path of ['/', '/products', '/cart', '/cart/checkout', '/pesanan-saya', '/pesanan/abc123token', '/products/mykonos?x=1']) {
        const { intercepted, responded } = await worker.dispatch('fetch', { request: request(path, { mode: 'navigate' }) });
        assert.equal(intercepted, true, path);
        assert.equal(responded.body, '<html>Nama penerima, alamat</html>', path);
    }
    // JSON and other same-origin data requests are left to the browser.
    for (const path of ['/cart/data', '/products?format=json', '/sitemap.xml', '/storage/products/a.jpg', '/images/logo.png']) {
        assert.equal((await worker.dispatch('fetch', { request: request(path) })).intercepted, false, path);
    }
    assert.deepEqual(cachedUrls(worker.caches), []);
});

test('admin, login, staff links, integrations and every non-GET request pass through untouched', async () => {
    const worker = loadWorker();
    const untouched = [
        request('/admin', { mode: 'navigate' }), request('/admin/orders', { mode: 'navigate' }), request('/admin/login', { mode: 'navigate' }),
        request('/admin/sw.js'), request('/admin/manifest.webmanifest'), request('/login', { mode: 'navigate' }),
        request('/tugas-pesanan/abc', { mode: 'navigate' }), request('/integrations/qammaris-app/orders/v1/orders'),
        request('/cart/checkout', { method: 'POST', mode: 'navigate' }), request('/cart/add', { method: 'POST' }),
        request('/cart/update/1', { method: 'PUT' }), request('/cart/remove/1', { method: 'DELETE' }),
        request('/pesanan/abc', { method: 'POST', mode: 'navigate' }), request('/pesanan-saya/lupakan', { method: 'POST', mode: 'navigate' }),
        request('https://fonts.googleapis.com/css2?family=Inter'), request('https://wa.me/6285144924931', { mode: 'navigate' }),
    ];
    for (const req of untouched) {
        assert.equal((await worker.dispatch('fetch', { request: req })).intercepted, false, `${req.method} ${req.url}`);
    }
    assert.equal(worker.calls.fetch.length, 0);
    // A public path that merely starts with the same letters is still a public page.
    assert.equal((await worker.dispatch('fetch', { request: request('/administrasi', { mode: 'navigate' }) })).intercepted, true);
});

test('fingerprinted build assets and public icons are cache-first in the public cache only', async () => {
    const worker = loadWorker({ network: async (req) => new FakeResponse(`asset ${req.url}`) });
    await worker.dispatch('install');
    const asset = request('/build/assets/app-3f2a1b.js');
    assert.equal((await worker.dispatch('fetch', { request: asset })).responded.body, `asset ${ORIGIN}/build/assets/app-3f2a1b.js`);
    await worker.dispatch('fetch', { request: asset });
    assert.equal(worker.calls.fetch.length, 1);
    await worker.dispatch('fetch', { request: request('/images/pwa/public-icon-512.png') });
    assert.equal((await worker.dispatch('fetch', { request: request('/images/pwa/admin-icon-512.png') })).intercepted, false);

    assert.deepEqual([...worker.caches.stores.keys()], ['qammaris-public-static-v2']);
    for (const url of cachedUrls(worker.caches)) {
        assert.match(url, /\/(build\/assets\/|images\/pwa\/public-|offline$)/);
    }
});

test('failed and error asset responses are not cached', async () => {
    const worker = loadWorker({ network: async () => new FakeResponse('nope', { status: 404 }) });
    await worker.dispatch('fetch', { request: request('/build/assets/missing.js') });
    assert.deepEqual(cachedUrls(worker.caches), []);
});

test('offline navigation shows the static offline page and sends nothing else', async () => {
    const worker = loadWorker({ network: async () => { throw new TypeError('Failed to fetch'); } });
    await worker.dispatch('install');
    const { responded } = await worker.dispatch('fetch', { request: request('/cart', { mode: 'navigate' }) });
    assert.equal(responded.body, 'static /offline');
    assert.equal(worker.calls.fetch.length, 1);

    // Without a precached page the browser shows its own error instead of anything stale.
    const cold = loadWorker({ network: async () => { throw new TypeError('offline'); } });
    assert.equal((await cold.dispatch('fetch', { request: request('/', { mode: 'navigate' }) })).responded.type, 'error');
});

test('kill switch worker removes public caches only and unregisters without a fetch handler', async () => {
    const caches = fakeCaches({ 'qammaris-public-static-v1': [], 'qammaris-admin-static-a1': [], 'other-app': [] });
    const worker = loadWorker({ enabled: false, caches });
    assert.equal(worker.listeners.fetch, undefined);
    await worker.dispatch('install');
    await worker.dispatch('activate');
    assert.deepEqual([...caches.stores.keys()].sort(), ['other-app', 'qammaris-admin-static-a1']);
    assert.equal(worker.calls.unregistered, 1);
});

test('registration uses the root scope; the kill switch unregisters only the root worker', async () => {
    const registered = [];
    const unregistered = [];
    const registration = (scope) => ({ scope: `${ORIGIN}${scope}`, unregister: async () => { unregistered.push(scope); return true; } });
    const container = {
        register: async (url, options) => { registered.push([url, options.scope]); },
        getRegistrations: async () => [registration('/admin'), registration('/'), registration('/admin/')],
    };
    const caches = fakeCaches({ 'qammaris-public-static-v1': [], 'qammaris-admin-static-a1': [] });

    assert.equal(await syncPublicServiceWorker({ enabled: true, url: '/sw.js', scope: '/' }, container, caches), 'registered');
    assert.deepEqual(registered, [['/sw.js', '/']]);

    assert.equal(await syncPublicServiceWorker({ enabled: false, url: '/sw.js', scope: '/' }, container, caches), 'unregistered');
    assert.deepEqual(unregistered, ['/']);
    assert.deepEqual([...caches.stores.keys()], ['qammaris-admin-static-a1']);

    const adminOnly = { getRegistrations: async () => [registration('/admin')] };
    assert.equal(await syncPublicServiceWorker({ enabled: false, url: '/sw.js', scope: '/' }, adminOnly, caches), 'none');
    assert.equal(await syncPublicServiceWorker({ enabled: true, url: '/sw.js', scope: '/' }, undefined, caches), 'unsupported');
});

test('cache clearing deletes only qammaris-public-* caches', async () => {
    const caches = fakeCaches({ 'qammaris-public-static-v1': [], 'qammaris-public-static-v2': [], 'qammaris-admin-static-a1': [] });
    assert.deepEqual((await clearPublicCaches(caches)).sort(), ['qammaris-public-static-v1', 'qammaris-public-static-v2']);
    assert.deepEqual([...caches.stores.keys()], ['qammaris-admin-static-a1']);
});

test('the public worker has no push, sync or notification code', () => {
    for (const forbidden of ['push', 'notification', 'Notification', 'sync', 'Clear-Site-Data', 'localStorage', 'indexedDB']) {
        assert.equal(SOURCE.includes(forbidden), false, forbidden);
    }
});
