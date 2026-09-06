const maxPolygonBytes = 1024 * 1024;

export function serializeTrackPolygon(feature) {
    const geometry = feature?.type === 'Feature' ? feature.geometry : feature;

    if (!['Polygon', 'MultiPolygon'].includes(geometry?.type)) {
        throw new Error('A track polygon is required.');
    }

    const polygon = JSON.stringify({
        type: geometry.type,
        coordinates: geometry.coordinates,
    });

    if (new TextEncoder().encode(polygon).byteLength > maxPolygonBytes) {
        throw new Error('The track polygon exceeds 1 MiB.');
    }

    return polygon;
}

export default class TrackPolygons {
    constructor({
        endpoint = globalThis.document?.querySelector('meta[name="track-polygons-url"]')?.content,
        csrfToken = () => globalThis.document?.querySelector('meta[name="csrf-token"]')?.content,
        fetch = (...args) => globalThis.fetch(...args),
        crypto = globalThis.crypto,
    } = {}) {
        this.endpoint = endpoint;
        this.csrfToken = csrfToken;
        this.fetch = fetch;
        this.crypto = crypto;
        this.inFlight = new Map();
        this.revision = 0;
        this.clear();
    }

    clear() {
        this.revision++;
        this.polygon = null;
        this.promise = null;
        this.id = null;
        this.hash = null;
        this.status = 'idle';
        this.error = null;
    }

    save(feature) {
        let polygon;

        try {
            polygon = serializeTrackPolygon(feature);
        } catch (error) {
            this.clear();
            this.status = 'failed';
            this.error = error;
            return Promise.resolve(null);
        }

        if (polygon === this.polygon && ['saving', 'saved'].includes(this.status)) {
            return this.promise;
        }

        this.clear();
        this.polygon = polygon;
        this.status = 'saving';
        const revision = this.revision;

        let pending = this.inFlight.get(polygon);

        if (!pending) {
            pending = this.findOrStore(polygon).finally(() => this.inFlight.delete(polygon));
            this.inFlight.set(polygon, pending);
        }

        this.promise = pending.then((record) => {
            if (revision === this.revision) {
                this.id = record.id;
                this.hash = record.hash;
                this.status = 'saved';
            }

            return record;
        }).catch((error) => {
            if (revision === this.revision) {
                this.status = 'failed';
                this.error = error;
            }

            return null;
        });

        return this.promise;
    }

    async findOrStore(polygon) {
        if (!this.endpoint) {
            throw new Error('The track polygon endpoint is unavailable.');
        }

        const digest = await this.crypto.subtle.digest('SHA-256', new TextEncoder().encode(polygon));
        const hash = Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
        const headers = { Accept: 'application/json' };
        const endpoint = this.endpoint.replace(/\/$/, '');

        let response = await this.fetch(`${endpoint}/${hash}`, {
            headers,
            credentials: 'same-origin',
            cache: 'no-store',
        });

        if (response.status === 404) {
            response = await this.fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    ...headers,
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken(),
                },
                body: JSON.stringify({ hash, polygon }),
            });
        }

        if (!response.ok) {
            throw new Error(`Track polygon request failed (${response.status}).`);
        }

        const record = await response.json();

        if (!Number.isInteger(record.id) || record.id <= 0 || record.hash !== hash) {
            throw new Error('The track polygon response is invalid.');
        }

        return { id: record.id, hash };
    }
}
