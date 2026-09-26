import { afterMapUiReady } from './sharedMapState.js';
import trans from './i18n.js';
import { normalizeTrackReference } from './mapUrlState.js';

const maxTrackBytes = 10 * 1024 * 1024;
const hashPattern = /^[a-f0-9]{64}$/;
const errorMessages = {
    shared_track_failed: 'The track could not be loaded or saved. Please try again.',
    shared_track_timed_out: 'The track request timed out. Please try again.',
    shared_track_too_large: 'The track is too large. Please choose a file smaller than 10 MiB.',
    shared_track_too_complex: 'The track is too complex to share. Simplify the track or reduce its metadata.',
    shared_track_deleted: 'This track has been deleted. You can still use the map without it.',
};

function trackError(code, cause) {
    const message = typeof window === 'undefined' ? errorMessages[code] : trans(code, errorMessages[code]);
    return Object.assign(new Error(message, { cause }), { code });
}

export function serializeSharedTrack(track) {
    if (track?.type !== 'FeatureCollection' || !Array.isArray(track.features) || !track.features.length) {
        throw trackError('shared_track_failed');
    }
    const serialized = JSON.stringify(track);
    if (new TextEncoder().encode(serialized).byteLength > maxTrackBytes) {
        throw trackError('shared_track_too_large');
    }
    return serialized;
}

export default class Tracks {
    constructor({
        endpoint = globalThis.document?.querySelector('meta[name="tracks-url"]')?.content,
        csrfToken = () => globalThis.document?.querySelector('meta[name="csrf-token"]')?.content,
        fetch = (...args) => globalThis.fetch(...args),
        crypto = globalThis.crypto,
        defer = afterMapUiReady,
        apply = () => {},
        changed = () => {},
        timeoutMs = 120000,
        reactive = value => globalThis.Alpine?.reactive(value) ?? value,
    } = {}) {
        Object.assign(this, { endpoint, csrfToken, fetch, crypto, defer, apply, changed, timeoutMs });
        this.state = reactive({ status: 'idle', id: null, hash: null, token: null, name: null, error: null, uploaded: false });
        this.revision = 0;
        this.clear();
    }

    clear() {
        this.revision++;
        this.controller?.abort();
        this.controller = null;
        this.promise = null;
        this.operation = null;
        this.error = null;
        Object.assign(this.state, { status: 'idle', id: null, hash: null, token: null, name: null, error: null, uploaded: false });
        this.changed();
    }

    replace(track, { name = null } = {}) {
        if (!track?.features?.length) {
            this.clear();
            return Promise.resolve(null);
        }
        const candidate = name ?? track.features.find(feature => typeof feature.properties?.name === 'string')?.properties.name;
        const title = typeof candidate === 'string' ? candidate.trim() : '';
        return this.start({ track, name: Array.from(title).slice(0, 160).join('') || null }, 'saving');
    }

    load(reference) {
        if (!reference) {
            this.clear();
            return Promise.resolve(null);
        }
        const record = typeof reference === 'string' ? { token: reference } : reference;
        return this.start({ reference: record }, 'loading');
    }

    retry() {
        if (!this.operation || this.state.status === 'missing') return Promise.resolve(null);
        return this.start(this.operation, this.operation.reference ? 'loading' : 'saving');
    }

    async recoverMissing(token, { signal } = {}) {
        signal?.throwIfAborted();
        if (this.state.status !== 'saved' || this.state.token !== token) {
            throw trackError('shared_track_failed');
        }
        this.markMissing();
        return null;
    }

    markMissing() {
        this.clear();
        this.apply({ type: 'FeatureCollection', features: [] });
        Object.assign(this.state, { status: 'missing', error: trackError('shared_track_deleted').message });
        this.changed();
    }

