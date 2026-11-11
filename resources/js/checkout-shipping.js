// ORD-13: checkout courier picker. Instant couriers are priced from a map pin, J&T from a destination area; the
// server prices and keeps every option, the page only shows them and submits the chosen option id.
import { cheapest, discountText, distanceKm, formatRupiah, locationStep, locationText, pinSummary, placeResults, reasonText } from './checkout-shipping/logic.js';

const root = document.querySelector('[data-shipping-quotes]');
if (root) setup(root);

function setup(root) {
    const form = root.closest('form');
    const config = JSON.parse(root.dataset.config);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const panels = Object.fromEntries([...root.querySelectorAll('[data-shipping-panel]')].map((panel) => [panel.dataset.shippingPanel, panel]));
    const manual = root.querySelector('[data-manual-option]');
    const manualInput = manual.querySelector('input');
    const template = root.querySelector('[data-option-template]');
    const locationDialog = document.querySelector('[data-location-dialog]');
    const mapDialog = document.querySelector('[data-map-dialog]');
    const mapMessage = mapDialog.querySelector('[data-map-message]');
    const instant = panels.local_delivery;
    const jnt = panels.intercity;
    const delivery = () => form.querySelector('[data-delivery]:checked')?.value ?? '';
    let manualFor = manual.hidden ? null : delivery();
    let map = null;
    let pin = null;

    // All options share one radio name, so picking J&T unchecks the instant courier; each delivery keeps its own pick.
    const picked = { [delivery()]: form.querySelector('[data-shipping-choice]:checked')?.value };
    // The courier (GoSend, Grab) the customer chose survives a new price check for another pin.
    const pickedProvider = {};
    form.addEventListener('change', (event) => {
        if (!event.target.matches?.('[data-shipping-choice]')) return;
        picked[delivery()] = event.target.value;
        pickedProvider[delivery()] = event.target.dataset.provider;
    });
    let quoteRun = 0;

    const announce = () => form.dispatchEvent(new Event('shipping-options'));

    // Only the chosen delivery's panel is visible and submitted; "dikonfirmasi admin" belongs to the panel that failed.
    const syncPanels = () => {
        const current = delivery();
        Object.entries(panels).forEach(([value, panel]) => {
            panel.hidden = value !== current;
            panel.querySelectorAll('[data-shipping-choice]').forEach((input) => { input.disabled = value !== current; });
        });
        manual.hidden = manualFor !== current;
        manualInput.disabled = manual.hidden;
        const again = [...root.querySelectorAll('[data-shipping-choice]:not(:disabled)')].find((input) => input.value === picked[current]);
        if (again) again.checked = true;
        announce();
    };

    const offerManual = (show) => {
        manualFor = show ? delivery() : null;
        if (!show) manualInput.checked = false;
        syncPanels();
    };

    const status = (panel, text, error = false) => {
        const line = panel.querySelector('[data-shipping-status]');
        line.textContent = text;
        line.classList.toggle('text-red-700', error);
        line.classList.toggle('text-gray-600', !error);
    };

    const card = (option) => {
        const node = template.content.firstElementChild.cloneNode(true);
        const input = node.querySelector('input');
        input.value = option.id;
        input.dataset.provider = option.provider;
        input.dataset.charged = option.charged;
        input.dataset.price = option.price;
        node.querySelector('[data-option-label]').textContent = option.label;
        const logo = node.querySelector('[data-option-logo]');
        const logoUrl = config.logos?.[option.provider];
        if (logoUrl) logo.src = logoUrl; else logo.removeAttribute('src');
        logo.hidden = !logoUrl;
        node.querySelector('[data-option-eta]').textContent = option.eta ?? '';
        const discount = node.querySelector('[data-option-discount]');
        discount.textContent = discountText(option);
        discount.hidden = !discount.textContent;
        const price = node.querySelector('[data-option-price]');
        price.textContent = formatRupiah(option.price);
        price.hidden = !(option.discount > 0);
        node.querySelector('[data-option-charged]').textContent = formatRupiah(option.charged);

        return node;
    };

    const quote = async (panel, body) => {
        // Only the latest price check may fill the panel; an older, slower answer is for a previous pin or area.
        const run = ++quoteRun;
        const box = panel.querySelector('[data-shipping-options]');
        box.replaceChildren();
        offerManual(false);
        status(panel, 'Menghitung ongkir…');
        panel.setAttribute('aria-busy', 'true');
        let reason = 'unavailable';
        try {
            const response = await fetch(config.quoteUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body),
            });
            const data = await response.json().catch(() => ({}));
            if (run !== quoteRun) return;
            reason = { 419: 'session', 429: 'busy' }[response.status] ?? data.reason ?? 'unavailable';
            if (response.ok && data.options?.length) {
                box.replaceChildren(...data.options.map(card));
                const kept = data.options.find((option) => option.provider === pickedProvider[delivery()]);
                const chosen = kept ?? cheapest(data.options);
                box.querySelector(`input[value="${chosen.id}"]`).checked = true;
                picked[delivery()] = chosen.id;
                status(panel, data.options.length > 1 ? (kept ? 'Kurir pilihan Anda tetap dipilih; harga sudah diperbarui.' : 'Pilih kurir. Yang termurah sudah dipilihkan.') : '');
                announce();

                return;
            }
        } catch {
            if (run !== quoteRun) return;
            reason = 'unavailable';
        } finally {
            if (run === quoteRun) panel.removeAttribute('aria-busy');
        }
        status(panel, reasonText(reason, body.mode), reason === 'cart' || reason === 'session');
        if (!['cart', 'session', 'busy'].includes(reason)) offerManual(true);
    };

    // Dialogs: centered pop-ups; the page behind does not scroll while one is open.
    const openDialog = (dialog) => {
        if (!dialog.open) dialog.showModal();
        document.documentElement.style.overflow = 'hidden';
    };
    const closeDialog = (dialog) => { if (dialog.open) dialog.close(); };
    [locationDialog, mapDialog].forEach((dialog) => {
        dialog.addEventListener('close', () => {
            if (!locationDialog.open && !mapDialog.open) document.documentElement.style.overflow = '';
        });
        dialog.addEventListener('click', (event) => { if (event.target === dialog) closeDialog(dialog); });
        dialog.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => closeDialog(dialog)));
    });

    const permission = async () => {
        if (!('geolocation' in navigator) || !window.isSecureContext) return 'unsupported';
        try {
            return (await navigator.permissions?.query({ name: 'geolocation' }))?.state ?? 'prompt';
        } catch {
            return 'prompt';
        }
    };
    const locate = () => new Promise((resolve) => {
        if (!('geolocation' in navigator) || !window.isSecureContext) {
            resolve({ error: 'unsupported' });

            return;
        }
        navigator.geolocation.getCurrentPosition(
            (position) => resolve({ point: [position.coords.latitude, position.coords.longitude] }),
            (error) => resolve({ error: error.code === error.PERMISSION_DENIED ? 'denied' : 'unavailable' }),
            { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 },
        );
    });

    const openMap = async (message) => {
        openDialog(mapDialog);
        mapMessage.textContent = message ?? locationText('manual');
        if (!map) {
            const { createPinMap } = await import('./checkout-shipping/map.js');
            map = createPinMap(mapDialog.querySelector('[data-map]'), { center: pin ?? config.origin, zoom: pin ? 17 : 14 });
            mapDialog.querySelector('[data-map-loading]')?.remove();
        }
        requestAnimationFrame(() => map.refresh());
    };

    // Every outcome leaves the map usable: found, refused (now or earlier, "allow once" included), or no GPS.
    const goToMyLocation = async () => {
        mapMessage.textContent = 'Mencari lokasimu…';
        const found = await locate();
        if (found.point) {
            map?.setView(found.point, 17);
            mapMessage.textContent = locationText('found');
        } else {
            mapMessage.textContent = locationText(found.error);
        }
    };

    const startLocation = async () => {
        const step = locationStep(await permission());
        if (step === 'ask') {
            openDialog(locationDialog);
            locationDialog.querySelector('[data-allow-location]').focus();

            return;
        }
        await openMap(step === 'map-blocked' ? locationText('denied') : null);
        if (step === 'locate') await goToMyLocation();
    };

    locationDialog.querySelector('[data-allow-location]').addEventListener('click', async () => {
        closeDialog(locationDialog);
        await openMap('Mencari lokasimu…');
        await goToMyLocation();
    });
    locationDialog.querySelector('[data-pick-manually]').addEventListener('click', async () => {
        closeDialog(locationDialog);
        await openMap(null);
        placeInput.focus({ preventScroll: true });
    });
    instant.querySelector('[data-open-location]').addEventListener('click', startLocation);
    instant.querySelector('[data-change-pin]').addEventListener('click', () => openMap(null));
    mapDialog.querySelector('[data-locate-me]').addEventListener('click', goToMyLocation);

    // Starting views per kecamatan; the customer still moves the map to the exact spot.
    const chips = mapDialog.querySelector('[data-kecamatan-list]');
    Object.entries(config.kecamatan).forEach(([name, point]) => {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'min-h-10 shrink-0 border border-gray-300 bg-white px-3 text-sm text-brand-black [touch-action:manipulation] active:bg-gray-100';
        chip.textContent = name;
        chip.addEventListener('click', () => {
            map?.setView(point, 15);
            mapMessage.textContent = `Peta di ${name}. Sekarang geser sampai pin tepat di titik antar.`;
        });
        chips.append(chip);
    });

    // Place search (streets, buildings, landmarks) inside the service area, then fine-tune by moving the map.
    const placeInput = mapDialog.querySelector('#place-search');
    const area = config.serviceArea;
    combobox({
        input: placeInput,
        list: mapDialog.querySelector('#place-search-list'),
        idPrefix: 'place',
        search: async (query, signal) => {
            const params = new URLSearchParams({ q: query, limit: '6', lat: String(config.origin[0]), lon: String(config.origin[1]),
                bbox: [area.west, area.south, area.east, area.north].join(',') });
            const response = await fetch(`${config.placeSearchUrl}?${params}`, { signal });
            if (!response.ok) throw new Error('search');

            return { items: placeResults(await response.json()), note: 'Belum ketemu. Coba nama jalan atau tempat lain, atau geser peta.' };
        },
        failed: () => { mapMessage.textContent = 'Pencarian sedang tidak bisa dipakai. Pilih kecamatan atau geser peta ke titik antar.'; },
        onChoose: (place) => {
            map?.setView(place.point, 17);
            mapMessage.textContent = `Peta di ${place.label}. Geser sedikit bila pin belum tepat di titik antar.`;
            placeInput.blur();
        },
    });

    mapDialog.querySelector('[data-use-pin]').addEventListener('click', () => {
        if (!map) return;
        pin = map.center();
        closeDialog(mapDialog);
        instant.querySelector('[data-open-location]').hidden = true;
        instant.querySelector('[data-pin-summary]').hidden = false;
        instant.querySelector('[data-pin-text]').textContent = pinSummary(distanceKm(config.origin, pin));
        quote(instant, { mode: 'instant', latitude: pin[0], longitude: pin[1] });
    });

    // J&T destination: the same combobox over the server's area search.
    const areaInput = jnt.querySelector('#jnt-area');
    combobox({
        input: areaInput,
        list: jnt.querySelector('#jnt-area-list'),
        idPrefix: 'jnt-area',
        onInput: () => {
            jnt.querySelector('[data-shipping-options]').replaceChildren();
            status(jnt, '');
            offerManual(false);
        },
        search: async (query, signal) => {
            const response = await fetch(`${config.areaUrl}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, signal });
            const data = await response.json().catch(() => ({}));
            if (response.status === 429) return { items: [], note: reasonText('busy') };
            if (!response.ok || data.reason) throw new Error('areas');

            return { items: data.areas ?? [], note: 'Belum ada hasil. Coba nama kecamatan, kota, atau kode pos lain.' };
        },
        failed: () => {
            status(jnt, reasonText('unavailable', 'jnt'));
            offerManual(true);
        },
        onChoose: (choice) => quote(jnt, { mode: 'jnt', area_id: choice.id, area_label: choice.label }),
    });

    // The form refused to send without a courier: point at what is missing.
    form.addEventListener('shipping-missing', () => {
        const panel = panels[delivery()];
        status(panel, delivery() === 'intercity' ? 'Pilih kecamatan atau kota tujuan dulu, lalu ongkir J&T muncul.' : 'Tentukan titik antar dulu, lalu pilih kurir.', true);
        panel.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
        (delivery() === 'intercity' ? areaInput : instant.querySelector('[data-open-location]:not([hidden])') ?? instant.querySelector('[data-change-pin]')).focus({ preventScroll: true });
    });

    form.querySelectorAll('[data-delivery]').forEach((input) => input.addEventListener('change', syncPanels));
    syncPanels();
}

/** Accessible search-as-you-type list (ARIA combobox): arrow keys, Enter, Escape, tap; results are text only. */
function combobox({ input, list, idPrefix, search, onChoose, onInput, failed }) {
    let items = [];
    let active = -1;
    let timer;
    let controller;
    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
    };
    const highlight = (index) => {
        active = index;
        [...list.children].forEach((item, position) => item.setAttribute('aria-selected', String(position === index)));
        if (index >= 0) {
            input.setAttribute('aria-activedescendant', `${idPrefix}-${index}`);
            list.children[index]?.scrollIntoView({ block: 'nearest' });
        }
    };
    const choose = (index) => {
        const item = items[index];
        if (!item) return;
        input.value = item.label;
        close();
        onChoose(item);
    };
    const show = (found, note) => {
        items = found;
        list.replaceChildren(...(found.length ? found.map((entry, index) => {
            const item = document.createElement('li');
            item.id = `${idPrefix}-${index}`;
            item.setAttribute('role', 'option');
            item.className = 'min-h-12 cursor-pointer border-b border-gray-100 px-3 py-2.5 text-base leading-6 text-brand-black last:border-b-0 aria-selected:bg-[#FAF8F3]';
            item.append(Object.assign(document.createElement('span'), { className: 'block', textContent: entry.label }));
            if (entry.detail) item.append(Object.assign(document.createElement('span'), { className: 'block text-sm leading-5 text-gray-600', textContent: entry.detail }));
            item.addEventListener('pointerdown', (event) => event.preventDefault());
            item.addEventListener('click', () => choose(index));

            return item;
        }) : [Object.assign(document.createElement('li'), { className: 'px-3 py-3 text-sm leading-6 text-gray-600', textContent: note })]));
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        highlight(-1);
    };
    input.addEventListener('input', () => {
        clearTimeout(timer);
        onInput?.();
        const query = input.value.trim();
        if (query.length < 3) {
            close();

            return;
        }
        timer = setTimeout(async () => {
            controller?.abort();
            controller = new AbortController();
            try {
                const result = await search(query, controller.signal);
                show(result.items, result.note);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    close();
                    failed?.();
                }
            }
        }, 350);
    });
    input.addEventListener('keydown', (event) => {
        if (list.hidden || !items.length) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            highlight((active + (event.key === 'ArrowDown' ? 1 : items.length - 1) + (active < 0 && event.key === 'ArrowUp' ? 1 : 0)) % items.length);
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            choose(active);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    });
    input.addEventListener('blur', () => setTimeout(close, 150));
}
