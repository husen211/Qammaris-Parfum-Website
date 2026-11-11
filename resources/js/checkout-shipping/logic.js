// ORD-13: pure helpers of the checkout courier picker (tested in tests/js/checkout-shipping.test.mjs).

export const formatRupiah = (value) => 'Rp ' + new Intl.NumberFormat('id-ID').format(value);

/** Straight-line distance in km between two [lat, lng] points. */
export function distanceKm([lat1, lng1], [lat2, lng2]) {
    const rad = Math.PI / 180;
    const a = Math.sin(((lat2 - lat1) * rad) / 2) ** 2
        + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(((lng2 - lng1) * rad) / 2) ** 2;

    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export function pinSummary(km) {
    return `Titik antar dipilih · ±${km.toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} km dari toko`;
}

/** The option a customer most likely wants first: the lowest charged ongkir. */
export function cheapest(options) {
    return options.reduce((best, option) => (!best || option.charged < best.charged ? option : best), null);
}

export function discountText(option) {
    return option.discount > 0 ? `Potongan ongkir ${option.percent}% dari Qammaris` : '';
}

/** What to tell the customer when no courier price came back; every case still lets them order. */
export function reasonText(reason, mode) {
    switch (reason) {
        case 'no_courier':
            return mode === 'instant'
                ? 'Kurir instan belum menjangkau titik ini. Geser titiknya, pilih J&T, atau lanjutkan dengan ongkir dikonfirmasi admin.'
                : 'J&T belum menjangkau tujuan ini. Coba tujuan lain, atau lanjutkan dengan ongkir dikonfirmasi admin.';
        case 'outside_area':
            return 'Titik ini di luar area kurir instan (Palu dan sekitarnya). Pilih J&T, atau lanjutkan dengan ongkir dikonfirmasi admin.';
        case 'cart':
            return 'Isi keranjang berubah. Muat ulang halaman ini untuk melihat ringkasan terbaru.';
        case 'busy':
            return 'Terlalu sering menghitung ongkir. Tunggu sebentar, lalu coba lagi.';
        case 'session':
            return 'Sesi checkout habis. Muat ulang halaman ini, lalu pilih kurir lagi.';
        default:
            return 'Ongkir otomatis sedang tidak bisa dihitung. Pesanan tetap bisa dikirim; admin mengonfirmasi ongkir di WhatsApp.';
    }
}

/**
 * First step after "Tentukan titik antar": our own pop-up only when the browser would still ask. Already allowed
 * goes straight to the located map; blocked or unsupported opens the map without asking again.
 */
export function locationStep(permission) {
    return { granted: 'locate', prompt: 'ask', denied: 'map-blocked' }[permission] ?? 'map';
}
