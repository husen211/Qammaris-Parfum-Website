// ORD-24: one entry for the pin map and place search. Google Maps when the page has a browser key; otherwise the
// OpenStreetMap map and Photon search as before. Pasted coordinates or Google Maps links work with both.
import { placeResults, pointFromText } from './logic.js';

// Google not loading (blocked, offline, key refused) falls back to the OpenStreetMap map, so a pin can always be set.
export async function createPinMap(element, options) {
    if (options.googleKey) {
        try {
            return await (await import('./google-maps.js')).googlePinMap(element, options);
        } catch { /* fall back below */ }
    }

    return (await import('./map.js')).createPinMap(element, options);
}

export async function createPreviewMap(element, point, googleKey) {
    if (googleKey) {
        try {
            return await (await import('./google-maps.js')).googlePreviewMap(element, point, googleKey);
        } catch { /* fall back below */ }
    }

    return (await import('./map.js')).createPreviewMap(element, point);
}

/** search(query, signal) → { items, note }; point(item) → [lat, lng] of a chosen item. */
export function placeSearch(config) {
    const area = config.serviceArea;
    const note = 'Belum ketemu. Coba nama jalan atau tempat lain, tempel koordinat, atau geser peta.';
    let google = null;
    const pasted = (query) => {
        const point = pointFromText(query);

        return point ? [{ label: `${point[0]}, ${point[1]}`, detail: 'Titik dari koordinat atau link yang ditempel', point }] : null;
    };

    return {
        search: async (query, signal) => {
            const typed = pasted(query);
            if (typed) return { items: typed, note };
            if (config.googleKey) {
                try {
                    google ??= (await import('./google-maps.js')).googlePlaces(config.googleKey, area);

                    return { items: await google.search(query, signal), note };
                } catch (error) {
                    if (error.name === 'AbortError') throw error;
                    // Google search unavailable (daily cap reached, blocked): the free search below still works.
                }
            }
            const params = new URLSearchParams({ q: query, limit: '6', lat: String(config.origin[0]), lon: String(config.origin[1]),
                bbox: [area.west, area.south, area.east, area.north].join(',') });
            const response = await fetch(`${config.placeSearchUrl}?${params}`, { signal });
            if (!response.ok) throw new Error('search');

            return { items: placeResults(await response.json()), note };
        },
        point: async (item) => item.point ?? google.point(item),
    };
}
