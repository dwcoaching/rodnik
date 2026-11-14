const opacityStorageKey = 'rodnik.sourceOverviewOpacity';
const typeFilters = ['spring', 'water_well', 'water_tap', 'drinking_water', 'fountain', 'other'];

export function sourceTypeMask(filters = {}) {
    return typeFilters.reduce((mask, key, index) => filters[key] === false ? mask : mask | (1 << index), 0);
}

export function normalizeOverviewManifest(value) {
    if (!value || typeof value.revision !== 'string' || !/^[a-zA-Z0-9_-]+$/.test(value.revision)
        || !Number.isInteger(value.max_zoom) || value.max_zoom < 0 || value.max_zoom > 16
        || !Number.isInteger(value.tile_size) || value.tile_size < 256 || value.tile_size > 1024
        || ![1, 2].includes(value.tile_pixel_ratio)
        || typeof value.url !== 'string' || !value.url.startsWith('/') || value.url.startsWith('//')
        || !['{z}', '{x}', '{y}'].every(part => value.url.includes(part))) return null;

    return {
        revision: value.revision,
        max_zoom: value.max_zoom,
        tile_size: value.tile_size,
        tile_pixel_ratio: value.tile_pixel_ratio,
        url: value.url,
    };
}

export function sourceOverviewUrl(manifest, filters = {}) {
    // A route selects few sources; the vector layers draw exactly those.
    if (filters.along) return null;
    const parameters = new URLSearchParams({ types: sourceTypeMask(filters) });
    if (filters.with_reports) parameters.set('reports', '1');
    return `${manifest.url}${manifest.url.includes('?') ? '&' : '?'}${parameters}`;
}

export function normalizeSourceOpacity(value, fallback = 0.85) {
    if (value === null || value === undefined || value === '') return fallback;
    const number = Number(value);
    return Number.isFinite(number) ? Math.min(1, Math.max(0, number)) : fallback;
}

export function readSourceOpacity() {
    try { return normalizeSourceOpacity(localStorage.getItem(opacityStorageKey)); }
    catch { return 0.85; }
}

export function saveSourceOpacity(value) {
    try { localStorage.setItem(opacityStorageKey, String(value)); }
    catch { /* The map also works when browser storage is unavailable. */ }
}
