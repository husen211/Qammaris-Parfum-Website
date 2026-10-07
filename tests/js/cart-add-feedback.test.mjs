import test from 'node:test';
import assert from 'node:assert/strict';
import { addCartItem, initProductCart } from '../../resources/js/ui/product-cart.js';
import { basketFlightFrames, animateQuantity, flyIntoBasket } from '../../resources/js/ui/cart-motion.js';

test('add feedback uses the real successful server response and preserves quantity/CSRF', async () => {
  const result = await addCartItem('/cart/add', { variant_id: 12, quantity: 2 }, 'synthetic-test-token', async (url, options) => {
    assert.equal(url, '/cart/add');
    assert.equal(options.method, 'POST');
    assert.equal(options.headers['X-CSRF-TOKEN'], 'synthetic-test-token');
    assert.deepEqual(JSON.parse(options.body), { variant_id: 12, quantity: 2 });
    return { ok: true, json: async () => ({ success: true, cart_count: 3 }) };
  });
  assert.equal(result.cart_count, 3);
});

test('sold-out, rejected quantity, expired session and transport errors never return success', async () => {
  for (const message of ['Produk Habis.', 'Jumlah maksimal 99.']) {
    await assert.rejects(addCartItem('/cart/add', {}, '', async () => ({ ok: false, json: async () => ({ success: false, message }) })), { message });
  }
  await assert.rejects(addCartItem('/cart/add', {}, '', async () => ({ ok: false, json: async () => { throw new SyntaxError('HTML'); } })), /Muat ulang/);
  await assert.rejects(addCartItem('/cart/add', {}, '', async () => { throw new Error('offline'); }), /offline/);
});

test('flight travels from product to basket, curves upwards and finishes invisible at target', () => {
  const frames = basketFlightFrames({ x: 200, y: 400 }, { x: 320, y: 30 });
  assert.equal(frames[0].transform, 'translate(200px, 400px) scale(1)');
  assert.match(frames.at(-1).transform, /^translate\(320px, 30px\)/);
  assert.equal(frames.at(-1).opacity, 0);
  const halfway = frames[15].transform.match(/translate\(([\d.]+)px, ([\d.]+)px\)/);
  assert.ok(Number(halfway[2]) < (400 + 30) / 2);
  assert.ok(frames.every(frame => Number.isFinite(frame.offset) && frame.opacity >= 0 && frame.opacity <= 1));
});

test('busy CTA prevents duplicate requests; rejected add restores retry without badge success', async () => {
  const original = { document: globalThis.document, window: globalThis.window, fetch: globalThis.fetch };
  const handlers = {};
  const pageHandlers = {};
  const events = [];
  const states = ['idle', 'loading', 'added'].map(state => ({ dataset: { cartState: state } }));
  const button = { dataset: { cartAddUrl: '/cart/add', variantId: '12' }, disabled: false,
    setAttribute: (key, value) => { button[key] = value; }, querySelectorAll: () => states,
    addEventListener: (key, callback) => { handlers[key] = callback; } };
  const feedback = { textContent: '', classList: { add() {}, remove() {} } };
  let release;
  let calls = 0;
  globalThis.document = { getElementById: () => ({ value: '2' }), querySelectorAll: () => [],
    querySelector: selector => selector === '[data-add-to-cart]' ? button : selector === '[data-cart-feedback]' ? feedback : null };
  globalThis.window = { addEventListener: (key, callback) => { pageHandlers[key] = callback; }, dispatchEvent: event => events.push(event), matchMedia: () => ({ matches: true }) };
  globalThis.fetch = () => { calls++; return new Promise(resolve => { release = resolve; }); };
  try {
    initProductCart();
    const first = handlers.click();
    await handlers.click();
    assert.equal(calls, 1);
    assert.equal(button.dataset.phase, 'loading');
    assert.equal(button.disabled, true);
    release({ ok: false, json: async () => ({ success: false, message: 'Produk Habis.' }) });
    await first;
    assert.equal(button.dataset.phase, 'idle');
    assert.equal(button.disabled, false);
    assert.equal(feedback.textContent, 'Produk Habis.');
    assert.deepEqual(events, []);
  } finally {
    pageHandlers.pageshow?.();
    for (const [key, value] of Object.entries(original)) {
      if (value === undefined) delete globalThis[key]; else globalThis[key] = value;
    }
  }
});

test('reduced motion skips both quantity animation and the flying overlay', () => {
  const original = { document: globalThis.document, window: globalThis.window };
  globalThis.window = { matchMedia: () => ({ matches: true }) };
  globalThis.document = { querySelector: () => ({}), getElementById: () => null,
    createElement: () => { assert.fail('Reduced motion must not create a flight overlay'); } };
  try {
    const noAnimation = { animate: () => { assert.fail('Reduced motion must not animate'); } };
    animateQuantity(noAnimation, 1);
    flyIntoBasket(noAnimation);
  } finally {
    for (const [key, value] of Object.entries(original)) {
      if (value === undefined) delete globalThis[key]; else globalThis[key] = value;
    }
  }
});
