import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { clearAdminCaches, guardSubmit, syncServiceWorker } from '../../resources/js/admin-pwa.js';

const ORIGIN = 'https://qammaris.test';
const SOURCE = readFileSync(new URL('../../resources/js/admin-sw.js', import.meta.url), 'utf8');

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

function loadWorker({ enabled = true, version = 'v2', network, caches = fakeCaches() } = {}) {
    const listeners = {};
    const calls = { fetch: [], unregistered: 0, claimed: 0, skipped: 0 };
    const self = {
        location: new URL(`${ORIGIN}/admin/sw.js`),
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
    vm.runInContext(`const ADMIN_SW = ${JSON.stringify({ version, enabled, offlineUrl: '/admin/offline' })};\n${SOURCE}`, context);

    const dispatch = async (type, data = {}) => {
        let waited; let responded;
        const event = { ...data, waitUntil: (p) => { waited = p; }, respondWith: (p) => { responded = p; } };
        listeners[type]?.(event);
        await waited;
        return { responded: responded === undefined ? undefined : await responded, intercepted: responded !== undefined };
    };
    return { dispatch, calls, caches, listeners };
}

const request = (path, { method = 'GET', mode = 'cors' } = {}) => ({ url: `${ORIGIN}${path}`, method, mode });
const offline = async () => { throw new TypeError('Failed to fetch'); };

test('install precaches only the static offline page and icon, then activation drops older admin caches only', async () => {
    const caches = fakeCaches({ 'qammaris-admin-static-v1': [], 'public-site-cache': [], 'other-app': [] });
    const worker = loadWorker({ caches, network: offline });

    await worker.dispatch('install');
    assert.deepEqual([...caches.stores.get('qammaris-admin-static-v2').keys()], [`${ORIGIN}/admin/offline`, `${ORIGIN}/images/pwa/admin-icon-192.png`]);
    await worker.dispatch('activate');
    assert.deepEqual([...caches.stores.keys()].sort(), ['other-app', 'public-site-cache', 'qammaris-admin-static-v2']);
    assert.equal(worker.calls.claimed, 1);
});

test('admin pages are network-only and never written to a cache, even when they succeed', async () => {
    const worker = loadWorker({ network: async () => new FakeResponse('<h1>QAM-0012 Siti 0812</h1>') });
    await worker.dispatch('install');

    const { responded } = await worker.dispatch('fetch', { request: request('/admin/orders/12', { mode: 'navigate' }) });
    assert.match(responded.body, /QAM-0012/);
    const cached = [...worker.caches.stores.values()].flatMap((store) => [...store.values()]).map((response) => response.body);
    assert.ok(cached.every((body) => !body.includes('QAM-0012')), 'personal page data must not be cached');
});

test('JSON, uploads and form posts are not intercepted at all', async () => {
    const worker = loadWorker({ network: async () => new FakeResponse('{}') });
    for (const req of [
        request('/admin/orders/product-search?q=hawas'),
        request('/storage/payment-proofs/1.jpg'),
        request('/admin/orders', { method: 'POST' }),
        request('/build/assets/app-123.js', { method: 'POST' }),
        { url: 'https://fonts.googleapis.com/css2?family=Inter', method: 'GET', mode: 'cors' },
    ]) {
        const { intercepted } = await worker.dispatch('fetch', { request: req });
        assert.equal(intercepted, false, req.url);
    }
});

test('fingerprinted build assets and admin icons are cache-first in the admin cache', async () => {
    let served = 0;
    const worker = loadWorker({ network: async () => { served++; return new FakeResponse('css'); } });
    const asset = request('/build/assets/app-DKvjYVO4.css');

    assert.equal((await worker.dispatch('fetch', { request: asset })).responded.body, 'css');
    assert.equal((await worker.dispatch('fetch', { request: asset })).responded.body, 'css');
    assert.equal(served, 1);
    assert.ok(worker.caches.stores.get('qammaris-admin-static-v2').has(asset.url));

    const failed = loadWorker({ network: async () => new FakeResponse('missing', { status: 404 }) });
    await failed.dispatch('fetch', { request: request('/build/assets/gone.js') });
    assert.equal(failed.caches.stores.get('qammaris-admin-static-v2').size, 0, 'errors are never cached');
});

test('offline navigation shows the static page; a failed form post is flagged so it is never re-posted', async () => {
    const caches = fakeCaches();
    const worker = loadWorker({ caches, network: offline });
    await worker.dispatch('install');
    caches.stores.get('qammaris-admin-static-v2').set(`${ORIGIN}/admin/offline`, new FakeResponse('<body data-request-method="GET">Tidak ada koneksi</body>'));

    const get = await worker.dispatch('fetch', { request: request('/admin/orders', { mode: 'navigate' }) });
    assert.match(get.responded.body, /data-request-method="GET"/);

    const post = await worker.dispatch('fetch', { request: request('/admin/orders', { method: 'POST', mode: 'navigate' }) });
    assert.match(post.responded.body, /data-request-method="POST"/);
    assert.equal(post.responded.headers['Content-Type'], 'text/html; charset=UTF-8');
});

test('kill switch worker removes admin caches and unregisters without a fetch handler', async () => {
    const caches = fakeCaches({ 'qammaris-admin-static-v1': [], 'public-site-cache': [] });
    const worker = loadWorker({ enabled: false, caches, network: offline });
    assert.equal(worker.listeners.fetch, undefined);
    await worker.dispatch('install');
    await worker.dispatch('activate');
    assert.deepEqual([...caches.stores.keys()], ['public-site-cache']);
    assert.equal(worker.calls.unregistered, 1);
});

test('logout cache clearing deletes only qammaris-admin-* caches', async () => {
    const caches = fakeCaches({ 'qammaris-admin-static-a': [], 'qammaris-admin-static-b': [], 'public-site-cache': [] });
    assert.deepEqual(await clearAdminCaches(caches), ['qammaris-admin-static-a', 'qammaris-admin-static-b']);
    assert.deepEqual([...caches.stores.keys()], ['public-site-cache']);
    assert.deepEqual(await clearAdminCaches(undefined), []);
});

test('registration uses the admin scope; the kill switch unregisters only admin-scoped workers', async () => {
    const registered = [];
    const unregistered = [];
    const container = {
        register: async (url, options) => { registered.push([url, options]); },
        getRegistrations: async () => [
            { scope: `${ORIGIN}/admin`, unregister: async () => unregistered.push('admin') },
            { scope: `${ORIGIN}/`, unregister: async () => unregistered.push('public') },
        ],
    };
    assert.equal(await syncServiceWorker({ enabled: true, url: '/admin/sw.js', scope: '/admin' }, container), 'registered');
    assert.deepEqual(registered, [['/admin/sw.js', { scope: '/admin' }]]);

    const caches = fakeCaches({ 'qammaris-admin-static-a': [], 'public-site-cache': [] });
    assert.equal(await syncServiceWorker({ enabled: false, url: '/admin/sw.js', scope: '/admin' }, container, caches), 'unregistered');
    assert.deepEqual(unregistered, ['admin']);
    assert.deepEqual([...caches.stores.keys()], ['public-site-cache']);
    assert.equal(await syncServiceWorker({ enabled: true, url: '/admin/sw.js', scope: '/admin' }, undefined), 'unsupported');
});

test('submit guard blocks offline and repeated submits, and keeps the clicked button value', () => {
    const buttons = [{ disabled: false }, { disabled: false }];
    const form = () => ({
        method: 'post', dataset: {}, attributes: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        querySelectorAll: () => buttons,
    });
    const scheduled = [];
    const schedule = (fn) => scheduled.push(fn);

    const offlineForm = form();
    assert.equal(guardSubmit(offlineForm, { online: false, defaultPrevented: false, schedule }), 'offline');
    assert.equal(offlineForm.dataset.submitting, undefined, 'an offline attempt can be retried later');

    const onlineForm = form();
    assert.equal(guardSubmit(onlineForm, { online: true, defaultPrevented: false, schedule }), null);
    assert.equal(onlineForm.attributes['aria-busy'], 'true');
    assert.ok(buttons.every((button) => !button.disabled), 'buttons stay enabled until the form data is built');
    scheduled.forEach((fn) => fn());
    assert.ok(buttons.every((button) => button.disabled));
    assert.equal(guardSubmit(onlineForm, { online: true, defaultPrevented: false, schedule }), 'pending');

    assert.equal(guardSubmit({ ...form(), method: 'get' }, { online: false, defaultPrevented: false, schedule }), null, 'GET filters are not mutations');
    assert.equal(guardSubmit(form(), { online: false, defaultPrevented: true, schedule }), null, 'a cancelled confirm() is left alone');
    assert.equal(guardSubmit({ ...form(), dataset: { submitGuard: 'off' } }, { online: false, defaultPrevented: false, schedule }), null);
});
