import assert from 'node:assert/strict';
import test from 'node:test';
import mapReports from '../../resources/js/mapReports.js';

const area = (west) => ({ west, south: 0, east: west + 10, north: 10 });
const defaultFilters = {
    spring: true, water_well: true, water_tap: true, drinking_water: true,
    fountain: true, other: true, confirmed: false, along: false,
};
const tick = () => new Promise(setImmediate);

function setup(t, { userId = null, bounds = null, viewport = area(0), filters = {}, trackPolygonHash = null } = {}) {
    const originalWindow = globalThis.window;
    const requests = [];
    const scrolls = [];
    const nextTicks = [];
    let active = 0;
    let maxActive = 0;

    t.after(() => { globalThis.window = originalWindow; });
    globalThis.window = {
        rodnikMap: {
            getViewportBounds: () => viewport,
            filters: { ...defaultFilters, ...filters },
            buffer: {
                revision: 0,
                buffer: null,
                trackPolygon: { clear: () => { polygonClears++; } },
            },
        },
        scrollTo: (options) => scrolls.push(options),
    };

    const wire = {
        userId,
        bounds,
        filters: { ...defaultFilters, ...filters },
        trackPolygonHash,
        updateMap: (bounds, filters, trackPolygonHash) => request('map', bounds, filters, trackPolygonHash),
        showMore: () => request('more'),
    };

    async function request(kind, bounds, filters, trackPolygonHash) {
        const deferred = Promise.withResolvers();
        const recorded = { kind, bounds, filters, trackPolygonHash, accept: true, ...deferred };
        requests.push(recorded);
        maxActive = Math.max(maxActive, ++active);
        try {
            await deferred.promise;
            if (kind === 'map' && recorded.accept) Object.assign(wire, { bounds, filters, trackPolygonHash });
        } finally {
            active--;
        }
    }

    const component = Object.assign(mapReports(), {
        $wire: wire,
        $el: { isConnected: true },
        $refs: { reportsList: { getBoundingClientRect: () => ({ top: 120 }) } },
        $nextTick: (callback) => nextTicks.push(callback),
    });

    let polygonClears = 0;
    let polygonSaves = 0;
    return {
        component, wire, requests, scrolls, nextTicks,
        move: (bounds) => { viewport = bounds; },
        maxActive: () => maxActive,
        filter: (filters) => Object.assign(window.rodnikMap.filters, filters),
        track: (promise) => {
            const buffer = window.rodnikMap.buffer;
            buffer.revision++;
            buffer.buffer = promise ? { geometry: { type: 'Polygon' } } : null;
            buffer.saveTrackPolygon = () => { polygonSaves++; return promise; };
        },
        polygonClears: () => polygonClears,
        polygonSaves: () => polygonSaves,
    };
}

test('initialization waits for the map and loads the viewport', async (t) => {
    const { component, requests, nextTicks, scrolls } = setup(t);
    component.init();
    assert.equal(requests.length, 0);
    const pending = nextTicks.shift()();
    assert.deepEqual(requests[0].bounds, area(0));
    assert.equal(component.busy, true);
    assert.equal(component.loaderTop, 120);
    requests[0].resolve();
    await pending;
    assert.equal(component.busy, false);
    assert.deepEqual(scrolls, [{ top: 0, behavior: 'instant' }]);
});

test('user feeds and maps without a viewport do not request reports', async (t) => {
    const { component, wire, move, requests } = setup(t, { userId: 12 });
    await component.refresh();
    await component.refresh(true);
    wire.userId = null;
    move(null);
    await component.refresh();
    assert.equal(requests.length, 0);
    assert.equal(component.busy, false);
});

test('viewport changes collapse to the latest bounds with one request at a time', async (t) => {
    const { component, requests, move, maxActive } = setup(t);
    const pending = component.refresh();
    move(area(20));
    await component.refresh();
    move(area(40));
    await component.refresh();
    assert.equal(requests.length, 1);
    requests[0].resolve();
    await new Promise(setImmediate);
    assert.deepEqual(requests.map((request) => request.bounds), [area(0), area(40)]);
    requests[1].resolve();
    await pending;
    assert.equal(maxActive(), 1);
    assert.equal(component.busy, false);
});

test('unchanged bounds are deduplicated, including changes back during a request', async (t) => {
    const { component, requests, move } = setup(t);
    const pending = component.refresh();
    move(area(20));
    await component.refresh();
    move(area(0));
    await component.refresh();
    requests[0].resolve();
    await pending;
    await component.refresh();
    assert.equal(requests.length, 1);
});

test('pagination finishes before loading the latest viewport', async (t) => {
    const { component, requests, move, maxActive, scrolls } = setup(t, { bounds: area(0) });
    const pending = component.refresh(true);
    move(area(20));
    await component.refresh();
    assert.deepEqual(requests.map((request) => request.kind), ['more']);
    requests[0].resolve();
    await new Promise(setImmediate);
    assert.deepEqual(requests.map((request) => request.kind), ['more', 'map']);
    assert.deepEqual(requests[1].bounds, area(20));
    requests[1].resolve();
    await pending;
    assert.equal(maxActive(), 1);
    assert.equal(scrolls.length, 1);
});

