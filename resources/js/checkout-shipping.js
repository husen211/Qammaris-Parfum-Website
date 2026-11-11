// ORD-13: checkout courier picker. Instant couriers are priced from a map pin, J&T from a destination area; the
// server prices and keeps every option, the page only shows them and submits the chosen option id.
import { cheapest, discountText, distanceKm, formatRupiah, locationStep, pinSummary, reasonText } from './checkout-shipping/logic.js';

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
    form.addEventListener('change', (event) => {
        if (event.target.matches?.('[data-shipping-choice]')) picked[delivery()] = event.target.value;
    });

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
        input.dataset.charged = option.charged;
        input.dataset.price = option.price;
        node.querySelector('[data-option-label]').textContent = option.label;
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
            reason = { 419: 'session', 429: 'busy' }[response.status] ?? data.reason ?? 'unavailable';
            if (response.ok && data.options?.length) {
                box.replaceChildren(...data.options.map(card));
                box.querySelector(`input[value="${cheapest(data.options).id}"]`).checked = true;
                picked[delivery()] = cheapest(data.options).id;
                status(panel, data.options.length > 1 ? 'Pilih kurir. Yang termurah sudah dipilihkan.' : '');
                announce();

                return;
            }
        } catch {
            reason = 'unavailable';
        } finally {
            panel.removeAttribute('aria-busy');
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
        navigator.geolocation.getCurrentPosition(
            (position) => resolve({ point: [position.coords.latitude, position.coords.longitude] }),
            (error) => resolve({ denied: error.code === error.PERMISSION_DENIED }),
            { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 },
        );
    });

    const openMap = async (message) => {
        openDialog(mapDialog);
        mapMessage.textContent = message ?? 'Geser peta sampai pin tepat di rumah atau kantor tujuan.';
        if (!map) {
            const { createPinMap } = await import('./checkout-shipping/map.js');
            map = createPinMap(mapDialog.querySelector('[data-map]'), { center: pin ?? config.origin, zoom: pin ? 17 : 14, pin: mapDialog.querySelector('[data-map-pin]') });
            mapDialog.querySelector('[data-map-loading]')?.remove();
        }
        requestAnimationFrame(() => map.refresh());
    };

    const goToMyLocation = async () => {
        mapMessage.textContent = 'Mencari lokasimu…';
        const found = await locate();
        if (found.point) {
            map?.setView(found.point, 17);
            mapMessage.textContent = 'Lokasimu ditemukan. Geser peta bila pin kurang pas.';
        } else {
            mapMessage.textContent = found.denied
                ? 'Izin lokasi diblokir. Aktifkan lokasi untuk situs ini di pengaturan browser, atau geser peta ke titik antar.'
                : 'Lokasi belum didapat. Geser peta ke titik antar, atau pilih kecamatan di atas.';
        }
    };

    const startLocation = async () => {
        const step = locationStep(await permission());
        if (step === 'ask') {
            openDialog(locationDialog);
            locationDialog.querySelector('[data-allow-location]').focus();

            return;
        }
        await openMap(step === 'map-blocked' ? 'Izin lokasi diblokir di browser. Geser peta ke titik antar, atau pilih kecamatan di atas.' : null);
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

    mapDialog.querySelector('[data-use-pin]').addEventListener('click', () => {
        if (!map) return;
        pin = map.center();
        closeDialog(mapDialog);
        instant.querySelector('[data-open-location]').hidden = true;
        instant.querySelector('[data-pin-summary]').hidden = false;
        instant.querySelector('[data-pin-text]').textContent = pinSummary(distanceKm(config.origin, pin));
        quote(instant, { mode: 'instant', latitude: pin[0], longitude: pin[1] });
    });

    // J&T destination: an accessible combobox over the server's area search.
    const areaInput = jnt.querySelector('#jnt-area');
    const areaList = jnt.querySelector('#jnt-area-list');
    let areas = [];
    let active = -1;
    let timer;
    let controller;
    const closeList = () => {
        areaList.hidden = true;
        areaInput.setAttribute('aria-expanded', 'false');
        areaInput.removeAttribute('aria-activedescendant');
        active = -1;
    };
    const highlight = (index) => {
        active = index;
        [...areaList.children].forEach((item, position) => item.setAttribute('aria-selected', String(position === index)));
        if (index >= 0) {
            areaInput.setAttribute('aria-activedescendant', `jnt-area-${index}`);
            areaList.children[index]?.scrollIntoView({ block: 'nearest' });
        }
    };
    const showAreas = (found, note) => {
        areas = found;
        areaList.replaceChildren(...(found.length ? found.map((area, index) => {
            const item = document.createElement('li');
            item.id = `jnt-area-${index}`;
            item.setAttribute('role', 'option');
            item.className = 'min-h-12 cursor-pointer border-b border-gray-100 px-3 py-3 text-base leading-6 text-brand-black last:border-b-0 aria-selected:bg-[#FAF8F3]';
            item.textContent = area.label;
            item.addEventListener('pointerdown', (event) => event.preventDefault());
            item.addEventListener('click', () => choose(index));

            return item;
        }) : [Object.assign(document.createElement('li'), { className: 'px-3 py-3 text-sm leading-6 text-gray-600', textContent: note })]));
        areaList.hidden = false;
        areaInput.setAttribute('aria-expanded', 'true');
        highlight(-1);
    };
    const choose = (index) => {
        const area = areas[index];
        if (!area) return;
        areaInput.value = area.label;
        closeList();
        quote(jnt, { mode: 'jnt', area_id: area.id, area_label: area.label });
    };
    const search = async (query) => {
        controller?.abort();
        controller = new AbortController();
        try {
            const response = await fetch(`${config.areaUrl}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
            const data = await response.json().catch(() => ({}));
            if (response.status === 429) return showAreas([], reasonText('busy'));
            if (!response.ok || data.reason) {
                closeList();
                status(jnt, reasonText('unavailable', 'jnt'));
                offerManual(true);

                return;
            }
            showAreas(data.areas ?? [], 'Belum ada hasil. Coba nama kecamatan, kota, atau kode pos lain.');
        } catch (error) {
            if (error.name !== 'AbortError') {
                closeList();
                status(jnt, reasonText('unavailable', 'jnt'));
                offerManual(true);
            }
        }
    };
    areaInput.addEventListener('input', () => {
        clearTimeout(timer);
        jnt.querySelector('[data-shipping-options]').replaceChildren();
        status(jnt, '');
        offerManual(false);
        const query = areaInput.value.trim();
        if (query.length < 3) {
            closeList();

            return;
        }
        timer = setTimeout(() => search(query), 350);
    });
    areaInput.addEventListener('keydown', (event) => {
        if (areaList.hidden || !areas.length) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            highlight((active + (event.key === 'ArrowDown' ? 1 : areas.length - 1) + (active < 0 && event.key === 'ArrowUp' ? 1 : 0)) % areas.length);
        } else if (event.key === 'Enter' && active >= 0) {
            event.preventDefault();
            choose(active);
        } else if (event.key === 'Escape') {
            closeList();
        }
    });
    areaInput.addEventListener('blur', () => setTimeout(closeList, 120));

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
