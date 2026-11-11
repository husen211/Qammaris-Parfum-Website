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

/** What the map tells the customer after each location outcome; the map stays usable in every case. */
export function locationText(outcome) {
    return {
        found: 'Lokasimu ditemukan. Geser peta bila pin belum tepat di titik antar.',
        denied: 'Izin lokasi untuk situs ini sedang ditolak browser. Cari alamat di atas atau geser peta. Untuk mengizinkan lagi: ketuk ikon di samping alamat situs, lalu izinkan Lokasi.',
        unavailable: 'Lokasi HP belum terbaca. Pastikan lokasi/GPS HP menyala, atau cari alamat di atas dan geser peta.',
        unsupported: 'Browser ini tidak bisa membaca lokasi. Cari alamat di atas atau geser peta ke titik antar.',
    }[outcome] ?? 'Cari alamat, pilih kecamatan, atau geser peta sampai pin tepat di titik antar.';
}

/** Photon (OpenStreetMap) search results as list items with a point to move the map to. */
export function placeResults(geojson) {
    const seen = new Set();

    return (geojson?.features ?? []).flatMap((feature) => {
        const [lon, lat] = feature?.geometry?.coordinates ?? [];
        const place = feature?.properties ?? {};
        if (typeof lat !== 'number' || typeof lon !== 'number') return [];
        const street = [place.street, place.housenumber].filter(Boolean).join(' ');
        const label = place.name || street;
        if (!label) return [];
        // Photon sometimes puts the RT/RW in the district; the kelurahan or city says more to a customer.
        const area = [place.district, place.locality].find((part) => part && !/^R[TW]\s*\d/i.test(part));
        const detail = [place.name && street !== label ? street : null, area, place.city].filter(Boolean).join(', ');
        const key = `${label}|${detail}`;
        if (seen.has(key)) return [];
        seen.add(key);

        return [{ label, detail, point: [lat, lon] }];
    });
}

/** ORD-16: destinations GoSend/GrabExpress reach from the store (Palu and the neighbouring regencies). */
export function instantArea(label) {
    return /\b(palu|sigi|donggala)\b/i.test(label ?? '');
}

/** ORD-16: the order's delivery type follows the chosen option; "manual" follows the destination. */
export function deliveryFor(receive, kind, areaLabel) {
    if (receive === 'pickup') return 'pickup';
    if (receive !== 'delivery') return '';

    return { instant: 'local_delivery', jnt: 'intercity', manual: instantArea(areaLabel) ? 'local_delivery' : 'intercity' }[kind] ?? '';
}

/** ORD-16: the Palu kecamatan whose name starts the area label, to open the map there. */
export function kecamatanCenter(label, kecamatan) {
    const name = Object.keys(kecamatan ?? {}).find((entry) => (label ?? '').toLowerCase().startsWith(entry.toLowerCase()));

    return name ? kecamatan[name] : null;
}
