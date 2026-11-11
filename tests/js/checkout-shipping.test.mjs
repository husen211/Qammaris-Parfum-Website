import test from 'node:test';
import assert from 'node:assert/strict';
import { cheapest, discountText, distanceKm, locationStep, pinSummary, reasonText } from '../../resources/js/checkout-shipping/logic.js';

test('the cheapest charged ongkir is preselected, not the cheapest courier price', () => {
    const options = [
        { id: 'grab', price: 18000, charged: 18000 },
        { id: 'gojek', price: 20500, charged: 17000 },
    ];
    assert.equal(cheapest(options).id, 'gojek');
    assert.equal(cheapest([]), null);
});

test('the discount line names the store and only appears with a discount', () => {
    assert.equal(discountText({ discount: 3500, percent: 15 }), 'Potongan ongkir 15% dari Qammaris');
    assert.equal(discountText({ discount: 0, percent: 0 }), '');
});

test('the pin summary shows a rounded distance from the store', () => {
    const km = distanceKm([-0.8981125, 119.8594844], [-0.916, 119.886]);
    assert.ok(km > 3 && km < 4, `distance ${km}`);
    assert.match(pinSummary(km), /^Titik antar dipilih · ±3,\d km dari toko$/);
});

test('our pop-up shows only while the browser would still ask for location', () => {
    assert.equal(locationStep('prompt'), 'ask');
    assert.equal(locationStep('granted'), 'locate');
    assert.equal(locationStep('denied'), 'map-blocked');
    assert.equal(locationStep('unsupported'), 'map');
});

test('every failure keeps a way to order', () => {
    assert.match(reasonText('no_courier', 'instant'), /pilih J&T, atau lanjutkan dengan ongkir dikonfirmasi admin/);
    assert.match(reasonText('no_courier', 'jnt'), /lanjutkan dengan ongkir dikonfirmasi admin/);
    assert.match(reasonText('outside_area', 'instant'), /Palu dan sekitarnya/);
    assert.match(reasonText('anything-else', 'instant'), /Pesanan tetap bisa dikirim/);
});
