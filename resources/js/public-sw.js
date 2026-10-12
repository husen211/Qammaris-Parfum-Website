/*
 * Qammaris Perfumes public service worker (PWA-01), served at /sw.js with scope /.
 * PublicPwaController prepends `const PUBLIC_SW = { version, enabled, offlineUrl }`.
 *
 * Privacy rule: only fingerprinted build assets, public PWA icons and the static offline page are cached,
 * in caches named qammaris-public-*. HTML, cart, checkout, order links, JSON and every non-GET request
 * go to the network and are never stored. Admin, login, staff links and integrations are not intercepted
 * at all (the Admin PWA has its own worker at /admin). Admin caches and other storage are never touched.
 */
/* global PUBLIC_SW */
const PREFIX = 'qammaris-public-';
const CACHE = `${PREFIX}static-${PUBLIC_SW.version}`;
const OFFLINE_URL = PUBLIC_SW.offlineUrl;
const PRECACHE = [OFFLINE_URL, '/images/pwa/public-icon-192.png'];
const BYPASS = ['/admin', '/login', '/logout', '/integrations', '/api', '/tugas-pesanan', '/sanctum', '/up'];

function clearPublicCaches(keep) {
    return caches.keys().then((keys) => Promise.all(
        keys.filter((key) => key.startsWith(PREFIX) && key !== keep).map((key) => caches.delete(key)),
    ));
}

function isBypassed(url) {
    return BYPASS.some((prefix) => url.pathname === prefix || url.pathname.startsWith(`${prefix}/`));
}

function isStaticAsset(url) {
    return url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/images/pwa/public-');
}

function offlinePage() {
    return caches.match(OFFLINE_URL).then((page) => page || Response.error());
}

if (!PUBLIC_SW.enabled) {
    // Kill switch: installed copies replace themselves with this script, drop the public caches and unregister.
    self.addEventListener('install', () => self.skipWaiting());
    self.addEventListener('activate', (event) => {
        event.waitUntil(clearPublicCaches(null).then(() => self.registration.unregister()));
    });
} else {
    self.addEventListener('install', (event) => {
        event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
    });

    self.addEventListener('activate', (event) => {
        event.waitUntil(clearPublicCaches(CACHE).then(() => self.clients.claim()));
    });

    self.addEventListener('fetch', (event) => {
        const { request } = event;
        const url = new URL(request.url);

        // Forms (checkout, order updates) and anything private or cross-origin: the browser handles it untouched.
        if (request.method !== 'GET' || url.origin !== self.location.origin || isBypassed(url)) {
            return;
        }

        if (request.mode === 'navigate') {
            // Network only. The fallback is a static page without customer data; nothing is resent from it.
            event.respondWith(fetch(request).catch(() => offlinePage()));
            return;
        }

        if (!isStaticAsset(url)) {
            return;
        }

        event.respondWith(caches.open(CACHE).then((cache) => cache.match(request).then((hit) => hit || fetch(request).then((response) => {
            if (response.ok && response.type === 'basic') {
                cache.put(request, response.clone());
            }

            return response;
        }))));
    });
}
