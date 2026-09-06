import assert from 'node:assert/strict';
import { createHash, webcrypto } from 'node:crypto';
import { registerHooks } from 'node:module';
import test from 'node:test';
import { featureCollection, lineString } from '@turf/turf';
import TrackPolygons, { serializeTrackPolygon } from '../../resources/js/trackPolygons.js';
import mapReports from '../../resources/js/mapReports.js';

const browserImports = registerHooks({
    resolve(specifier, context, nextResolve) {
        if (specifier.startsWith('@/')) {
            const path = specifier.slice(2).replace(/(?:\.js)?$/, '.js');
            return nextResolve(new URL(`../../resources/js/${path}`, import.meta.url).href, context);
        }

        if (specifier.startsWith('ol/') && !specifier.endsWith('.js')) {
            return nextResolve(`${specifier}.js`, context);
        }

        return nextResolve(specifier, context);
    },
});

const { default: TrackLayer } = await import('../../resources/js/layers/tracks/track.js');
const { default: TrackBuffer } = await import('../../resources/js/buffer.js');
const { default: visible } = await import('../../resources/js/filters/visible.js');
browserImports.deregister();

const polygon = (offset = 0) => ({
    type: 'Polygon',
    coordinates: [[[offset, 0], [offset + 1, 0], [offset + 1, 1], [offset, 0]]],
});
const hashOf = (geometry) => createHash('sha256').update(serializeTrackPolygon(geometry)).digest('hex');
const response = (hash, id = 12, status = 200) => Response.json({ id, hash }, { status });

function setup(fetch) {
    const requests = [];
    const persistence = new TrackPolygons({
        endpoint: '/track-polygons',
        csrfToken: () => 'csrf-token',
        crypto: webcrypto,
        fetch: async (url, options) => {
            requests.push({ url, ...options });
            return fetch(url, options);
        },
    });

    return { persistence, requests };
}

function globals(t, values) {
    const previous = Object.fromEntries(Object.keys(values).map((key) => [key, globalThis[key]]));
    Object.assign(globalThis, values);
    t.after(() => Object.assign(globalThis, previous));
}

test('hash lookup uses deterministic geometry JSON and omits all feature properties', async () => {
    const { persistence, requests } = setup((url) => response(url.split('/').at(-1)));
    const geometry = polygon();
    const feature = { type: 'Feature', geometry, properties: { name: 'Private GPX name' }, bbox: [0, 0, 1, 1] };

    assert.equal(serializeTrackPolygon(feature), '{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,0]]]}');
    await persistence.save(feature);
    await persistence.save({ coordinates: geometry.coordinates, type: geometry.type });

    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, `/track-polygons/${hashOf(geometry)}`);
    assert.equal(requests[0].body, undefined);
    assert.equal(requests[0].credentials, 'same-origin');
    assert.equal(requests[0].cache, 'no-store');
    assert.equal(persistence.id, 12);
    assert.equal(persistence.hash, hashOf(geometry));
    assert.equal(persistence.status, 'saved');
});

test('a missing hash uploads the exact hashed JSON with session CSRF protection', async () => {
    const { persistence, requests } = setup((url, options) => options.method === 'POST'
        ? response(JSON.parse(options.body).hash, 25, 201)
        : new Response(null, { status: 404 }));

    const record = await persistence.save(polygon());

    assert.deepEqual(requests.map((request) => request.method ?? 'GET'), ['GET', 'POST']);
    assert.equal(requests[1].url, '/track-polygons');
    assert.deepEqual(JSON.parse(requests[1].body), { hash: hashOf(polygon()), polygon: serializeTrackPolygon(polygon()) });
    assert.equal(requests[1].headers['X-CSRF-TOKEN'], 'csrf-token');
    assert.equal(requests[1].headers['Content-Type'], 'application/json');
    assert.equal(requests[1].credentials, 'same-origin');
    assert.deepEqual(record, { id: 25, hash: hashOf(polygon()) });
});

test('an existing record returned by POST handles simultaneous uploads', async () => {
    const { persistence } = setup((url, options) => options.method === 'POST'
        ? response(JSON.parse(options.body).hash)
        : new Response(null, { status: 404 }));

    assert.deepEqual(await persistence.save(polygon()), { id: 12, hash: hashOf(polygon()) });
});