test('a failed request clears the busy state and can be retried', async (t) => {
    const { component, requests, scrolls } = setup(t);
    const failed = component.refresh();
    requests[0].reject(new Error('Network unavailable'));
    await failed;
    assert.equal(component.failed, true);
    assert.equal(component.busy, false);
    assert.equal(scrolls.length, 0);
    const retried = component.refresh();
    assert.equal(component.failed, false);
    assert.deepEqual(requests[1].bounds, area(0));
    requests[1].resolve();
    await retried;
    assert.equal(component.busy, false);
    assert.equal(scrolls.length, 1);
});

test('retrying failed pagination loads more reports again', async (t) => {
    const { component, requests, scrolls } = setup(t, { bounds: area(0) });
    const failed = component.refresh(true);
    requests[0].reject(new Error('Network unavailable'));
    await failed;
    assert.equal(component.failed, true);
    assert.equal(component.retryMore, true);
    const retried = component.refresh(component.retryMore);
    assert.deepEqual(requests.map((request) => request.kind), ['more', 'more']);
    requests[1].resolve();
    await retried;
    assert.equal(component.failed, false);
    assert.equal(component.retryMore, false);
    assert.equal(component.busy, false);
    assert.equal(scrolls.length, 0);
});

test('detached components discard queued work and cannot request or scroll again', async (t) => {
    const { component, requests, move, scrolls } = setup(t);
    const pending = component.refresh();
    move(area(20));
    await component.refresh();
    component.$el.isConnected = false;
    requests[0].resolve();
    await pending;
    await component.refresh();
    await component.refresh(true);
    assert.equal(requests.length, 1);
    assert.equal(scrolls.length, 0);
    assert.equal(component.busy, false);
});

test('source types and confirmation refresh at unchanged bounds and ignore the all checkbox', async (t) => {
    const { component, filter, requests } = setup(t, { bounds: area(0) });
    filter({ spring: false, confirmed: true, all: false });
    const pending = component.refresh();

    assert.deepEqual(requests[0].filters, { ...defaultFilters, spring: false, confirmed: true });
    assert.equal(requests[0].trackPolygonHash, null);
    requests[0].resolve();
    await pending;
    filter({ all: true });
    await component.refresh();
    assert.equal(requests.length, 1);
});

test('all map conditions collapse to the latest snapshot during a report request', async (t) => {
    const { component, filter, move, requests, maxActive } = setup(t);
    const pending = component.refresh();
    filter({ spring: false });
    await component.refresh();
    move(area(40));
    filter({ water_well: false, confirmed: true });
    await component.refresh();
    requests[0].resolve();
    await tick();

    assert.equal(requests.length, 2);
    assert.deepEqual(requests[1].bounds, area(40));
    assert.deepEqual(requests[1].filters, {
        ...defaultFilters, spring: false, water_well: false, confirmed: true,
    });
    requests[1].resolve();
    await pending;
    assert.equal(maxActive(), 1);
});

test('along-track loading waits for polygon persistence and readiness storms do not duplicate reports', async (t) => {
    const { component, requests, track, maxActive } = setup(t, { filters: { along: true } });
    const polygon = Promise.withResolvers();
    track(polygon.promise);
    const pending = component.refresh();
    await component.refresh();
    assert.equal(component.busy, true);
    assert.equal(requests.length, 0);

    polygon.resolve({ hash: 'a'.repeat(64) });
    await tick();
    await component.refresh();
    assert.equal(requests.length, 1);
    assert.equal(requests[0].trackPolygonHash, 'a'.repeat(64));
    requests[0].resolve();
    await pending;
    await component.refresh();
    assert.equal(requests.length, 1);
    assert.equal(maxActive(), 1);
});

test('changes during polygon persistence submit the latest bounds and filters without paginating', async (t) => {
    const hash = 'a'.repeat(64);
    const { component, requests, track, filter, move } = setup(t, {
        bounds: area(0), filters: { along: true }, trackPolygonHash: hash,
    });
    const polygon = Promise.withResolvers();
    track(polygon.promise);
    const pending = component.refresh(true);
    move(area(30));
    filter({ confirmed: true });
    await component.refresh();
    polygon.resolve({ hash });
    await tick();

    assert.equal(requests.length, 1);
    assert.equal(requests[0].kind, 'map');
    assert.deepEqual(requests[0].bounds, area(30));
    assert.equal(requests[0].filters.confirmed, true);
    assert.equal(requests[0].trackPolygonHash, hash);
    requests[0].resolve();
    await pending;
});

