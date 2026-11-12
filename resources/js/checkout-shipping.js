// ORD-13/ORD-16/ORD-19: checkout delivery in marketplace order. The customer chooses how to receive, fills the address
// (in Palu the map pin first, which fills the kecamatan; elsewhere the destination area; then the full address), then
// picks a priced option grouped as
// Instan (GoSend, GrabExpress) or Reguler (J&T). The server prices and keeps every option; the page only shows them,
// submits the chosen option id and keeps the hidden `delivery` in step with it.
import { areaParts, deliveryFor, discountText, distanceKm, exactArea, formatRupiah, instantArea, kecamatanCenter, locationStep, locationText, pinSummary, pinWarning, placeResults, reasonText } from './checkout-shipping/logic.js';
import { combobox } from './checkout-shipping/combobox.js';

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
    const areaButton = root.querySelector('[data-area-open]');
    const areaText = root.querySelector('[data-area-text]');
    const areaDialog = document.querySelector('[data-area-dialog]');
    const areaSearch = areaDialog.querySelector('#area-search');
    const areaResults = areaDialog.querySelector('#area-results');
    const areaState = areaDialog.querySelector('[data-area-state]');
    const preview = root.querySelector('[data-pin-preview]');
    const mismatch = root.querySelector('[data-pin-mismatch]');
    const pinAreas = root.querySelector('[data-pin-areas]');
    const areaIdInput = root.querySelector('[data-area-id]');
    const areaLabelInput = root.querySelector('[data-area-label]');
    const address = root.querySelector('#customer_address');
    const locationDialog = document.querySelector('[data-location-dialog]');
    const mapDialog = document.querySelector('[data-map-dialog]');
    const mapMessage = mapDialog.querySelector('[data-map-message]');
    const runs = { instant: 0, jnt: 0 };
    let area = areaIdInput.value && areaLabelInput.value ? { id: areaIdInput.value, label: areaLabelInput.value } : null;
    let map = null;
    let previewMap = null;
    // After a failed submit the page brings the customer's own pin back for the small map.
    let pin = preview.dataset.point ? JSON.parse(preview.dataset.point) : null;
    let pinKecamatan = null;

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

    const status = (group, text, error = false, retry = null) => {
        const line = group.querySelector('[data-shipping-status]');
        line.textContent = text;
        line.classList.toggle('text-red-700', error);
        line.classList.toggle('text-gray-600', !error);
        if (retry) {
            const again = Object.assign(document.createElement('button'), { type: 'button', textContent: 'Hitung ulang ongkir' });
            again.className = 'ml-1 min-h-11 font-semibold text-brand-black underline underline-offset-4';
            again.addEventListener('click', retry);
            line.append(' ', again);
        }
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
        runs[kind] += 1;
        groups[kind].removeAttribute('aria-busy');
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
        status(group, reasonText(reason, kind), reason === 'cart' || reason === 'session',
            ['unavailable', 'busy'].includes(reason) ? () => quote(kind, body) : null);
        if (!['cart', 'session', 'busy'].includes(reason)) offerManual(true);
        sync();
    };

    // The destination decides the groups: J&T everywhere, instant couriers around Palu (after a map pin).
    const setArea = (choice, { requote = true } = {}) => {
        area = choice;
        areaIdInput.value = choice?.id ?? '';
        areaLabelInput.value = choice?.label ?? '';
        areaText.textContent = choice?.label ?? 'Cari kecamatan atau kota';
        areaText.classList.toggle('text-gray-500', !choice);
        showMismatch();
        // ORD-19: with a pin and no kecamatan yet (being read, or to be chosen) the instant prices stay.
        const instant = choice ? instantArea(choice.label) : Boolean(pin);
        groups.instant.hidden = !instant;
        if (!instant) clearGroup('instant');
        else if (!pin && !groups.instant.querySelector('[data-shipping-choice]')) {
            status(groups.instant, 'Tentukan titik di peta (di atas) untuk melihat ongkir GoSend dan GrabExpress.');
        }
        if (!choice) {
            groups.jnt.hidden = true;
            clearGroup('jnt');
            offerManual(false);
            hint.hidden = Boolean(pin);
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
    [locationDialog, mapDialog, areaDialog].forEach((dialog) => {
        dialog.addEventListener('close', () => {
            if (!locationDialog.open && !mapDialog.open && !areaDialog.open) document.documentElement.style.overflow = '';
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
        showPreview();
        groups.instant.hidden = false;
        quote('instant', { mode: 'instant', latitude: pin[0], longitude: pin[1] });
        checkPinKecamatan({ fill: true });
    });

    // ORD-17/18: kecamatan/kota search screen. Typing searches after a short pause ("Mencari…" at once); the keyboard's
    // Search key searches straight away and closes the keyboard so the results can be read and scrolled; the customer
    // always taps the result. Results are kept per query in the page and cached on the server.
    const areaCache = new Map();
    const areaMore = areaDialog.querySelector('[data-area-more]');
    let areaItems = [];
    let areaActive = -1;
    let areaTimer;
    let areaController;
    const fetchAreas = async (query) => {
        const key = query.toLowerCase();
        if (areaCache.has(key)) return areaCache.get(key);
        areaController?.abort();
        areaController = new AbortController();
        const response = await fetch(`${config.areaUrl}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, signal: areaController.signal });
        const data = await response.json().catch(() => ({}));
        if (response.status === 429) throw Object.assign(new Error('busy'), { reason: 'busy' });
        if (!response.ok || data.reason) throw new Error('areas');
        areaCache.set(key, data.areas ?? []);

        return data.areas ?? [];
    };
    const areaRows = () => [...areaResults.querySelectorAll('[role="option"]')];
    const highlightArea = (index) => {
        areaActive = index;
        areaRows().forEach((row, position) => row.setAttribute('aria-selected', String(position === index)));
        const row = areaRows()[index];
        if (row) {
            areaSearch.setAttribute('aria-activedescendant', row.id);
            row.scrollIntoView({ block: 'nearest' });
        } else {
            areaSearch.removeAttribute('aria-activedescendant');
        }
    };
    const chooseArea = (choice) => {
        if (!choice) return;
        closeDialog(areaDialog);
        setArea(choice);
        areaButton.focus({ preventScroll: true });
    };
    const optionRow = (id, title, detail, onPick) => {
        const row = document.createElement('li');
        row.id = id;
        row.setAttribute('role', 'option');
        row.setAttribute('aria-selected', 'false');
        row.className = 'flex min-h-14 cursor-pointer flex-col justify-center gap-0.5 border-b border-gray-100 px-3 py-2 [touch-action:manipulation] last:border-b-0 aria-selected:bg-[#FAF8F3] active:bg-[#FAF8F3]';
        row.append(Object.assign(document.createElement('span'), { className: 'text-base font-medium leading-6 text-brand-black', textContent: title }));
        if (detail) row.append(Object.assign(document.createElement('span'), { className: 'text-sm leading-5 text-gray-600', textContent: detail }));
        row.addEventListener('click', onPick);

        return row;
    };
    const showIntro = () => {
        areaItems = [];
        areaResults.replaceChildren();
        areaMore.hidden = true;
        areaState.textContent = 'Ketik nama kecamatan, kota, atau kode pos tujuan.';
        highlightArea(-1);
    };
    const showAreas = (found, query) => {
        areaItems = found;
        areaState.textContent = found.length ? `${found.length} hasil untuk "${query}". Ketuk yang sesuai.` : `Belum ada hasil untuk "${query}". Coba nama kecamatan, kota, atau kode pos lain.`;
        areaResults.replaceChildren(...found.map((entry, index) => {
            const { title, detail } = areaParts(entry.label);

            return optionRow(`area-${index}`, title, detail, () => chooseArea(entry));
        }));
        areaResults.scrollTop = 0;
        // Biteship sends at most 10 places; a full list means a more precise search may find the right one.
        areaMore.hidden = found.length < 10;
        highlightArea(-1);
    };
    const failedAreas = (error) => {
        areaItems = [];
        areaResults.replaceChildren();
        areaMore.hidden = true;
        areaState.textContent = error?.reason === 'busy' ? reasonText('busy') : 'Pencarian sedang tidak bisa dipakai. Coba lagi sebentar.';
    };
    const runAreaSearch = async (query) => {
        try {
            const found = await fetchAreas(query);
            if (areaSearch.value.trim() === query) showAreas(found, query);
        } catch (error) {
            if (error.name !== 'AbortError') failedAreas(error);
        }
    };
    const searchAreas = (query, now = false) => {
        clearTimeout(areaTimer);
        if (!query) {
            showIntro();

            return;
        }
        if (query.length < 3) {
            areaItems = [];
            areaResults.replaceChildren();
            areaMore.hidden = true;
            areaState.textContent = 'Ketik minimal 3 huruf.';

            return;
        }
        const cached = areaCache.get(query.toLowerCase());
        if (cached) {
            showAreas(cached, query);

            return;
        }
        areaState.textContent = 'Mencari…';
        if (now) runAreaSearch(query);
        else areaTimer = setTimeout(() => runAreaSearch(query), 250);
    };
    // On phones the full-screen search is exactly as tall as the space above the keyboard and follows the visible
    // area when the browser shifts it, so the results scroll inside it and nothing jumps.
    const fitAreaCard = () => {
        const viewport = window.visualViewport;
        if (!viewport) return;
        areaDialog.style.setProperty('--area-h', `${Math.floor(viewport.height)}px`);
        areaDialog.style.marginTop = window.matchMedia('(min-width: 640px)').matches ? '' : `${Math.max(0, Math.floor(viewport.offsetTop))}px`;
    };
    const openAreaSearch = () => {
        openDialog(areaDialog);
        fitAreaCard();
        window.visualViewport?.addEventListener('resize', fitAreaCard);
        window.visualViewport?.addEventListener('scroll', fitAreaCard);
        areaSearch.value = '';
        showIntro();
        areaSearch.focus({ preventScroll: true });
    };
    areaDialog.addEventListener('close', () => {
        window.visualViewport?.removeEventListener('resize', fitAreaCard);
        window.visualViewport?.removeEventListener('scroll', fitAreaCard);
    });
    areaButton.addEventListener('click', openAreaSearch);
    areaSearch.addEventListener('input', () => searchAreas(areaSearch.value.trim()));
    areaSearch.addEventListener('keydown', (event) => {
        const rows = areaRows();
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (!rows.length) return;
            event.preventDefault();
            highlightArea((areaActive + (event.key === 'ArrowDown' ? 1 : rows.length - 1) + (areaActive < 0 && event.key === 'ArrowUp' ? 1 : 0)) % rows.length);
        } else if (event.key === 'Enter') {
            // Never the checkout form. A result highlighted with the arrow keys is taken; otherwise Enter / the
            // keyboard's Search key searches now and closes the keyboard so the customer can read and tap a result.
            event.preventDefault();
            if (areaActive >= 0 && rows[areaActive]) {
                rows[areaActive].click();

                return;
            }
            searchAreas(areaSearch.value.trim(), true);
            if (window.matchMedia('(pointer: coarse)').matches) areaSearch.blur();
        }
    });

    // ORD-17: the chosen pin on a small map, and a hint when OpenStreetMap places it in another kecamatan.
    const showPreview = async () => {
        if (!pin) {
            preview.hidden = true;

            return;
        }
        preview.hidden = false;
        const { createPreviewMap } = await import('./checkout-shipping/map.js');
        if (!previewMap) previewMap = createPreviewMap(preview.querySelector('[data-pin-preview-map]'), pin);
        previewMap.show(pin);
    };
    const showMismatch = () => {
        const differs = pinWarning(area?.label, pin, pinKecamatan, config.kecamatan);
        mismatch.hidden = !differs;
        if (!differs) return;
        mismatch.querySelector('[data-pin-mismatch-text]').textContent = `Titik antar ini sepertinya di Kecamatan ${pinKecamatan}, sedangkan kecamatan yang dipilih ${areaParts(area.label).title}. Pastikan titik dan kecamatan sesuai.`;
        mismatch.querySelector('[data-pin-mismatch-fix]').textContent = `Ganti kecamatan ke ${pinKecamatan}`;
    };
    mismatch.querySelector('[data-pin-mismatch-fix]').addEventListener('click', async () => {
        try {
            const exact = exactArea(await fetchAreas(pinKecamatan), pinKecamatan);
            if (exact) setArea(exact); else openAreaSearch();
        } catch {
            openAreaSearch();
        }
    });
    // ORD-19: the kecamatan of a new pin fills itself in; where two kecamatan meet the customer taps one, and when
    // it cannot be read the search is offered. The customer can always change it with the kecamatan field.
    const offerPinAreas = (text, choices) => {
        pinAreas.hidden = !text;
        pinAreas.querySelector('[data-pin-areas-text]').textContent = text ?? '';
        pinAreas.querySelector('[data-pin-areas-buttons]').replaceChildren(...choices.map(({ label, onPick }) => {
            const button = Object.assign(document.createElement('button'), { type: 'button', textContent: label });
            button.className = 'min-h-11 border border-brand-black bg-white px-4 text-sm font-medium text-brand-black [touch-action:manipulation] active:bg-gray-100';
            button.addEventListener('click', () => {
                offerPinAreas(null, []);
                onPick();
            });

            return button;
        }));
    };
    const checkPinKecamatan = async ({ fill = false } = {}) => {
        pinKecamatan = null;
        showMismatch();
        offerPinAreas(null, []);
        if (!pin) return;
        const asked = pin;
        if (fill) {
            areaText.textContent = 'Membaca kecamatan dari titik…';
            areaText.classList.add('text-gray-500');
        }
        let found = [];
        try {
            const response = await fetch(`${config.pinUrl}?latitude=${asked[0]}&longitude=${asked[1]}`, { headers: { Accept: 'application/json' } });
            const data = await response.json().catch(() => ({}));
            if (asked !== pin) return;
            pinKecamatan = response.ok ? (data.kecamatan ?? null) : null;
            found = response.ok && Array.isArray(data.areas) ? data.areas : [];
        } catch {
            if (asked !== pin) return;
        }
        if (!fill) {
            showMismatch();

            return;
        }
        if (found.length === 1) {
            if (found[0].id !== area?.id) setArea(found[0]);
            else setArea(area, { requote: false });
        } else {
            setArea(area?.id && found.some((entry) => entry.id === area.id) ? area : null, { requote: false });
            if (found.length > 1) {
                offerPinAreas('Titik ini di perbatasan kecamatan. Pilih yang sesuai:', found.map((entry) => ({ label: areaParts(entry.label).title, onPick: () => setArea(entry) })));
            } else {
                offerPinAreas('Kecamatan belum terbaca dari titik ini.', [{ label: 'Cari kecamatan', onPick: () => openAreaSearch() }]);
            }
        }
        showMismatch();
    };

    // The form refused to send without an option: point at the first thing that is missing.
    form.addEventListener('shipping-missing', () => {
        const focusOn = (element, message) => {
            hint.hidden = false;
            hint.textContent = message;
            hint.classList.add('text-red-700');
            choices.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'center' });
            element?.focus({ preventScroll: true });
        };
        if (!area && !pin) {
            const pinButton = pinBlock.querySelector('[data-open-location]');
            focusOn(pinButton, 'Pasang titik antar dulu (luar Palu: isi kecamatan atau kota), lalu pilih pengiriman.');
            pinButton.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'center' });
        } else if (!area) {
            focusOn(areaButton, 'Isi kecamatan atau kota tujuan dulu, lalu pilih pengiriman.');
            areaButton.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'center' });
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
    if (pin) {
        showPreview();
        checkPinKecamatan();
    }
    sync();
}