test('duplicate saves share an in-flight request even after clearing and reloading the same polygon', async () => {
    const started = Promise.withResolvers();
    const pending = Promise.withResolvers();
    const { persistence, requests } = setup(() => {
        started.resolve();
        return pending.promise;
    });

    const first = persistence.save(polygon());
    assert.equal(persistence.save(polygon()), first);
    await started.promise;
    persistence.clear();
    const reloaded = persistence.save(polygon());
    pending.resolve(response(hashOf(polygon())));
    await Promise.all([first, reloaded]);

    assert.equal(requests.length, 1);
    assert.equal(persistence.id, 12);
    assert.equal(persistence.status, 'saved');
});

test('a failed lookup remains retryable and never triggers an upload', async () => {
    let fail = true;
    const { persistence, requests } = setup((url) => {
        if (fail) throw new Error('Offline');
        return response(url.split('/').at(-1));
    });

    assert.equal(await persistence.save(polygon()), null);
    assert.equal(persistence.status, 'failed');
    assert.equal(persistence.id, null);
    fail = false;
    await persistence.save(polygon());

    assert.equal(persistence.status, 'saved');
    assert.equal(requests.length, 2);
    assert.equal(requests.every((request) => !request.method), true);
});

test('failed POST can be retried with a fresh lookup and upload', async () => {
    let fail = true;
    const { persistence, requests } = setup((url, options) => {
        if (options.method !== 'POST') return new Response(null, { status: 404 });
        if (fail) throw new Error('Upload interrupted');
        return response(JSON.parse(options.body).hash);
    });

    await persistence.save(polygon());
    assert.equal(persistence.status, 'failed');
    fail = false;
    await persistence.save(polygon());

    assert.equal(persistence.status, 'saved');
    assert.deepEqual(requests.map((request) => request.method ?? 'GET'), ['GET', 'POST', 'GET', 'POST']);
});

test('lookup HTTP errors and invalid responses do not trigger a POST or mark the polygon saved', async () => {
    for (const result of [new Response(null, { status: 429 }), response('wrong-hash'), response(hashOf(polygon()), null)]) {
        const { persistence, requests } = setup(() => result);

        assert.equal(await persistence.save(polygon()), null);
        assert.equal(persistence.status, 'failed');
        assert.equal(persistence.id, null);
        assert.equal(requests.length, 1);
    }
});

test('an older successful response cannot replace the current polygon', async () => {
    const started = Promise.withResolvers();
    const old = Promise.withResolvers();
    const { persistence } = setup((url) => {
        if (url.endsWith(hashOf(polygon()))) {
            started.resolve();
            return old.promise;
        }

        return response(hashOf(polygon(5)), 26);
    });

    const previous = persistence.save(polygon());
    await started.promise;
    await persistence.save(polygon(5));
    old.resolve(response(hashOf(polygon())));
    await previous;

    assert.equal(persistence.id, 26);
    assert.equal(persistence.hash, hashOf(polygon(5)));
    assert.equal(persistence.status, 'saved');
});

test('clearing during a pending request prevents both success and failure from restoring state', async () => {
    for (const fail of [false, true]) {
        const started = Promise.withResolvers();
        const pending = Promise.withResolvers();
        const { persistence } = setup(() => {
            started.resolve();
            return pending.promise;
        });
        const saved = persistence.save(polygon());
        await started.promise;
        persistence.clear();
        if (fail) pending.reject(new Error('Offline'));
        else pending.resolve(response(hashOf(polygon())));
        await saved;

        assert.equal(persistence.id, null);
        assert.equal(persistence.hash, null);
        assert.equal(persistence.status, 'idle');
        assert.equal(persistence.error, null);
    }
});

test('MultiPolygon is supported and invalid or oversized geometry makes no request', async () => {
    const multiPolygon = { type: 'MultiPolygon', coordinates: [polygon().coordinates] };
    const { persistence, requests } = setup((url) => response(url.split('/').at(-1)));
    await persistence.save(multiPolygon);
    assert.equal(persistence.hash, hashOf(multiPolygon));

    const oversized = { type: 'Polygon', coordinates: [Array(100000).fill([10.123456789, 20.123456789])] };
    for (const invalid of [null, { type: 'FeatureCollection', features: [] }, { type: 'Point', coordinates: [0, 0] }, oversized]) {
        assert.equal(await persistence.save(invalid), null);
        assert.equal(persistence.status, 'failed');
    }

    assert.equal(requests.length, 1);
});

