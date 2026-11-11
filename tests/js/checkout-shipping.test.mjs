import test from 'node:test';
import assert from 'node:assert/strict';
import { cheapest, discountText, distanceKm, locationStep, locationText, pinSummary, placeResults, reasonText } from '../../resources/js/checkout-shipping/logic.js';

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

test('every location outcome tells the customer how to still place the pin', () => {
    assert.match(locationText('denied'), /Cari alamat di atas atau geser peta/);
    assert.match(locationText('denied'), /izinkan Lokasi/);
    assert.match(locationText('unavailable'), /GPS/);
    assert.match(locationText('found'), /Geser peta/);
    assert.match(locationText(undefined), /Cari alamat, pilih kecamatan, atau geser peta/);
});

test('place search results become labelled points, without duplicates or pointless rows', () => {
    const results = placeResults({ features: [
        { geometry: { coordinates: [119.8796, -0.9189] }, properties: { name: 'Jalan Basuki Rahmat', district: 'Tatura Selatan', city: 'Palu' } },
        { geometry: { coordinates: [119.8796, -0.9189] }, properties: { name: 'Jalan Basuki Rahmat', district: 'Tatura Selatan', city: 'Palu' } },
        { geometry: { coordinates: [119.8947, -0.8362] }, properties: { name: 'Universitas Tadulako', street: 'Jalan Tekukur', locality: 'Boyangapa' } },
        { geometry: { coordinates: [119.86, -0.9] }, properties: { street: 'Jalan Sis Aljufri', housenumber: '28' } },
        { geometry: { coordinates: [119.8888, -0.917] }, properties: { name: 'Jalan Basuki Rahmat I', district: 'RT 02', locality: 'Birobuli Utara' } },
        { geometry: { coordinates: [119.86, -0.9] }, properties: {} },
        { geometry: {}, properties: { name: 'Tanpa titik' } },
    ] });
    assert.deepEqual(results, [
        { label: 'Jalan Basuki Rahmat', detail: 'Tatura Selatan, Palu', point: [-0.9189, 119.8796] },
        { label: 'Universitas Tadulako', detail: 'Jalan Tekukur, Boyangapa', point: [-0.8362, 119.8947] },
        { label: 'Jalan Sis Aljufri 28', detail: '', point: [-0.9, 119.86] },
        { label: 'Jalan Basuki Rahmat I', detail: 'Birobuli Utara', point: [-0.917, 119.8888] },
    ]);
    assert.deepEqual(placeResults(null), []);
});
