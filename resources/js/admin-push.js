/*
 * ORD-09 (Owner 2026-10-17): this device receives a push when someone orders on the website.
 * The admin service worker (scope /admin) shows it; this module only subscribes, unsubscribes and reports state.
 */

/** VAPID public key (base64url) -> bytes for PushManager.subscribe. */
export function urlBase64ToUint8Array(value) {
    const padded = (value + '='.repeat((4 - (value.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const raw = globalThis.atob(padded);

    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
}

/** @returns {'unsupported'|'ios-install'|'denied'|'no-worker'|'on'|'off'} */
export async function pushState(win = globalThis, scope = '/admin') {
    const nav = win.navigator;
    if (!nav?.serviceWorker || !('PushManager' in win) || !('Notification' in win)) {
        // iPhone/iPad: Web Push only works from the app added to the Home Screen.
        return /iPhone|iPad|iPod/.test(nav?.userAgent ?? '') ? 'ios-install' : 'unsupported';
    }
    if (win.Notification.permission === 'denied') {
        return 'denied';
    }
    const registration = await nav.serviceWorker.getRegistration(scope);
    if (!registration) {
        return 'no-worker';
    }

    return (await registration.pushManager.getSubscription()) ? 'on' : 'off';
}

export const PUSH_TEXT = {
    'on': ['Aktif di perangkat ini. Ada pesanan dari website → muncul notifikasi.', 'Matikan'],
    'off': ['Belum aktif di perangkat ini.', 'Aktifkan notifikasi'],
    'denied': ['Diblokir browser. Izinkan notifikasi untuk situs ini di pengaturan browser, lalu muat ulang.', null],
    'ios-install': ['Di iPhone: Bagikan → Tambahkan ke Layar Utama, lalu buka dari ikon QAM Admin untuk mengaktifkan.', null],
};

async function send(url, method, csrf, body) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(body),
    });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response;
}

export function initAdminPush(doc = document, win = window) {
    const root = doc.documentElement;
    // Sidebar (desktop) and order list (phone) each have a switch; they show the same device state.
    const boxes = [...doc.querySelectorAll('[data-admin-push]')];
    const key = root.dataset.adminPushKey;
    const url = root.dataset.adminPushUrl;
    if (boxes.length === 0 || !key || !url) {
        return;
    }
    const scope = root.dataset.adminScope || '/admin';
    const csrf = boxes[0].dataset.csrf;
    const testUrl = boxes[0].dataset.testUrl;

    const render = (state, note = null) => {
        const [text, action] = PUSH_TEXT[state] ?? [null, null];
        boxes.forEach((box) => {
            const toggle = box.querySelector('[data-admin-push-toggle]');
            // Unsupported browsers and a disabled admin worker show nothing rather than a dead button.
            box.hidden = text === null;
            box.querySelector('[data-admin-push-status]').textContent = note ?? text ?? '';
            toggle.hidden = action === null;
            toggle.textContent = action ?? '';
            toggle.disabled = false;
            box.querySelector('[data-admin-push-test]').hidden = state !== 'on';
        });
    };
    const say = (note) => boxes.forEach((box) => { box.querySelector('[data-admin-push-status]').textContent = note; });

    const subscription = async () => (await win.navigator.serviceWorker.getRegistration(scope))?.pushManager.getSubscription();

    const refresh = async () => {
        const state = await pushState(win, scope);
        // Shared devices: whoever is signed in now owns this browser's subscription.
        if (state === 'on') {
            const current = await subscription();
            send(url, 'POST', csrf, current.toJSON()).catch(() => {});
        }
        render(state);
    };

    const toggle = async () => {
        boxes.forEach((box) => { box.querySelector('[data-admin-push-toggle]').disabled = true; });
        try {
            const current = await subscription();
            if (current) {
                await send(url, 'DELETE', csrf, { endpoint: current.endpoint }).catch(() => {});
                await current.unsubscribe();
                render('off', 'Notifikasi dimatikan di perangkat ini.');

                return;
            }
            if ((await win.Notification.requestPermission()) !== 'granted') {
                render(await pushState(win, scope));

                return;
            }
            const registration = await win.navigator.serviceWorker.getRegistration(scope);
            const created = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(key) });
            await send(url, 'POST', csrf, created.toJSON());
            render('on');
        } catch (error) {
            render(await pushState(win, scope).catch(() => 'off'), 'Belum berhasil. Periksa koneksi lalu coba lagi.');
        }
    };

    const test = async (button) => {
        button.disabled = true;
        try {
            await send(testUrl, 'POST', csrf, {});
            say('Tes dikirim. Notifikasi muncul dalam beberapa detik.');
        } catch (error) {
            say('Tes belum terkirim. Coba lagi sebentar.');
        } finally {
            button.disabled = false;
        }
    };

    boxes.forEach((box) => {
        box.querySelector('[data-admin-push-toggle]').addEventListener('click', toggle);
        const button = box.querySelector('[data-admin-push-test]');
        button.addEventListener('click', () => test(button));
    });

    refresh().catch(() => render('unsupported'));
}