test('buffer construction starts persistence and clearing removes the saved reference', async (t) => {
    const { persistence, requests } = setup((url) => response(url.split('/').at(-1)));
    const buffer = new TrackBuffer();
    buffer.trackPolygon = persistence;
    buffer.makeSimplifiedTrack = () => {};
    buffer.makeBuffer = () => { buffer.buffer = { type: 'Feature', geometry: polygon() }; };
    const events = [];
    const states = [];
    let restyles = 0;
    globals(t, { window: {
        rodnikMap: {
            trackLayer: { getSource: () => ({ getFeatures: () => [] }) },
            updateFilterStyles: () => restyles++,
        },
        dispatchEvent: (event) => {
            events.push(event.type);
            if (event.detail) states.push(event.detail);
        },
    } });

    const revision = buffer.revision;
    buffer.setTrack([]);
    assert.equal(buffer.revision, revision + 1);
    assert.deepEqual(events, ['map-track-polygon-state-changed', 'map-track-changed']);
    await buffer.trackPolygon.promise;
    assert.equal(requests.length, 1);
    assert.equal(buffer.trackPolygon.id, 12);
    assert.equal(events.length, 3);
    await buffer.saveTrackPolygon();
    assert.equal(events.length, 3);
    buffer.clear();
    assert.equal(buffer.trackPolygon.id, null);
    assert.equal(buffer.buffer, null);
    assert.equal(buffer.revision, revision + 2);
    await buffer.saveTrackPolygon();
    assert.equal(requests.length, 1);
    assert.deepEqual(events, [
        'map-track-polygon-state-changed', 'map-track-changed',
        'map-track-polygon-state-changed', 'map-track-changed',
    ]);
    assert.deepEqual(states, [
        { revision: revision + 1, status: 'saving', hash: null },
        { revision: revision + 1, status: 'saved', hash: hashOf(polygon()) },
    ]);
    assert.equal(restyles, 2);
});

test('polygon lookup and upload run while an earlier Livewire report request remains unresolved', async (t) => {
    const lookupStarted = Promise.withResolvers();
    const lookup = Promise.withResolvers();
    const uploadStarted = Promise.withResolvers();
    const upload = Promise.withResolvers();
    const { persistence, requests } = setup((url, options) => {
        if (options.method === 'POST') {
            uploadStarted.resolve();
            return upload.promise;
        }

        lookupStarted.resolve();
        return lookup.promise;
    });
    const buffer = new TrackBuffer();
    buffer.trackPolygon = persistence;
    buffer.makeSimplifiedTrack = () => {};
    buffer.makeBuffer = () => { buffer.buffer = { type: 'Feature', geometry: polygon() }; };
    const wireRequests = [];
    let restyles = 0;
    let component;
    const wire = {
        userId: null,
        bounds: null,
        async updateMap(bounds, filters, trackPolygonHash) {
            const deferred = Promise.withResolvers();
            wireRequests.push({ bounds, filters, trackPolygonHash, ...deferred });
            await deferred.promise;
            Object.assign(wire, { bounds, filters, trackPolygonHash });
        },
    };
    globals(t, { window: {
        rodnikMap: {
            filters: { along: false },
            getViewportBounds: () => ({ west: 0, south: 0, east: 10, north: 10 }),
            buffer,
            trackLayer: { getSource: () => ({ getFeatures: () => [] }) },
            updateFilterStyles: () => restyles++,
        },
        dispatchEvent: () => component.refresh(),
        scrollTo: () => {},
    } });
    component = Object.assign(mapReports(), {
        $wire: wire,
        $el: { isConnected: true },
        $refs: { reportsList: { getBoundingClientRect: () => ({ top: 120 }) } },
    });

    const reports = component.refresh();
    assert.equal(wireRequests.length, 1);
    assert.equal(component.busy, true);
    window.rodnikMap.filters.along = true;
    buffer.setTrack([]);
    await lookupStarted.promise;
    assert.equal(wireRequests.length, 1);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, `/track-polygons/${hashOf(polygon())}`);
    assert.equal(component.waitingForPolygon, true);

    lookup.resolve(new Response(null, { status: 404 }));
    await uploadStarted.promise;
    assert.equal(requests[1].method, 'POST');
    assert.equal(wireRequests.length, 1);
    upload.resolve(response(hashOf(polygon()), 12, 201));
    await buffer.trackPolygon.promise;
    assert.equal(restyles, 1);
    assert.equal(wireRequests.length, 1);

    wireRequests[0].resolve();
    await new Promise(setImmediate);
    assert.equal(wireRequests.length, 2);
    assert.equal(wireRequests[1].trackPolygonHash, hashOf(polygon()));
    wireRequests[1].resolve();
    await reports;
    assert.equal(component.busy, false);
    assert.equal(component.waitingForPolygon, false);
});

