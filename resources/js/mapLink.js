import { mapStateUrl, normalizeTrackReference, parseMapUrlState } from './mapUrlState.js';
import { resourceNavigationUrl } from './navigationState.js';
import { normalizeSharedMapState } from './sharedMapState.js';

export function fullMapUrl(record, baseUrl) {
    if (!record?.state) return '';
    const state = normalizeSharedMapState(record.state);
    const trackToken = record.track_token ?? record.track?.token ?? null;
    let resource = resourceNavigationUrl(baseUrl, state.page);
    if (record.resource_url) {
        const resolved = new URL(record.resource_url, baseUrl);
        if (resolved.origin === new URL(baseUrl).origin && !resolved.username && !resolved.password
            && /^\/(?:ru(?:\/|$))?(?:(?:users\/)?\d+\/?)?$/.test(resolved.pathname)) resource = resolved;
    }
    return mapStateUrl(resource, state, trackToken).href;
}

export async function resolveMapLink(value, { baseUrl, fetch = globalThis.fetch, signal, locale = 'en', message = 'Invalid map URL' }) {
    const invalid = () => new Error(message);
    let url;
    try {
        url = new URL(value.trim(), baseUrl);
    } catch {
        throw invalid();
    }
    if (!value.trim() || !['https:', 'http:'].includes(url.protocol) || url.origin !== new URL(baseUrl).origin
        || url.username || url.password) throw invalid();

    const path = url.pathname.replace(/^\/(en|ru)(?=\/|$)/, '') || '/';
    if (path.replace(/\/$/, '') === '/maps/options') throw invalid();
    if (/^\/maps\/[a-z0-9][a-z0-9-]{2,79}\/?$/.test(path)) {
        if (url.hash || url.search) throw invalid();
        const response = await fetch(url.href, {
            credentials: 'same-origin', redirect: 'error', signal,
            headers: { Accept: 'application/json', 'X-Rodnik-Locale': locale },
        });
        if (!response.ok) throw invalid();
        const record = await response.json();
        let state;
        try { state = normalizeSharedMapState(record.state); } catch { throw invalid(); }
        const trackToken = record.track?.token ?? record.track_token ?? null;
        const reference = normalizeTrackReference(trackToken);
        if ((record.track || trackToken !== null) && !reference) throw invalid();
        return { state, track_token: reference, ...(record.resource_url ? { resource_url: record.resource_url } : {}) };
    }

    const resource = path.match(/^\/(?:(\d+)|users\/(\d+))?\/?$/);
    const parsed = parseMapUrlState(url.href);
    if (!resource || !parsed) throw invalid();
    const fragment = new URLSearchParams(url.hash.slice(1));
    if ((fragment.has('track') || fragment.has('t')) && !parsed.trackToken) throw invalid();

    const identifier = value => {
        if (value === null || value === '' || value === '0') return null;
        if (!/^\d+$/.test(value) || !Number.isSafeInteger(Number(value)) || Number(value) < 1) throw invalid();
        return Number(value);
    };
    const query = url.searchParams;
    const legacy = name => query.get(`page[${name}]`) ?? query.get(`view[${name}]`);
    const spring = identifier(resource[1] ?? query.get('spring') ?? legacy('spring') ?? query.get('s') ?? query.get('spring_id'));
    const user = identifier(resource[2] ?? query.get('user') ?? legacy('user') ?? query.get('u'));
    const location = query.get('location') ?? legacy('location') ?? query.get('locating');
    return {
        state: normalizeSharedMapState({ ...parsed.state, page: { spring, user, location: location && location !== '0' ? 1 : null } }),
        track_token: parsed.trackToken,
    };
}
