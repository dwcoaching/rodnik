import assert from 'node:assert/strict';
import test from 'node:test';
import { get as getProjection } from 'ol/proj.js';
import SpringsFinalSource from '../../resources/js/sources/final.js';

function setup(t) {
    const previousRequest = globalThis.XMLHttpRequest;
    const previousWindow = globalThis.window;
    const requests = [];
    globalThis.XMLHttpRequest = class {
        open(method, url) {
            this.url = url;
        }

        send() {
            requests.push(this);
        }

        complete() {
            this.status = 200;
            this.responseText = JSON.stringify({ type: 'FeatureCollection', features: [] });
            this.onload();
        }
    };
    t.after(() => {
        globalThis.XMLHttpRequest = previousRequest;
        globalThis.window = previousWindow;
    });
    const load = source => source.loadFeatures([1, 1, 2, 2], 1, getProjection('EPSG:3857'));
    return { requests, load };
}

test('tile completion belongs to its source even when the active map has changed', t => {
    const { requests, load } = setup(t);
    let ownerCalls = 0;
    let replacementCalls = 0;
    const source = new SpringsFinalSource(() => ownerCalls++);
    load(source);
    globalThis.window = { rodnikMap: { featuresLoadEnd: () => replacementCalls++ } };
    requests.forEach(request => request.complete());

    assert.equal(ownerCalls, 1);
    assert.equal(replacementCalls, 0);
});

test('pending tiles can complete after leaving the map without accessing a disposed owner', t => {
    const { requests, load } = setup(t);
    let ownerCalls = 0;
    const source = new SpringsFinalSource(() => ownerCalls++);
    load(source);
    source.dispose();
    globalThis.window = { rodnikMap: null };

    assert.doesNotThrow(() => requests.forEach(request => request.complete()));
    assert.equal(ownerCalls, 0);
});
