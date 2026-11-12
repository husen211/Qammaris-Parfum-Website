// ORD-20: the delivery pin on the customer's order link (admin-made orders too). The same map as the checkout:
// the pin stays in the middle and the customer moves the map, finds their location, picks a kecamatan or searches
// a place. "Pakai titik ini" sends the point; the server checks it is in Palu and saves it on the order.
import { locationText, placeResults } from './checkout-shipping/logic.js';
import { combobox } from './checkout-shipping/combobox.js';

const root = document.querySelector('[data-order-pin]');
if (root) setup(root);

function setup(root) {
    const config = JSON.parse(root.dataset.config);
    const form = root.querySelector('[data-pin-form]');
    const dialog = document.querySelector('[data-map-dialog]');
    const message = dialog.querySelector('[data-map-message]');
    const useButton = dialog.querySelector('[data-use-pin]');
    let map = null;

    const close = () => {
        if (dialog.open) dialog.close();
    };
    dialog.addEventListener('close', () => { document.documentElement.style.overflow = ''; });
    dialog.addEventListener('click', (event) => { if (event.target === dialog) close(); });
    dialog.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', close));

    const open = async () => {
        if (!dialog.open) dialog.showModal();
        document.documentElement.style.overflow = 'hidden';
        message.textContent = locationText('manual');
        if (!map) {
            const { createPinMap } = await import('./checkout-shipping/map.js');
            map = createPinMap(dialog.querySelector('[data-map]'), { center: config.point ?? config.origin, zoom: config.point ? 17 : 14 });
            dialog.querySelector('[data-map-loading]')?.remove();
        }
        requestAnimationFrame(() => map.refresh());
    };
    root.querySelectorAll('[data-open-map]').forEach((button) => button.addEventListener('click', open));

    // "Temukan lokasi saya": the phone asks for permission here; every outcome leaves the map usable.
    dialog.querySelector('[data-locate-me]').addEventListener('click', () => {
        if (!('geolocation' in navigator) || !window.isSecureContext) {
            message.textContent = locationText('unsupported');

            return;
        }
        message.textContent = 'Mencari lokasimu…';
        navigator.geolocation.getCurrentPosition(
            (position) => {
                map?.setView([position.coords.latitude, position.coords.longitude], 17);
                message.textContent = locationText('found');
            },
            (error) => { message.textContent = locationText(error.code === error.PERMISSION_DENIED ? 'denied' : 'unavailable'); },
            { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 },
        );
    });

    const chips = dialog.querySelector('[data-kecamatan-list]');
    Object.entries(config.kecamatan).forEach(([name, point]) => {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'min-h-10 shrink-0 border border-gray-300 bg-white px-3 text-sm text-brand-black [touch-action:manipulation] active:bg-gray-100';
        chip.textContent = name;
        chip.addEventListener('click', () => {
            map?.setView(point, 15);
            message.textContent = `Peta di ${name}. Sekarang geser sampai pin tepat di titik antar.`;
        });
        chips.append(chip);
    });

    const placeInput = dialog.querySelector('#place-search');
    const area = config.serviceArea;
    combobox({
        input: placeInput,
        list: dialog.querySelector('#place-search-list'),
        idPrefix: 'place',
        search: async (query, signal) => {
            const params = new URLSearchParams({ q: query, limit: '6', lat: String(config.origin[0]), lon: String(config.origin[1]),
                bbox: [area.west, area.south, area.east, area.north].join(',') });
            const response = await fetch(`${config.placeSearchUrl}?${params}`, { signal });
            if (!response.ok) throw new Error('search');

            return { items: placeResults(await response.json()), note: 'Belum ketemu. Coba nama jalan atau tempat lain, atau geser peta.' };
        },
        failed: () => { message.textContent = 'Pencarian sedang tidak bisa dipakai. Pilih kecamatan atau geser peta ke titik antar.'; },
        onChoose: (place) => {
            map?.setView(place.point, 17);
            message.textContent = `Peta di ${place.label}. Geser sedikit bila pin belum tepat di titik antar.`;
            placeInput.blur();
        },
    });

    useButton.addEventListener('click', () => {
        if (!map) return;
        const [latitude, longitude] = map.center();
        if (latitude < area.south || latitude > area.north || longitude < area.west || longitude > area.east) {
            message.textContent = 'Titik antar harus di Palu dan sekitarnya. Geser peta ke lokasi Anda.';

            return;
        }
        form.querySelector('[name="latitude"]').value = latitude.toFixed(6);
        form.querySelector('[name="longitude"]').value = longitude.toFixed(6);
        useButton.disabled = true;
        useButton.textContent = 'Menyimpan…';
        form.submit();
    });
}
