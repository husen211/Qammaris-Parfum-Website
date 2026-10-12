/*
 * Qammaris Perfumes public PWA page module (PWA-01). Loaded only by the public layout.
 * It only ever registers or removes the root-scope public worker and caches named qammaris-public-*.
 * The Admin PWA worker (/admin), admin caches, cookies and other storage are never touched; no Clear-Site-Data.
 */
export const PUBLIC_CACHE_PREFIX = 'qammaris-public-';

export async function clearPublicCaches(cacheStorage = globalThis.caches) {
    if (!cacheStorage) {
        return [];
    }
    const ours = (await cacheStorage.keys()).filter((key) => key.startsWith(PUBLIC_CACHE_PREFIX));
    await Promise.all(ours.map((key) => cacheStorage.delete(key)));

    return ours;
}

/** Registers the public worker, or removes it when the kill switch is off. Only the exact scope is touched. */
export async function syncPublicServiceWorker({ enabled, url, scope }, container = globalThis.navigator?.serviceWorker, cacheStorage = globalThis.caches) {
    if (!container) {
        return 'unsupported';
    }
    if (enabled) {
        await container.register(url, { scope });

        return 'registered';
    }
    const ours = (await container.getRegistrations())
        .filter((registration) => new URL(registration.scope).pathname === scope);
    if (ours.length === 0) {
        return 'none';
    }
    await Promise.all(ours.map((registration) => registration.unregister()));
    await clearPublicCaches(cacheStorage);

    return 'unregistered';
}

function init(doc, win) {
    const root = doc.documentElement;
    const config = {
        enabled: root.dataset.publicPwa === 'on',
        url: root.dataset.publicSw,
        scope: root.dataset.publicScope,
    };
    if (!config.url || !config.scope) {
        return;
    }
    // After load, so registration never competes with the first paint of the catalog.
    const run = () => syncPublicServiceWorker(config).catch(() => {});
    if (doc.readyState === 'complete') {
        run();
    } else {
        win.addEventListener('load', run, { once: true });
    }
}

if (typeof document !== 'undefined' && typeof window !== 'undefined') {
    init(document, window);
}
