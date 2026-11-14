// Admin-only raster lab (/admin/raster-lab): tune the source overview style on a
// map of its own. Delete this folder, its Vite entry, the Blade view and the
// "admin/raster-lab" route to remove the page; nothing else imports it.
import Alpine from 'alpinejs';
import Map from 'ol/Map.js';
import View from 'ol/View.js';
import TileLayer from 'ol/layer/Tile.js';
import OSM from 'ol/source/OSM.js';
import XYZ from 'ol/source/XYZ.js';
import { fromLonLat, toLonLat } from 'ol/proj.js';
import SpringsFinalLayer from '@/layers/springs/final';
import SpringsFinalSource from '@/sources/final';
import { readSourceOpacity } from '../sourceOverview.js';
import panel, { previewUrl } from './panel.js';

// The real marker style reads the main map's filters; here every source is shown.
window.rodnikMap = { filters: { spring: true, water_well: true, water_tap: true, drinking_water: true, fountain: true, other: true, with_reports: false, along: false } };

const start = (location.hash.match(/^#m=([\d.]+)\/(-?[\d.]+)\/(-?[\d.]+)$/) ?? [null, 10, 41.3851, 2.1734]).slice(1).map(Number);
const view = new View({ center: fromLonLat([start[2], start[1]]), zoom: start[0], maxZoom: 18, constrainResolution: false });
// Same default opacity as visitors get from the main map's Layers slider.
const raster = new TileLayer({ opacity: readSourceOpacity(), maxZoom: 10, zIndex: 300 });
const markers = new SpringsFinalLayer();
markers.setSource(new SpringsFinalSource());
markers.setMinZoom(10);
const map = new Map({ target: 'raster-lab-map', view, layers: [new TileLayer({ source: new OSM() }), raster, markers] });

let manifest = null;
let draft = null;

function draw() {
    if (!manifest) return raster.setSource(null);
    raster.setSource(new XYZ({
        ...(draft
            ? { tileUrlFunction: ([z, x, y]) => previewUrl(draft, z, x, y) }
            : { url: `${manifest.url}?types=63` }),
        minZoom: 0, maxZoom: manifest.max_zoom, tileSize: 256, tilePixelRatio: 2, transition: 0, wrapX: true,
    }));
}

async function loadManifest() {
    // The admin endpoint, not /source-tiles.json: that one is off while visitors get the vector overview.
    const response = await fetch('/admin/source-raster/style', { cache: 'no-store', credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const body = response.ok ? await response.json() : null;
    manifest = body?.url ? { url: body.url, max_zoom: body.max_zoom } : null;
    draw();
}

map.on('moveend', () => {
    const [longitude, latitude] = toLonLat(view.getCenter());
    history.replaceState(null, '', `#m=${view.getZoom().toFixed(2)}/${latitude.toFixed(4)}/${longitude.toFixed(4)}`);
});

Alpine.data('rasterLab', () => Object.assign(panel({
    zoom: () => Math.max(0, Math.min(10, Math.round(view.getZoom()))),
    onMove: callback => map.on('moveend', callback),
    preview: value => { draft = value; draw(); },
    published: () => { draft = null; loadManifest(); },
}), {
    mapZoom: view.getZoom(),
    markersFrom: 10,
    setMarkers(value) {
        this.markersFrom = Number(value);
        markers.setVisible(this.markersFrom <= 18);
        markers.setMinZoom(this.markersFrom);
    },
    go(longitude, latitude, zoom) { view.animate({ center: fromLonLat([longitude, latitude]), zoom, duration: 300 }); },
    trackZoom() { map.on('moveend', () => { this.mapZoom = view.getZoom(); }); },
}));

loadManifest();
Alpine.start();