    start(operation, status, signal) {
        this.clear();
        this.operation = operation;
        const revision = this.revision;
        const controller = this.controller = new AbortController();
        const requestSignal = signal ? AbortSignal.any([controller.signal, signal]) : controller.signal;
        Object.assign(this.state, { status, hash: operation.reference?.hash ?? null, token: operation.reference?.token ?? null, name: operation.name ?? operation.reference?.name ?? null });
        this.changed();
        this.promise = this.run(operation, requestSignal).then(record => {
            if (revision !== this.revision) return null;
            requestSignal.throwIfAborted();
            if (operation.reference) this.apply(record.track);
            Object.assign(this.state, { status: 'saved', id: record.id, hash: record.hash, token: record.token, name: record.name ?? this.state.name, error: null, uploaded: !operation.reference });
            this.changed();
            return { id: record.id, hash: record.hash, token: record.token, ...(record.name ? { name: record.name } : {}) };
        }).catch(error => {
            if (revision !== this.revision) return null;
            if (error.code === 'shared_track_deleted') {
                this.operation = null;
                this.error = null;
                this.apply({ type: 'FeatureCollection', features: [] });
                Object.assign(this.state, { status: 'missing', id: null, hash: null, token: null, error: error.message });
                this.changed();
                return null;
            }
            const code = Object.hasOwn(errorMessages, error.code) ? error.code
                : error.name === 'TimeoutError' ? 'shared_track_timed_out' : 'shared_track_failed';
            this.error = trackError(code, error);
            Object.assign(this.state, { status: 'failed', error: this.error.message });
            this.changed();
            return null;
        });
        return this.promise;
    }

    async ensure() {
        if (!this.promise) return null;

        const revision = this.revision;
        const record = await this.promise;
        if (revision !== this.revision) {
            if (!this.operation) return null;
            throw trackError('shared_track_failed');
        }
        if (this.error) throw this.error;
        return record;
    }

    async run(operation, signal) {
        await this.defer();
        signal.throwIfAborted();
        if (!this.endpoint) throw new Error('The track endpoint is unavailable.');
        const endpoint = this.endpoint.replace(/\/$/, '');
        if (operation.reference) {
            const reference = normalizeTrackReference(operation.reference.token);
            if (!reference) throw new Error('The track identifier is invalid.');
            const response = await this.request(`${endpoint}/${reference}`, {
                credentials: 'same-origin', headers: { Accept: 'application/json' }, signal,
            });
            if (response.status === 404) throw trackError('shared_track_deleted');
            if (!response.ok) throw new Error(`Track request failed (${response.status}).`);
            return this.validateRecord(await response.json(), { token: reference }, true);
        }

        const track = serializeSharedTrack(operation.track);
        const digest = await this.crypto.subtle.digest('SHA-256', new TextEncoder().encode(track));
        signal.throwIfAborted();
        const hash = Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join('');
        const response = await this.request(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            signal,
            headers: {
                Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken(),
            },
            body: JSON.stringify({ hash, track, ...(operation.name ? { name: operation.name } : {}) }),
        });
        if (!response.ok) {
            if (response.status === 413) throw trackError('shared_track_too_large');
            if (response.status === 422) {
                const body = await response.json().catch(() => ({}));
                const errors = body.errors?.track ?? [];
                if (errors.some(message => typeof message === 'string' && message.includes('too complex'))) {
                    throw trackError('shared_track_too_complex');
                }
                if (errors.some(message => typeof message === 'string' && message.includes('10 MiB'))) {
                    throw trackError('shared_track_too_large');
                }
            }
            throw new Error(`Track upload failed (${response.status}).`);
        }
        const record = { ...this.validateRecord(await response.json(), { hash }), track: operation.track };
        return record;
    }

    request(url, options) {
        return this.fetch(url, {
            ...options,
            signal: AbortSignal.any([options.signal, AbortSignal.timeout(this.timeoutMs)]),
        });
    }

    validateRecord(record, reference, withTrack = false) {
        if (!Number.isSafeInteger(record.id) || record.id <= 0 || !hashPattern.test(record.hash)
            || (reference.hash && record.hash !== reference.hash) || (reference.token && record.token !== reference.token)
            || !normalizeTrackReference(record.token)) {
            throw new Error('The track response is invalid.');
        }
        if (withTrack) {
            if (typeof record.track === 'string') record.track = JSON.parse(record.track);
            serializeSharedTrack(record.track);
        }
        return record;
    }
}
