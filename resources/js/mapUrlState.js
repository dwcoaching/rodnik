import { normalizeSharedMapState, sharedMapFilterDefaults, sharedMapPage, sharedMapSources } from './sharedMapState.js';
import { normalizeReportScopeUrl, resourceNavigationUrl } from './navigationState.js';

const sourceTypes = Object.keys(sharedMapFilterDefaults).filter(key => sharedMapFilterDefaults[key]);
const overlayNames = ['stravaPublic', 'osmTraces'];
const decimal = /^-?(?:\d+(?:\.\d*)?|\.\d+)$/;
const trackToken = /^[a-zA-Z\d]{10}$/;
const layerNames = { osm: 'osm', mapy: 'mapy', outdoors: 'outdoors', openTopoMap: 'topo', terrain: 'terrain', satellite: 'satellite' };
const filterNames = { spring: 'spring', water_well: 'well', water_tap: 'tap', drinking_water: 'drinking', fountain: 'fountain', other: 'other', with_reports: 'reports', along: 'along' };
const overlayAliases = { stravaPublic: 'strava', osmTraces: 'traces' };
const viewNames = { fullscreen: 'full', minimized: 'min' };
const legacyLayerCodes = { osm: 'o', mapy: 'm', outdoors: 'h', openTopoMap: 'p', terrain: 't', satellite: 's' };
const legacyFilterBits = ['spring', 'water_well', 'water_tap', 'drinking_water', 'fountain', 'other', 'with_reports', 'along'];

export function normalizeTrackReference(value) {
    if (typeof value !== 'string') return null;
    return trackToken.test(value) ? value : null;
}

function flags(value, maximum) {
    return typeof value === 'string' && /^[a-f\d]{1,2}$/i.test(value) && parseInt(value, 16) <= maximum
        ? parseInt(value, 16) : null;
}

function enabledNames(value, names) {
    return value.split(',').filter(name => names.includes(name));
}

function namedFlags(value, aliases) {
    if (typeof value !== 'string') return null;
    const values = value.split(',');
    if (!values.every(name => /^[a-z][a-zA-Z0-9_-]*$/.test(name))) return null;
    const names = Object.keys(aliases);
    const enabled = names.filter(name => values.includes(aliases[name]) || values.includes(name));
    return Object.fromEntries(names.map(name => [name, enabled.includes(name)]));
}

function namedValues(state, aliases) {
    return Object.entries(aliases).filter(([name]) => state[name]).map(([, value]) => value).join(',');
}

export function parseMapUrlState(href) {
    let parameters;
    try {
        parameters = new URLSearchParams(new URL(href).hash.slice(1));
    } catch {
        return null;
    }

    const camera = (parameters.get('m') ?? parameters.get('map'))?.split('/');
    if (camera?.length !== 3 || !camera.every(value => decimal.test(value))) return null;
    const [zoom, latitude, longitude] = camera.map(Number);
    const filters = { ...sharedMapFilterDefaults };

    if (parameters.has('filters')) {
        const value = parameters.get('filters');
        const enabled = enabledNames(value, sourceTypes);
        if (value === '' || enabled.length > 0) {
            for (const name of sourceTypes) filters[name] = enabled.includes(name);
        }
    }

    filters.with_reports = parameters.get('reports') === '1';
    filters.along = parameters.get('along') === '1';
    const overlays = enabledNames(parameters.get('overlays') ?? '', overlayNames);
    const filterFlags = flags(parameters.get('f'), 255);
    if (filterFlags !== null) legacyFilterBits.forEach((name, index) => { filters[name] = Boolean(filterFlags & (1 << index)); });
    if (filterFlags === null) Object.assign(filters, namedFlags(parameters.get('f'), filterNames));
    const overlayFlags = flags(parameters.get('o'), 3);
    const viewFlags = flags(parameters.get('v'), 3);
    const layer = Object.keys(layerNames).find(name => [name, layerNames[name], legacyLayerCodes[name]].includes(parameters.get('l')));
    let state;
    try {
        const { page, ...normalized } = normalizeSharedMapState({
            version: 1,
            center: [longitude, latitude],
            zoom,
            sourceName: layer ?? parameters.get('layer'),
            filters,
            overlays: {
                ...Object.fromEntries(overlayNames.map((name, index) => [name, overlayFlags === null ? overlays.includes(name) : Boolean(overlayFlags & (1 << index))])),
                ...(overlayFlags === null ? namedFlags(parameters.get('o'), overlayAliases) : null),
            },
            fullscreen: viewFlags === null ? parameters.get('fullscreen') === '1' : Boolean(viewFlags & 1),
            minimized: viewFlags === null ? parameters.get('minimized') === '1' : Boolean(viewFlags & 2),
            ...(viewFlags === null ? namedFlags(parameters.get('v'), viewNames) : null),
        });
        state = normalized;
    } catch {
        return null;
    }

    return { state, trackToken: normalizeTrackReference(parameters.get('t') ?? parameters.get('track')) };
}

export function mapStateUrl(href, state, trackToken = null) {
    let url = normalizeReportScopeUrl(new URL(href));
    const normalized = normalizeSharedMapState(state);
    if (/^\/(?:ru\/)?maps\/[^/]+\/?$/.test(url.pathname)) {
        url = resourceNavigationUrl(href, normalized.page);
    }
    if (url.searchParams.has('t')) url.searchParams.delete('t');
    const compact = (value, precision) => Number(value.toFixed(precision)).toString();
    const camera = [compact(normalized.zoom, 2), compact(normalized.center[1], 6), compact(normalized.center[0], 6)];
    const parameters = [`m=${camera.join('/')}`];

    if (normalized.sourceName !== sharedMapSources[0]) parameters.push(`l=${layerNames[normalized.sourceName]}`);
    if (Object.keys(filterNames).some(name => normalized.filters[name] !== sharedMapFilterDefaults[name])) {
        parameters.push(`f=${namedValues(normalized.filters, filterNames) || 'none'}`);
    }
    const overlays = namedValues(normalized.overlays, overlayAliases);
    if (overlays) parameters.push(`o=${overlays}`);
    const track = normalizeTrackReference(trackToken);
    if (track) parameters.push(`t=${track}`);
    const view = namedValues(normalized, viewNames);
    if (view) parameters.push(`v=${view}`);

    url.hash = parameters.join('&');
    return url;
}

export function initialMapConfiguration(href, page, sharedMap = null) {
    const parsed = parseMapUrlState(href);
    if (parsed) {
        return {
            state: { ...parsed.state, page: sharedMapPage(page) },
            track: parsed.trackToken,
        };
    }

    const url = new URL(href);
    const parameters = new URLSearchParams(url.hash.slice(1));
    const track = normalizeTrackReference(parameters.get('t') ?? url.searchParams.get('t'));
    return {
        state: sharedMap?.state ?? null,
        track: track ?? sharedMap?.track ?? null,
    };
}
