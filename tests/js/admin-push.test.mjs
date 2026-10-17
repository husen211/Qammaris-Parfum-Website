import assert from 'node:assert/strict';
import test from 'node:test';
import { PUSH_TEXT, pushState, urlBase64ToUint8Array } from '../../resources/js/admin-push.js';

const win = ({ permission = 'default', registration = null, subscription = null, userAgent = 'Chrome', push = true } = {}) => ({
    navigator: { userAgent, serviceWorker: { getRegistration: async () => (registration ? { pushManager: { getSubscription: async () => subscription } } : null) } },
    ...(push ? { PushManager: function PushManager() {}, Notification: { permission } } : {}),
});

test('decodes the VAPID public key to bytes', () => {
    assert.deepEqual([...urlBase64ToUint8Array('AQID_-8')], [1, 2, 3, 255, 239]);
});

test('reports the device state the switch shows', async () => {
    assert.equal(await pushState(win({ push: false })), 'unsupported');
    assert.equal(await pushState(win({ push: false, userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)' })), 'ios-install');
    assert.equal(await pushState(win({ permission: 'denied', registration: true })), 'denied');
    assert.equal(await pushState(win({})), 'no-worker');
    assert.equal(await pushState(win({ registration: true })), 'off');
    assert.equal(await pushState(win({ registration: true, subscription: { endpoint: 'https://push.test/1' } })), 'on');
    // Unsupported browsers and a disabled admin worker show no switch at all.
    assert.equal(PUSH_TEXT.unsupported, undefined);
    assert.equal(PUSH_TEXT['no-worker'], undefined);
    assert.equal(PUSH_TEXT.on[1], 'Matikan');
});
