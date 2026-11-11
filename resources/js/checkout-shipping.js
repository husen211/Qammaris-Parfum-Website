// ORD-13/ORD-16: checkout delivery in marketplace order. The customer chooses how to receive, fills the address
// (destination area, full address and, for instant couriers, a map pin), then picks a priced option grouped as
// Instan (GoSend, GrabExpress) or Reguler (J&T). The server prices and keeps every option; the page only shows them,
// submits the chosen option id and keeps the hidden `delivery` in step with it.
import { deliveryFor, discountText, distanceKm, formatRupiah, instantArea, kecamatanCenter, locationStep, locationText, pinSummary, placeResults, reasonText } from './checkout-shipping/logic.js';

const root = document.querySelector('[data-shipping-quotes]');
if (root) setup(root);

function setup(root) {
    const form = root.closest('form');
    const config = JSON.parse(root.dataset.config);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const deliveryInput = root.querySelector('[data-delivery-value]');
    const section = root.querySelector('[data-delivery-section]');
    const choices = root.querySelector('[data-shipping-choices]');
    const hint = root.querySelector('[data-shipping-hint]');
    const groups = { instant: root.querySelector('[data-shipping-group="instant"]'), jnt: root.querySelector('[data-shipping-group="jnt"]') };
    const pinBlock = root.querySelector('[data-instant-pin]');
    const manual = root.querySelector('[data-manual-option]');
    const manualInput = manual.querySelector('input');
    const template = root.querySelector('[data-option-template]');
    const areaInput = root.querySelector('#delivery-area');
    const areaIdInput = root.querySelector('[data-area-id]');
    const areaLabelInput = root.querySelector('[data-area-label]');
    const address = root.querySelector('#customer_address');
    const locationDialog = document.querySelector('[data-location-dialog]');
    const mapDialog = document.querySelector('[data-map-dialog]');
    const mapMessage = mapDialog.querySelector('[data-map-message]');
    const runs = { instant: 0, jnt: 0 };
    let area = areaIdInput.value && areaLabelInput.value ? { id: areaIdInput.value, label: areaLabelInput.value } : null;
    let map = null;
    let pin = null;

    const receive = () => form.querySelector('[data-receive]:checked')?.value ?? '';
    const selected = () => form.querySelector('[data-shipping-choice]:checked:not(:disabled)');
    const announce = () => form.dispatchEvent(new Event('shipping-options'));
    const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // One place decides what is visible, required and sent.
    const sync = () => {
        const delivers = receive() === 'delivery';
        section.hidden = !delivers;
        address.required = delivers;
        root.querySelectorAll('[data-shipping-choice]').forEach((input) => {
            input.disabled = !delivers || (input === manualInput && manual.hidden);
        });
        deliveryInput.value = deliveryFor(receive(), selected()?.dataset.kind, area?.label);
        announce();
    };

    const status = (group, text, error = false) => {
        const line = group.querySelector('[data-shipping-status]');
        line.textContent = text;
        line.classList.toggle('text-red-700', error);
        line.classList.toggle('text-gray-600', !error);
    };

    const offerManual = (show) => {
        manual.hidden = !show;
        if (!show) manualInput.checked = false;
    };

    const card = (option) => {
        const node = template.content.firstElementChild.cloneNode(true);
        const input = node.querySelector('input');
        input.value = option.id;
        input.dataset.kind = option.kind;
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

    const clearGroup = (kind) => {
        groups[kind].querySelector('[data-shipping-options]').replaceChildren();
        status(groups[kind], '');
    };

    // Each group asks the server on its own; only its latest answer may fill it (an older one is for a previous pin or area).
    const quote = async (kind, body) => {
        const group = groups[kind];
        const run = ++runs[kind];
        const box = group.querySelector('[data-shipping-options]');
        // A courier the customer already chose in this group stays chosen at its new price.
        const keepProvider = selected()?.dataset.kind === kind ? selected().dataset.provider : null;
        group.hidden = false;
        hint.hidden = true;
        box.replaceChildren();
        status(group, 'Menghitung ongkir…');
        group.setAttribute('aria-busy', 'true');
        let reason = 'unavailable';
        try {
            const response = await fetch(config.quoteUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body),
            });
            const data = await response.json().catch(() => ({}));
            if (run !== runs[kind]) return;
            reason = { 419: 'session', 429: 'busy' }[response.status] ?? data.reason ?? 'unavailable';
            if (response.ok && data.options?.length) {
                box.replaceChildren(...data.options.map(card));
                const kept = keepProvider ? data.options.find((option) => option.provider === keepProvider) : null;
                if (kept) box.querySelector(`input[value="${kept.id}"]`).checked = true;
                status(group, '');
                sync();

                return;
            }
        } catch {
            if (run !== runs[kind]) return;
            reason = 'unavailable';
        } finally {
            if (run === runs[kind]) group.removeAttribute('aria-busy');
        }
        status(group, reasonText(reason, kind), reason === 'cart' || reason === 'session');
        if (!['cart', 'session', 'busy'].includes(reason)) offerManual(true);
        sync();
    };

    // The destination decides the groups: J&T everywhere, instant couriers around Palu (after a map pin).
    const setArea = (choice, { requote = true } = {}) => {
        area = choice;
        areaIdInput.value = choice?.id ?? '';
        areaLabelInput.value = choice?.label ?? '';
        const instant = instantArea(choice?.label);
        pinBlock.hidden = !instant;
        groups.instant.hidden = !instant;
        if (!instant) clearGroup('instant');
        else if (!groups.instant.querySelector('[data-shipping-choice]')) {
            status(groups.instant, pin ? '' : 'Tentukan titik di peta (di atas) untuk melihat ongkir GoSend dan GrabExpress.');
        }
        if (!choice) {
            groups.jnt.hidden = true;
            clearGroup('jnt');
            offerManual(false);
            hint.hidden = false;
        } else if (requote) {
            offerManual(false);
            quote('jnt', { mode: 'jnt', area_id: choice.id, area_label: choice.label });
        }
        sync();
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
        // The map opens on the pin, else on the chosen kecamatan, else on the store.
        const start = pin ?? kecamatanCenter(area?.label, config.kecamatan);
        if (!map) {
            const { createPinMap } = await import('./checkout-shipping/map.js');
            map = createPinMap(mapDialog.querySelector('[data-map]'), { center: start ?? config.origin, zoom: pin ? 17 : start ? 15 : 14 });
            mapDialog.querySelector('[data-map-loading]')?.remove();
        } else if (!pin && start) {
            map.setView(start, 15);
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
    pinBlock.querySelector('[data-open-location]').addEventListener('click', startLocation);
    pinBlock.querySelector('[data-change-pin]').addEventListener('click', () => openMap(null));
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
    const serviceArea = config.serviceArea;
    combobox({
        input: placeInput,
        list: mapDialog.querySelector('#place-search-list'),
        idPrefix: 'place',
        search: async (query, signal) => {
            const params = new URLSearchParams({ q: query, limit: '6', lat: String(config.origin[0]), lon: String(config.origin[1]),
                bbox: [serviceArea.west, serviceArea.south, serviceArea.east, serviceArea.north].join(',') });
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
        pinBlock.querySelector('[data-open-location]').hidden = true;
        pinBlock.querySelector('[data-pin-summary]').hidden = false;
        pinBlock.querySelector('[data-pin-text]').textContent = pinSummary(distanceKm(config.origin, pin));
        quote('instant', { mode: 'instant', latitude: pin[0], longitude: pin[1] });
    });

    // Destination: kecamatan, city or postcode from the server's area search.
    combobox({
        input: areaInput,
        list: root.querySelector('#delivery-area-list'),
        idPrefix: 'delivery-area',
        onInput: () => { if (area) setArea(null); },
        search: async (query, signal) => {
            const response = await fetch(`${config.areaUrl}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, signal });
            const data = await response.json().catch(() => ({}));
            if (response.status === 429) return { items: [], note: reasonText('busy') };
            if (!response.ok || data.reason) throw new Error('areas');

            return { items: data.areas ?? [], note: 'Belum ada hasil. Coba nama kecamatan, kota, atau kode pos lain.' };
        },
        failed: () => {
            groups.jnt.hidden = false;
            hint.hidden = true;
            status(groups.jnt, reasonText('unavailable', 'jnt'));
            offerManual(true);
            sync();
        },
        onChoose: (choice) => setArea(choice),
    });

    // The form refused to send without an option: point at the first thing that is missing.
    form.addEventListener('shipping-missing', () => {
        const focusOn = (element, message) => {
            hint.hidden = false;
            hint.textContent = message;
            hint.classList.add('text-red-700');
            choices.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'center' });
            element?.focus({ preventScroll: true });
        };
        if (!area) {
            focusOn(areaInput, 'Isi kecamatan atau kota tujuan dulu, lalu pilih pengiriman.');
            areaInput.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'center' });
        } else {
            focusOn(root.querySelector('[data-shipping-choice]:not(:disabled)') ?? pinBlock.querySelector('[data-open-location]:not([hidden])'),
                'Pilih salah satu pengiriman di bawah.');
        }
    });

    form.addEventListener('change', (event) => {
        if (event.target.matches?.('[data-receive]')) sync();
        if (event.target.matches?.('[data-shipping-choice]')) {
            hint.classList.remove('text-red-700');
            if (area) hint.hidden = true;
            sync();
        }
    });

    // Back from a failed submit: the area is known again; J&T is priced again (an instant option stays as chosen).
    if (area) setArea(area, { requote: !root.querySelector('[data-shipping-group="jnt"] [data-shipping-choice]') || selected()?.dataset.kind !== 'jnt' });
    sync();
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