test('changing filters away and back during polygon persistence discards show-more intent', async (t) => {
    const hash = 'a'.repeat(64);
    const { component, requests, track, filter } = setup(t, {
        bounds: area(0), filters: { along: true }, trackPolygonHash: hash,
    });
    const polygon = Promise.withResolvers();
    track(polygon.promise);
    const pending = component.refresh(true);
    filter({ confirmed: true });
    await component.refresh();
    filter({ confirmed: false });
    await component.refresh();
    polygon.resolve({ hash });
    await pending;

    assert.equal(requests.length, 0);
    assert.equal(component.failed, false);
    assert.equal(component.retryMore, false);
});

test('replacing a pending track uses the new hash without waiting for the old upload', async (t) => {
    const { component, requests, track } = setup(t, { filters: { along: true } });
    const previous = Promise.withResolvers();
    track(previous.promise);
    const pending = component.refresh();
    track(Promise.resolve({ hash: 'b'.repeat(64) }));
    await component.refresh();
    await tick();

    assert.equal(requests.length, 1);
    assert.equal(requests[0].trackPolygonHash, 'b'.repeat(64));
    requests[0].resolve();
    await pending;
    previous.resolve({ hash: 'a'.repeat(64) });
    await tick();
    assert.equal(requests.length, 1);
});

test('clearing the track during upload immediately applies along with no hash', async (t) => {
    const { component, requests, track } = setup(t, { filters: { along: true } });
    const polygon = Promise.withResolvers();
    track(polygon.promise);
    const pending = component.refresh();
    track(null);
    await component.refresh();
    await tick();

    assert.equal(requests.length, 1);
    assert.equal(requests[0].filters.along, true);
    assert.equal(requests[0].trackPolygonHash, null);
    requests[0].resolve();
    await pending;
    polygon.resolve({ hash: 'a'.repeat(64) });
    await tick();
    await component.refresh();
    assert.equal(requests.length, 1);
});

test('turning along off does not wait for or transmit a pending polygon', async (t) => {
    const { component, requests, track, filter } = setup(t, { filters: { along: true } });
    const polygon = Promise.withResolvers();
    track(polygon.promise);
    const pending = component.refresh();
    filter({ along: false });
    await component.refresh();
    await tick();

    assert.equal(requests.length, 1);
    assert.equal(requests[0].filters.along, false);
    assert.equal(requests[0].trackPolygonHash, null);
    requests[0].resolve();
    await pending;
    polygon.resolve(null);
    await tick();
    assert.equal(component.failed, false);
});

test('failed polygon persistence cannot load unfiltered reports and can be retried', async (t) => {
    const { component, requests, track } = setup(t, { filters: { along: true } });
    track(Promise.resolve(null));
    await component.refresh();
    assert.equal(requests.length, 0);
    assert.equal(component.failed, true);
    assert.equal(component.busy, false);

    window.rodnikMap.buffer.saveTrackPolygon = () => Promise.resolve({ hash: 'a'.repeat(64) });
    const retried = component.refresh(component.retryMore);
    await tick();
    assert.equal(requests.length, 1);
    assert.equal(requests[0].trackPolygonHash, 'a'.repeat(64));
    requests[0].resolve();
    await retried;
    assert.equal(component.failed, false);
});

test('track creation and clearing do not refresh reports when along is disabled', async (t) => {
    const { component, requests, track, polygonSaves } = setup(t, { bounds: area(0) });
    track(Promise.resolve({ hash: 'a'.repeat(64) }));
    await component.refresh();
    track(null);
    await component.refresh();
    assert.equal(requests.length, 0);
    assert.equal(polygonSaves(), 0);
});

test('a replaced saved track resets the report query at identical bounds and filters', async (t) => {
    const { component, requests, track } = setup(t, {
        bounds: area(0), filters: { along: true }, trackPolygonHash: 'a'.repeat(64),
    });
    track(Promise.resolve({ hash: 'b'.repeat(64) }));
    const pending = component.refresh(true);
    await tick();
    assert.equal(requests[0].kind, 'map');
    assert.equal(requests[0].trackPolygonHash, 'b'.repeat(64));
    requests[0].resolve();
    await pending;
    assert.equal(requests.length, 1);
});

test('server validation that resolves without applying filters stays failed and rechecks the polygon on retry', async (t) => {
    const { component, requests, track, polygonClears, scrolls } = setup(t, { filters: { along: true } });
    track(Promise.resolve({ hash: 'a'.repeat(64) }));
    const failed = component.refresh();
    await tick();
    requests[0].accept = false;
    requests[0].resolve();
    await failed;

    assert.equal(component.failed, true);
    assert.equal(polygonClears(), 1);
    assert.equal(scrolls.length, 0);
    const retried = component.refresh();
    await tick();
    requests[1].resolve();
    await retried;
    assert.equal(component.failed, false);
});

test('failure of an obsolete report request still processes the latest filters', async (t) => {
    const { component, filter, requests } = setup(t);
    const pending = component.refresh();
    filter({ confirmed: true });
    await component.refresh();
    requests[0].reject(new Error('Unavailable'));
    await tick();
    assert.equal(requests.length, 2);
    assert.equal(requests[1].filters.confirmed, true);
    requests[1].resolve();
    await pending;
    assert.equal(component.failed, false);
});