test('a cleared buffer does not emit a readiness event when its previous upload completes', async (t) => {
    const started = Promise.withResolvers();
    const upload = Promise.withResolvers();
    const { persistence, requests } = setup(() => {
        started.resolve();
        return upload.promise;
    });
    const events = [];
    globals(t, { window: {
        rodnikMap: {},
        dispatchEvent: (event) => events.push(event.type),
    } });
    const buffer = new TrackBuffer();
    buffer.trackPolygon = persistence;
    buffer.buffer = { type: 'Feature', geometry: polygon() };

    const pending = buffer.saveTrackPolygon();
    assert.equal(buffer.saveTrackPolygon(), pending);
    await started.promise;
    buffer.clear();
    upload.resolve(response(hashOf(polygon())));
    await pending;

    assert.equal(requests.length, 1);
    assert.deepEqual(events, ['map-track-polygon-state-changed', 'map-track-changed']);
    assert.equal(buffer.trackPolygon.hash, null);
});

test('failed buffer persistence emits its status without automatically retrying or restyling', async (t) => {
    const { persistence, requests } = setup(() => new Response(null, { status: 503 }));
    const events = [];
    globals(t, { window: {
        rodnikMap: {},
        dispatchEvent: (event) => events.push([event.type, event.detail.status]),
    } });
    const buffer = new TrackBuffer();
    buffer.trackPolygon = persistence;
    buffer.buffer = { type: 'Feature', geometry: polygon() };
    await buffer.saveTrackPolygon();

    assert.equal(requests.length, 1);
    assert.equal(buffer.trackPolygon.status, 'failed');
    assert.deepEqual(events, [
        ['map-track-polygon-state-changed', 'saving'],
        ['map-track-polygon-state-changed', 'failed'],
    ]);
});

test('invalidating persistence without changing the track suppresses an obsolete completion event', async (t) => {
    const started = Promise.withResolvers();
    const upload = Promise.withResolvers();
    const { persistence } = setup(() => {
        started.resolve();
        return upload.promise;
    });
    const events = [];
    globals(t, { window: {
        rodnikMap: {},
        dispatchEvent: (event) => events.push(event.detail.status),
    } });
    const buffer = new TrackBuffer();
    buffer.trackPolygon = persistence;
    buffer.buffer = { type: 'Feature', geometry: polygon() };
    const pending = buffer.saveTrackPolygon();
    await started.promise;
    persistence.clear();
    upload.resolve(response(hashOf(polygon())));
    await pending;

    assert.deepEqual(events, ['saving']);
    assert.equal(persistence.hash, null);
});

test('invalid geometry emits one failed state without starting a request', async (t) => {
    const { persistence, requests } = setup(() => { throw new Error('Unexpected request'); });
    const events = [];
    globals(t, { window: {
        rodnikMap: {},
        dispatchEvent: (event) => events.push(event.detail.status),
    } });
    const buffer = new TrackBuffer();
    buffer.trackPolygon = persistence;
    buffer.buffer = { type: 'Feature', geometry: { type: 'Point', coordinates: [0, 0] } };
    await buffer.saveTrackPolygon();

    assert.deepEqual(events, ['failed']);
    assert.equal(requests.length, 0);
});

test('track buffers cache bounds for all polygon parts and replace them when rebuilt or cleared', () => {
    const buffer = new TrackBuffer();
    buffer.trackSimplified = featureCollection([
        lineString([[30, 50], [30, 50.02]]),
        lineString([[32, 51], [32, 51.02]]),
    ]);
    buffer.makeBuffer();

    const oldPolygon = buffer.buffer;
    assert.equal(oldPolygon.geometry.type, 'MultiPolygon');
    assert.equal(oldPolygon.bbox.length, 4);
    assert.ok(oldPolygon.bbox[0] < 30);
    assert.ok(oldPolygon.bbox[1] < 50);
    assert.ok(oldPolygon.bbox[2] > 32);
    assert.ok(oldPolygon.bbox[3] > 51.02);
    assert.equal(serializeTrackPolygon(oldPolygon), serializeTrackPolygon(oldPolygon.geometry));

    buffer.trackSimplified = featureCollection([lineString([[40, 60], [40, 60.02]])]);
    buffer.makeBuffer();
    assert.equal(buffer.buffer.geometry.type, 'Polygon');
    assert.notEqual(buffer.buffer, oldPolygon);
    assert.ok(buffer.buffer.bbox[0] > oldPolygon.bbox[2]);

    buffer.clear(false);
    assert.equal(buffer.buffer, null);
});

