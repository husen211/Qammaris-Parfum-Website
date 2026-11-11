// ORD-13: the map behind the fixed centre pin. Loaded only when the customer opens the map.
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/** ORD-17: the chosen pin on a small, still map (no dragging or zooming, so the page scrolls normally). */
export function createPreviewMap(element, point) {
    const map = L.map(element, { zoomControl: false, attributionControl: false, dragging: false, touchZoom: false, scrollWheelZoom: false,
        doubleClickZoom: false, boxZoom: false, keyboard: false }).setView(point, 17);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, referrerPolicy: 'strict-origin' }).addTo(map);

    return { show: (latlng) => { map.setView(latlng, 17); map.invalidateSize(); } };
}

export function createPinMap(element, { center, zoom }) {
    const map = L.map(element, { zoomControl: true, attributionControl: true }).setView(center, zoom);
    map.attributionControl.setPrefix(false);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        // The checkout sends no referrer; OpenStreetMap's tile servers refuse tiles without one ("Access blocked").
        // Only the site's origin is sent, never the page path.
        referrerPolicy: 'strict-origin',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
    }).addTo(map);

    return {
        center: () => {
            const point = map.getCenter();

            return [point.lat, point.lng];
        },
        setView: (latlng, level) => map.setView(latlng, level ?? Math.max(map.getZoom(), 16)),
        refresh: () => map.invalidateSize(),
    };
}
