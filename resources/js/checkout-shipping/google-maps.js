// ORD-24 (Owner 2026-11-15): Google Maps for the pin map when the site has a browser key (GOOGLE_MAPS_BROWSER_KEY).
// The key is restricted to the site's own address in Google Cloud and capped per day below the free monthly use.
let loading = null;

export function loadGoogleMaps(key) {
    if (window.google?.maps?.importLibrary) return Promise.resolve(window.google.maps);
    loading ??= new Promise((resolve, reject) => {
        const callback = '__qammarisMapsReady';
        window[callback] = () => resolve(window.google.maps);
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?${new URLSearchParams({ key, v: 'weekly', loading: 'async', language: 'id', region: 'ID', callback })}`;
        script.async = true;
        script.onerror = () => {
            loading = null;
            reject(new Error('google-maps'));
        };
        document.head.append(script);
    });

    return loading;
}

const latLng = ([lat, lng]) => ({ lat, lng });

/** The map behind the fixed centre pin, with the same small interface as the OpenStreetMap one. */
export async function googlePinMap(element, { center, zoom, googleKey }) {
    const maps = await loadGoogleMaps(googleKey);
    const { Map } = await maps.importLibrary('maps');
    const map = new Map(element, {
        center: latLng(center), zoom, disableDefaultUI: true, zoomControl: true, gestureHandling: 'greedy', clickableIcons: false,
    });

    return {
        center: () => {
            const point = map.getCenter();

            return [point.lat(), point.lng()];
        },
        setView: (point, level) => {
            map.setCenter(latLng(point));
            map.setZoom(level ?? Math.max(map.getZoom(), 16));
        },
        refresh: () => {},
    };
}

/** ORD-17: the chosen pin on a small, still map. */
export async function googlePreviewMap(element, point, googleKey) {
    const maps = await loadGoogleMaps(googleKey);
    const { Map } = await maps.importLibrary('maps');
    const map = new Map(element, { center: latLng(point), zoom: 17, disableDefaultUI: true, gestureHandling: 'none', clickableIcons: false, keyboardShortcuts: false });

    return { show: (next) => map.setCenter(latLng(next)) };
}

/**
 * Place search inside the service area. One autocomplete session per chosen place: Google bills the session's
 * suggestions together with the one place's location.
 */
export function googlePlaces(googleKey, area) {
    let token = null;

    return {
        search: async (query, signal) => {
            const maps = await loadGoogleMaps(googleKey);
            const { AutocompleteSuggestion, AutocompleteSessionToken } = await maps.importLibrary('places');
            token ??= new AutocompleteSessionToken();
            const { suggestions } = await AutocompleteSuggestion.fetchAutocompleteSuggestions({
                input: query, sessionToken: token, language: 'id', region: 'id', includedRegionCodes: ['id'],
                locationRestriction: { north: area.north, south: area.south, east: area.east, west: area.west },
            });
            if (signal?.aborted) throw new DOMException('aborted', 'AbortError');

            return suggestions.flatMap(({ placePrediction: prediction }) => (prediction
                ? [{ label: prediction.mainText?.text ?? prediction.text.text, detail: prediction.secondaryText?.text ?? '', prediction }]
                : []));
        },
        point: async (item) => {
            const place = item.prediction.toPlace();
            await place.fetchFields({ fields: ['location'] });
            token = null;

            return [place.location.lat(), place.location.lng()];
        },
    };
}