test('map filtering skips polygon traversal outside the bbox and still checks holes and boundaries inside', (t) => {
    const buffer = new TrackBuffer();
    buffer.trackSimplified = featureCollection([
        lineString([[30, 50], [30.1, 50], [30.1, 50.1], [30, 50.1], [30, 50]]),
    ]);
    buffer.makeBuffer();
    globals(t, { window: { rodnikMap: {
        filters: { spring: true, along: true },
        buffer,
        trackLayer: { getSource: () => ({ getFeatures: () => [{}] }) },
    } } });
    const isVisible = (coordinates) => visible({
        get: (key) => key === 'type' ? 'Spring' : undefined,
        getGeometry: () => ({ clone: () => ({ transform: () => ({ getCoordinates: () => coordinates }) }) }),
    });
    const coordinates = buffer.buffer.geometry.coordinates;
    const bounds = buffer.buffer.bbox;
    buffer.buffer.geometry.coordinates = new Proxy(coordinates, {
        get() { throw new Error('Points outside the bbox must not traverse polygon coordinates.'); },
    });

    for (const outside of [
        [bounds[0] - 0.01, 50.05], [bounds[2] + 0.01, 50.05],
        [30.05, bounds[1] - 0.01], [30.05, bounds[3] + 0.01],
    ]) {
        assert.equal(isVisible(outside), false);
    }

    buffer.buffer.geometry.coordinates = coordinates;
    assert.equal(isVisible([30, 50.05]), true);
    assert.equal(isVisible([30.05, 50.05]), false);
    assert.equal(isVisible([bounds[0], bounds[1]]), false);
    assert.equal(isVisible(coordinates[0][0]), true);
    assert.equal(isVisible(coordinates[1][0]), true);
});

test('GPX storage quota failure preserves the loaded track and removes the previous cached GPX', (t) => {
    const events = [];
    globals(t, {
        localStorage: {
            setItem: () => { events.push('cache'); throw new DOMException('Full', 'QuotaExceededError'); },
            removeItem: (key) => events.push(['remove', key]),
        },
        window: { rodnikMap: { view: { fit() {}, setZoom() {}, getZoom: () => 10 } } },
    });
    const layer = {
        getSource: () => ({
            setFromGPXString: () => events.push('load-and-start-polygon-upload'),
            getFeatures: () => [{}],
            getExtent: () => [0, 0, 1, 1],
        }),
        isUploaded: { value: false },
        clearFromLocalStorage: TrackLayer.prototype.clearFromLocalStorage,
    };

    TrackLayer.prototype.load.call(layer, 'large GPX');

    assert.equal(layer.isUploaded.value, true);
    assert.deepEqual(events, ['load-and-start-polygon-upload', 'cache', ['remove', 'uploadedGPXTrack']]);
});

test('restoring GPX rebuilds its polygon and removing a track also clears polygon state', (t) => {
    const events = [];
    globals(t, {
        localStorage: { getItem: () => 'saved GPX', removeItem: () => events.push('remove-cache') },
        window: { rodnikMap: { buffer: { clear: () => events.push('clear-polygon') } } },
    });
    const layer = {
        getSource: () => ({
            setFromGPXString: (content) => events.push(['restore', content]),
            clear: () => events.push('clear-track'),
        }),
        clearFromLocalStorage: TrackLayer.prototype.clearFromLocalStorage,
        isUploaded: { value: false },
    };

    TrackLayer.prototype.restoreFromLocalStorage.call(layer);
    assert.equal(layer.isUploaded.value, true);
    TrackLayer.prototype.clear.call(layer);
    assert.equal(layer.isUploaded.value, false);
    assert.deepEqual(events, [['restore', 'saved GPX'], 'clear-track', 'clear-polygon', 'remove-cache']);
});
