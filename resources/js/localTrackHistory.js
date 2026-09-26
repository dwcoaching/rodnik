const storagePrefix = 'rodnik.local-track.';
const stores = new WeakMap();

function storage(window) {
    if (!stores.has(window)) stores.set(window, { tracks: new Map(), references: new WeakMap() });
    return stores.get(window);
}

function freeze(value) {
    if (value && typeof value === 'object' && !Object.isFrozen(value)) {
        Object.values(value).forEach(freeze);
        Object.freeze(value);
    }
    return value;
}

export function captureLocalTrackHistory(window, map, ownerId) {
    if (!['local', 'saving', 'failed'].includes(map?.sharedTrack?.status) || map.sharedTrack.token) return null;
    const track = map.tracks?.operation?.track ?? map.captureNavigationState?.().track;
    if (track?.type !== 'FeatureCollection' || !track.features?.length) return null;

    const store = storage(window);
    const owner = String(ownerId ?? '');
    const existing = store.references.get(track);
    if (existing?.ownerId === owner) return existing;

    const reference = { id: window.crypto.randomUUID(), ownerId: owner };
    const encoded = JSON.stringify({ ownerId: owner, track });
    const record = freeze(JSON.parse(encoded));
    store.tracks.set(reference.id, record);
    store.references.set(track, reference);
    store.references.set(record.track, reference);
    try { window.sessionStorage.setItem(storagePrefix + reference.id, encoded); } catch { /* Keep in-tab navigation usable when storage is full or unavailable. */ }
    return reference;
}

export function readLocalTrackHistory(window, ownerId, href = window.location.href) {
    const entry = window.history.state?.rodnik;
    const url = new URL(href, window.location.href);
    const fragment = new URLSearchParams(url.hash.slice(1));
    if (!entry?.localTrackId || entry.url !== url.href || entry.localTrackOwnerId !== String(ownerId ?? '')
        || !/^\/(?:ru(?:\/|$))?(?:(?:users\/)?\d+\/?)?$/.test(url.pathname)
        || fragment.has('track') || fragment.has('t') || url.searchParams.has('t')) return null;

    const store = storage(window);
    let record = store.tracks.get(entry.localTrackId);
    try {
        record ??= freeze(JSON.parse(window.sessionStorage.getItem(storagePrefix + entry.localTrackId)));
    } catch { return null; }
    if (record?.ownerId !== String(ownerId ?? '') || record.track?.type !== 'FeatureCollection'
        || !Array.isArray(record.track.features) || !record.track.features.length) return null;

    store.tracks.set(entry.localTrackId, record);
    store.references.set(record.track, { id: entry.localTrackId, ownerId: record.ownerId });
    return record.track;
}
