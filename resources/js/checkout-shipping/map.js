// ORD-13: the map behind the fixed centre pin. Loaded only when the customer opens the map.
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export function createPinMap(element, { center, zoom, pin }) {
    const map = L.map(element, { zoomControl: true, attributionControl: true }).setView(center, zoom);
    map.attributionControl.setPrefix(false);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
    }).addTo(map);
    // The pin lifts while the map moves and drops when it settles, so the customer sees where it lands.
    map.on('movestart', () => { pin.style.translate = '0 -10px'; });
    map.on('moveend', () => { pin.style.translate = ''; });

    return {
        center: () => {
            const point = map.getCenter();

            return [point.lat, point.lng];
        },
        setView: (latlng, level) => map.setView(latlng, level ?? Math.max(map.getZoom(), 16)),
        refresh: () => map.invalidateSize(),
    };
}
