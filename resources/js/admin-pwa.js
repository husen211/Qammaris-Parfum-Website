/*
 * Qammaris Admin PWA page module (ORD-02b). Loaded only by admin pages and the admin login page.
 * Never use Clear-Site-Data here: it is origin-wide and would wipe the public site's cart session
 * and catalog state. Logout removes only caches named qammaris-admin-*.
 */
export const ADMIN_CACHE_PREFIX = 'qammaris-admin-';

export async function clearAdminCaches(cacheStorage = globalThis.caches) {
    if (!cacheStorage) {
        return [];
    }
    const ours = (await cacheStorage.keys()).filter((key) => key.startsWith(ADMIN_CACHE_PREFIX));
    await Promise.all(ours.map((key) => cacheStorage.delete(key)));

    return ours;
}

/** Registers the admin worker, or removes it when the kill switch is off. Other scopes are never touched. */
export async function syncServiceWorker({ enabled, url, scope }, container = globalThis.navigator?.serviceWorker, cacheStorage = globalThis.caches) {
    if (!container) {
        return 'unsupported';
    }
    if (enabled) {
        await container.register(url, { scope });

        return 'registered';
    }
    const registrations = await container.getRegistrations();
    await Promise.all(registrations
        .filter((registration) => new URL(registration.scope).pathname.startsWith(scope))
        .map((registration) => registration.unregister()));
    await clearAdminCaches(cacheStorage);

    return 'unregistered';
}

/**
 * Mutations need the network. Offline submits are stopped before anything is sent, and a second
 * submit while the first is pending is ignored. Returns why a submit was blocked, or null.
 */
export function guardSubmit(form, { online, defaultPrevented, submitter = null, schedule = (fn) => setTimeout(fn, 0) }) {
    // getAttribute: a field named "method" would shadow form.method.
    const method = (form.getAttribute?.('method') ?? form.method ?? 'get').toString().toLowerCase();
    if (defaultPrevented || method !== 'post' || form.dataset.submitGuard === 'off') {
        return null;
    }
    if (!online) {
        return 'offline';
    }
    if (form.dataset.submitting === 'true') {
        return 'pending';
    }
    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    // Disable after the browser has built the form data, so the clicked button's value is still sent.
    schedule(() => {
        const buttons = [...form.querySelectorAll('button[type="submit"], button:not([type])')];
        buttons.forEach((button) => {
            button.disabled = true;
        });
        // Loading state: the pressed button (or the only one) says what is happening.
        const busy = submitter ?? (buttons.length === 1 ? buttons[0] : null);
        if (busy?.dataset?.busyLabel) {
            busy.textContent = busy.dataset.busyLabel;
        }
    });

    return null;
}

function initConnectionNotice(doc, win) {
    const notice = doc.querySelector('[data-admin-offline]');
    if (!notice) {
        return () => {};
    }
    const update = () => {
        notice.hidden = win.navigator.onLine;
    };
    win.addEventListener('online', update);
    win.addEventListener('offline', update);
    update();

    return () => {
        notice.hidden = false;
        notice.focus?.();
    };
}

export function boot(doc = document, win = window) {
    const root = doc.documentElement;
    const showOffline = initConnectionNotice(doc, win);

    doc.addEventListener('submit', (event) => {
        const blocked = guardSubmit(event.target, { online: win.navigator.onLine, defaultPrevented: event.defaultPrevented, submitter: event.submitter ?? null });
        if (blocked) {
            event.preventDefault();
            if (blocked === 'offline') {
                showOffline();
            }
        }
    });

    // Shared store phones: a page restored from the back/forward cache after logout must be re-checked by the server.
    win.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            win.location.reload();
        }
    });

    if (root.dataset.adminClearCaches === 'true') {
        clearAdminCaches().catch(() => {});
    }
    syncServiceWorker({
        enabled: root.dataset.adminPwa === 'on',
        url: root.dataset.adminSw,
        scope: root.dataset.adminScope,
    }).catch(() => {});
}

if (typeof document !== 'undefined' && document.documentElement.dataset.adminPwa) {
    boot();
}
