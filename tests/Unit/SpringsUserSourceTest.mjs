import assert from 'node:assert/strict';
import test from 'node:test';
import { get as getProjection } from 'ol/proj.js';
import SpringsUserSource from '../../resources/js/sources/user.js';

function setup(t) {
    const previousRequest = globalThis.XMLHttpRequest;
    const requests = [];
    const loaded = [];
    globalThis.XMLHttpRequest = class {
        open(method, url) {
            this.url = url;
        }

        send() {
            requests.push(this);
        }

        abort() {
            this.aborted = true;
            this.onabort();
        }

        complete(id, status = 200) {
            this.status = status;
            this.responseText = JSON.stringify({
                type: 'FeatureCollection',
                features: [{ type: 'Feature', id, properties: { id }, geometry: { type: 'Point', coordinates: [37, 55] } }],
            });
            this.onload();
        }
    };
    t.after(() => { globalThis.XMLHttpRequest = previousRequest; });
    const source = new SpringsUserSource(() => loaded.push(source.getFeatures().map(feature => feature.getId())));
    const load = () => source.loadFeatures([-1, -1, 1, 1], 1, getProjection('EPSG:3857'));
    const select = userId => { source.setUser(userId); load(); };
    return { source, requests, loaded, load, select };
}

test('a late response from the previous user cannot contaminate the selected user source', (t) => {
    const { source, requests, loaded, select } = setup(t);
    select(1);
    select(2);

    assert.equal(requests[0].aborted, true);
    requests[1].complete('second-user-source');
    requests[0].complete('first-user-source');

    assert.equal(source.getUser(), 2);
    assert.deepEqual(source.getFeatures().map(feature => feature.getId()), ['second-user-source']);
    assert.deepEqual(loaded, [['second-user-source']]);
});

test('reselecting the same user and returning to cached user data do not issue extra requests', (t) => {
    const { source, requests, loaded, select } = setup(t);
    select(1);
    requests[0].complete('first-user-source');
    select('1');
    assert.equal(requests.length, 1);

    select(2);
    requests[1].complete('second-user-source');
    select(1);

    assert.equal(requests.length, 2);
    assert.deepEqual(source.getFeatures().map(feature => feature.getId()), ['first-user-source']);
    assert.deepEqual(loaded, [['first-user-source'], ['second-user-source'], ['first-user-source']]);
});

test('failed user responses can be retried without caching the error', (t) => {
    const { source, requests, load, select } = setup(t);
    select(1);
    requests[0].complete('unavailable', 500);
    load();
    requests[1].complete('first-user-source');

    assert.equal(requests.length, 2);
    assert.deepEqual(source.getFeatures().map(feature => feature.getId()), ['first-user-source']);
});

test('disposing the map can cancel its request without applying a late response', (t) => {
    const { source, requests, loaded, select } = setup(t);
    select(1);
    source.cancelRequests();
    requests[0].complete('first-user-source');

    assert.equal(requests[0].aborted, true);
    assert.deepEqual(source.getFeatures(), []);
    assert.deepEqual(loaded, []);
});

test('a mutation invalidates the current and previously cached user data without changing selection', t => {
    const { source, requests, select, load } = setup(t);
    select(1);
    requests[0].complete('old-first-user-source');
    select(2);
    requests[1].complete('old-second-user-source');

    source.invalidateCache();
    assert.equal(source.getUser(), 2);
    assert.deepEqual(source.getFeatures(), []);
    load();
    requests[2].complete('updated-second-user-source');
    assert.deepEqual(source.getFeatures().map(feature => feature.getId()), ['updated-second-user-source']);

    select(1);
    assert.equal(requests.length, 4);
    requests[3].complete('updated-first-user-source');
    assert.deepEqual(source.getFeatures().map(feature => feature.getId()), ['updated-first-user-source']);
});

test('a user response started before a mutation cannot restore stale source data', t => {
    const { source, requests, loaded, select, load } = setup(t);
    select(1);
    source.invalidateCache();
    load();
    assert.equal(requests[0].aborted, true);
    requests[1].complete('updated-source');
    requests[0].complete('stale-source');

    assert.equal(source.getUser(), 1);
    assert.deepEqual(source.getFeatures().map(feature => feature.getId()), ['updated-source']);
    assert.deepEqual(loaded, [['updated-source']]);
});
