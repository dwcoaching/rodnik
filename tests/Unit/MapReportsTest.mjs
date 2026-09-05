import assert from 'node:assert/strict';
import test from 'node:test';
import mapReports from '../../resources/js/mapReports.js';

const area = (west) => ({ west, south: 0, east: west + 10, north: 10 });

function setup(t, { userId = null, bounds = null, viewport = area(0) } = {}) {
    const originalWindow = globalThis.window;
    const requests = [];
    const scrolls = [];
    const nextTicks = [];
    let active = 0;
    let maxActive = 0;

    t.after(() => { globalThis.window = originalWindow; });
    globalThis.window = {
        rodnikMap: { getViewportBounds: () => viewport },
        scrollTo: (options) => scrolls.push(options),
    };

    const wire = {
        userId,
        bounds,
        updateBounds: (bounds) => request('bounds', bounds),
        showMore: () => request('more'),
    };

    async function request(kind, bounds) {
        const deferred = Promise.withResolvers();
        requests.push({ kind, bounds, ...deferred });
        maxActive = Math.max(maxActive, ++active);
        try {
            await deferred.promise;
            if (kind === 'bounds') wire.bounds = bounds;
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

    return {
        component, wire, requests, scrolls, nextTicks,
        move: (bounds) => { viewport = bounds; },
        maxActive: () => maxActive,
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
    assert.deepEqual(requests.map((request) => request.kind), ['more', 'bounds']);
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
