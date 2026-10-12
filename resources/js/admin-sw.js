/*
 * Qammaris Admin service worker (ORD-02b), served at /admin/sw.js with scope /admin.
 * AdminPwaController prepends `const ADMIN_SW = { version, enabled, offlineUrl }`.
 *
 * Privacy rule: only fingerprinted build assets, admin icons and the static offline page are cached.
 * Admin HTML, JSON, uploads and form responses always go to the network and are never stored.
 */
/* global ADMIN_SW */
const PREFIX = 'qammaris-admin-';
const CACHE = `${PREFIX}static-${ADMIN_SW.version}`;
const OFFLINE_URL = ADMIN_SW.offlineUrl;
const PRECACHE = [OFFLINE_URL, '/images/pwa/admin-icon-192.png'];

function clearAdminCaches(keep) {
    return caches.keys().then((keys) => Promise.all(
        keys.filter((key) => key.startsWith(PREFIX) && key !== keep).map((key) => caches.delete(key)),
    ));
}

function offlinePage(method) {
    return caches.match(OFFLINE_URL).then((page) => {
        if (!page) {
            return Response.error();
        }
        if (method === 'GET') {
            return page;
        }

        // Tell the page a form submission failed, so it never offers to re-post it.
        return page.text().then((html) => new Response(
            html.replace('data-request-method="GET"', 'data-request-method="POST"'),
            { headers: { 'Content-Type': 'text/html; charset=UTF-8' } },
        ));
    });
}

function isStaticAsset(request) {
    const url = new URL(request.url);

    return request.method === 'GET'
        && url.origin === self.location.origin
        && (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/images/pwa/'));
}

if (!ADMIN_SW.enabled) {
    // Kill switch: installed copies replace themselves with this script, drop their caches and unregister.
    self.addEventListener('install', () => self.skipWaiting());
    self.addEventListener('activate', (event) => {
        event.waitUntil(clearAdminCaches(null).then(() => self.registration.unregister()));
    });
} else {
    self.addEventListener('install', (event) => {
        event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
    });

    self.addEventListener('activate', (event) => {
        event.waitUntil(clearAdminCaches(CACHE).then(() => self.clients.claim()));
    });

    self.addEventListener('fetch', (event) => {
        const { request } = event;

        if (request.mode === 'navigate') {
            // Network only. The fallback is a static page without customer or account data.
            event.respondWith(fetch(request).catch(() => offlinePage(request.method)));
            return;
        }

        if (!isStaticAsset(request)) {
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
