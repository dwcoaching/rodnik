export function normalizeReportScopeUrl(url) {
    const value = url.searchParams.get('w') ?? url.searchParams.get('whole_world');
    if (url.searchParams.has('whole_world')) url.searchParams.delete('whole_world');
    if (value === '1' || value === '0') url.searchParams.set('w', value);
    return url;
}

export function localizedNavigationUrl(href, locale) {
    const url = normalizeReportScopeUrl(new URL(href));
    const path = url.pathname.replace(/^\/(en|ru)(?=\/|$)/, '') || '/';
    url.pathname = locale === 'ru' ? (path === '/' ? '/ru' : `/ru${path}`) : path;
    return url;
}

export function resourceNavigationUrl(href, page) {
    const current = new URL(href);
    const prefix = /^\/ru(?:\/|$)/.test(current.pathname) ? '/ru' : '';
    const url = new URL(current.origin);
    const spring = Number(page.spring) || null;
    const user = Number(page.user) || null;
    url.pathname = spring ? `${prefix}/${spring}/` : user ? `${prefix}/users/${user}/` : (prefix || '/');
    // Resource selection changes identity while retaining independent shared context.
    for (const [key, value] of current.searchParams) {
        if (!['spring', 'spring_id', 'user', 'location', 's', 'u', 'locating', 'redirect'].includes(key) && !key.startsWith('page[') && !key.startsWith('view[')) {
            url.searchParams.append(key, value);
        }
    }
    if (spring && user) url.searchParams.set('user', user);
    if (page.location) url.searchParams.set('location', page.location);
    url.hash = current.hash;
    return normalizeReportScopeUrl(url);
}

export class NavigationSnapshots {
    constructor(storage) {
        this.storage = storage;
        this.memory = new Map();
        this.tracks = new Map();
        this.trackIds = new WeakMap();
    }

    get(id) {
        if (!id) return null;
        let snapshot = this.memory.get(id);
        try {
            snapshot ??= JSON.parse(this.storage.getItem(`rodnik.navigation.${id}`));
        } catch {
            // An in-memory snapshot remains usable if persistent storage is disabled.
        }
        if (!snapshot) return null;
        if (!snapshot.map?.trackId) return snapshot;
        const { trackId, ...map } = snapshot.map;
        let track = this.tracks.get(trackId);
        if (!track) {
            try { track = JSON.parse(this.storage.getItem(`rodnik.navigation.track.${trackId}`)); } catch { /* Storage may be unavailable. */ }
            if (track) this.tracks.set(trackId, track);
        }
        return { ...snapshot, map: { ...map, track } };
    }

    set(id, snapshot) {
        if (!id) return;
        let prepared = snapshot;
        const track = snapshot.map?.track;
        if (track && typeof track === 'object') {
            let trackId = this.trackIds.get(track);
            if (!trackId) {
                trackId = globalThis.crypto.randomUUID();
                const encoded = JSON.stringify(track);
                const immutableTrack = JSON.parse(encoded);
                this.trackIds.set(track, trackId);
                this.trackIds.set(immutableTrack, trackId);
                this.tracks.set(trackId, immutableTrack);
                try { this.storage.setItem(`rodnik.navigation.track.${trackId}`, encoded); } catch { /* Keep the in-memory track. */ }
            }
            const { track: omitted, ...map } = snapshot.map;
            prepared = { ...snapshot, map: { ...map, trackId } };
        }
        // Track geometry is immutable and stored once, outside lightweight per-entry state.
        const encoded = JSON.stringify(prepared);
        const value = JSON.parse(encoded);
        if (JSON.stringify(this.memory.get(id)) === encoded) return;
        this.memory.set(id, value);
        try {
            this.storage.setItem(`rodnik.navigation.${id}`, encoded);
        } catch {
            // History still works in this tab when browser storage is unavailable/full.
        }
    }
}

export class NavigationRequests {
    constructor() {
        this.controller = null;
        this.cancelled = new WeakSet();
    }

    start() {
        this.cancel();
        this.controller = new AbortController();
        return this.controller;
    }

    cancel() {
        if (!this.controller || this.controller.signal.aborted) return;
        const reason = new DOMException('Navigation superseded', 'AbortError');
        this.cancelled.add(reason);
        this.controller.abort(reason);
    }

    attach(options) {
        if (!this.controller) return;
        options.signal = options.signal
            ? AbortSignal.any([options.signal, this.controller.signal])
            : this.controller.signal;
    }

    owns(error) {
        return error !== null && typeof error === 'object' && this.cancelled.has(error);
    }
}
