import test from 'node:test';
import assert from 'node:assert/strict';
import { bootGa4, createTracker, safePageContext, readConsent, CONSENT_KEY } from '../../resources/js/ga4.js';

const ID = 'G-TEST123456';
function fixture(choice = null) {
  const scripts = [], deleted = [], handlers = {};
  const button = name => ({ addEventListener: (_, fn) => { handlers[name] = fn; }, focus: () => { handlers.focus = name; } });
  const accept = button('accept'), decline = button('decline'), preferences = { ...button('preferences'), hidden: true };
  const banner = { hidden: true, dataset: { measurementId: ID }, querySelector: s => s.includes('accept') ? accept : decline };
  const doc = {
    referrer: 'https://qammarisparfum.id/pesanan/private-token?phone=private',
    createElement: () => ({}), head: { appendChild: el => scripts.push(el) },
    querySelector: s => s.includes('consent') ? banner : preferences,
    get cookie() { return '_ga=123; _ga_TEST123456=456; qammaris_session=protected; guest_order_history=protected'; },
    set cookie(value) { deleted.push(value); },
  };
  const storage = new Map(choice ? [[CONSENT_KEY, choice]] : []);
  const win = { location: new URL('https://qammarisparfum.id/products?search=private#secret'), localStorage: { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value) } };
  return { doc, win, scripts, deleted, handlers, banner, preferences, storage };
}

test('page context strips query, fragment, private referrer path and page title', () => {
  assert.deepEqual(safePageContext('https://qammarisparfum.id/products?phone=secret#token', 'https://qammarisparfum.id/pesanan/secret?q=private'), {
    page_location: 'https://qammarisparfum.id/products', page_referrer: 'https://qammarisparfum.id', page_title: 'Qammaris Website',
  });
  assert.equal(safePageContext('https://qammarisparfum.id/', 'javascript:secret').page_referrer, '');
});

test('no Google script or data is created before consent or after decline', () => {
  const f = fixture(); bootGa4(f.doc, f.win);
  assert.equal(f.banner.hidden, false); assert.equal(f.scripts.length, 0); assert.equal(f.win.dataLayer, undefined);
  f.handlers.decline();
  assert.equal(f.scripts.length, 0); assert.equal(f.win.dataLayer, undefined);
  assert.equal(f.storage.get(CONSENT_KEY), 'denied');
  assert.equal(f.preferences.hidden, false);
});

test('accept records one safe page view with advertising and automatic page views disabled', () => {
  const f = fixture(); bootGa4(f.doc, f.win); f.handlers.accept(); f.handlers.accept();
  assert.equal(f.scripts.length, 1);
  assert.ok(f.win.dataLayer.every(row => Object.prototype.toString.call(row) === '[object Arguments]'), 'Google command entries must follow the vendor snippet contract');
  assert.equal(f.scripts[0].src, `https://www.googletagmanager.com/gtag/js?id=${ID}`);
  const config = f.win.dataLayer.find(row => row[0] === 'config')[2];
  assert.equal(config.send_page_view, false); assert.equal(config.allow_google_signals, false); assert.equal(config.allow_ad_personalization_signals, false);
  const views = f.win.dataLayer.filter(row => row[0] === 'event'); assert.equal(views.length, 1);
  assert.equal(views[0][2].page_location, 'https://qammarisparfum.id/products');
  assert.ok(!JSON.stringify(f.win.dataLayer).includes('private'));
  const consent = f.win.dataLayer.filter(row => row[0] === 'consent').at(-1)[2];
  assert.equal(consent.analytics_storage, 'granted'); assert.equal(consent.ad_storage, 'denied'); assert.equal(consent.ad_user_data, 'denied'); assert.equal(consent.ad_personalization, 'denied');
});

test('withdrawal disables analytics and clears only GA cookies while preferences remain usable', () => {
  const f = fixture('granted'); bootGa4(f.doc, f.win);
  assert.equal(f.scripts.length, 1); f.handlers.preferences();
  assert.equal(f.banner.hidden, false); assert.equal(f.handlers.focus, 'decline'); f.handlers.decline();
  assert.equal(f.win[`ga-disable-${ID}`], true);
  assert.ok(f.deleted.length > 0); assert.ok(f.deleted.every(cookie => /^_ga(?:_|=)/.test(cookie)));
  assert.equal(f.win.dataLayer.at(-1)[2].analytics_storage, 'denied');
});

test('unavailable storage and invalid measurement IDs safely avoid tracking', () => {
  const f = fixture(); Object.defineProperty(f.win, 'localStorage', { get() { throw new Error('blocked'); } });
  bootGa4(f.doc, f.win); assert.equal(f.scripts.length, 0); f.handlers.decline(); assert.equal(f.scripts.length, 0);
  assert.equal(readConsent({ getItem() { throw new Error('blocked'); } }), null);
  assert.equal(readConsent({ getItem() { return 'unexpected'; } }), null);
  assert.equal(createTracker('G-invalid', f.doc, f.win).allow(), false); assert.equal(f.scripts.length, 0);
});
