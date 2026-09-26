export const sharedMapFilterDefaults = Object.freeze({
    spring: true,
    water_well: true,
    water_tap: true,
    drinking_water: true,
    fountain: true,
    other: true,
    with_reports: false,
    along: false,
});

export const sharedMapSources = Object.freeze(['osm', 'mapy', 'outdoors', 'openTopoMap', 'terrain', 'satellite']);

export function sharedMapFilters(filters) {
    return Object.fromEntries(Object.entries(sharedMapFilterDefaults).map(([key, fallback]) => [
        key, typeof filters?.[key] === 'boolean' ? filters[key] : fallback,
    ]));
}

export function sharedMapPage(page) {
    const identifier = value => Number.isSafeInteger(Number(value)) && Number(value) > 0 ? Number(value) : null;
    return {
        spring: identifier(page?.spring),
        user: identifier(page?.user),
        location: page?.location ? 1 : null,
    };
}

export function normalizeSharedMapState(state) {
    if (state?.version !== 1 || !Array.isArray(state.center) || state.center.length !== 2
        || !state.center.every(Number.isFinite) || Math.abs(state.center[0]) > 180
        || Math.abs(state.center[1]) > 90 || !Number.isFinite(state.zoom) || state.zoom < 0 || state.zoom > 28) {
        throw new Error('The shared map state is invalid.');
    }

    return {
        version: 1,
        center: [...state.center],
        zoom: state.zoom,
        sourceName: sharedMapSources.includes(state.sourceName) ? state.sourceName : 'osm',
        filters: sharedMapFilters(state.filters),
        overlays: {
            stravaPublic: state.overlays?.stravaPublic === true,
            osmTraces: state.overlays?.osmTraces === true,
        },
        page: sharedMapPage(state.page),
        fullscreen: state.fullscreen === true,
        minimized: state.minimized === true,
    };
}

export async function afterMapUiReady() {
    await globalThis.Alpine?.nextTick?.();
    await new Promise(resolve => {
        if (globalThis.requestAnimationFrame) {
            globalThis.requestAnimationFrame(() => setTimeout(resolve, 0));
        } else {
            setTimeout(resolve, 0);
        }
    });
}
