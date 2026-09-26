import assert from 'node:assert/strict';
import test from 'node:test';
import { captureLocalTrackHistory, readLocalTrackHistory } from '../../resources/js/localTrackHistory.js';
import { writeDuoHistory } from '../../resources/js/navigationHistory.js';

const track = () => ({ type: 'FeatureCollection', features: [
    { type: 'Feature', properties: { name: 'Private route' }, geometry: { type: 'LineString', coordinates: [[37, 55, 120, 1750000000], [38, 56, 130, 1750000060]] } },
] });

function browser({ storage = new Map(), state = null, href = 'https://rodnik.test/1/#map=10/55/37' } = {}) {
    let sequence = 0;
    let writes = 0;
    const window = {
        location: new URL(href),
        crypto: { randomUUID: () => `private-${++sequence}` },
        sessionStorage: {
            getItem: key => storage.get(key) ?? null,
            setItem: (key, value) => { writes++; storage.set(key, value); },
        },
        history: {
            state,
            replaceState(state, title, href) { this.state = structuredClone(state); window.location = new URL(href); },
            pushState(state, title, href) { this.replaceState(state, title, href); },
        },
    };
    return { window, storage, writes: () => writes };
}

test('private history restores an immutable local track after reload without exposing coordinates in the URL or history entry', () => {
    const original = track();
    const page = browser();
    const map = { sharedTrack: { status: 'local', hash: null }, tracks: { operation: { track: original } } };
    const reference = captureLocalTrackHistory(page.window, map, 7);
    writeDuoHistory(page.window, page.window.location.href, { replace: true, localTrack: reference });

    const reloaded = browser({ storage: page.storage, state: structuredClone(page.window.history.state) });
    const restored = readLocalTrackHistory(reloaded.window, 7);
    assert.deepEqual(restored, original);
    assert.throws(() => { restored.features[0].properties.name = 'Changed'; }, TypeError);
    assert.deepEqual(Object.keys(reloaded.window.history.state.rodnik).sort(), ['duo', 'localTrackId', 'localTrackOwnerId', 'url']);
    assert.equal(reloaded.window.location.hash, '#map=10/55/37');
    assert.equal(JSON.stringify(reloaded.window.history.state).includes('Private route'), false);
});

test('camera updates and restored immutable tracks reuse one private snapshot', () => {
    const page = browser();
    const original = track();
    const map = { sharedTrack: { status: 'local', hash: null }, tracks: { operation: { track: original } } };
    const reference = captureLocalTrackHistory(page.window, map, null);
    for (let zoom = 3; zoom < 20; zoom++) {
        const next = captureLocalTrackHistory(page.window, map, null);
        assert.equal(next, reference);
        writeDuoHistory(page.window, `https://rodnik.test/1/#map=${zoom}/55/37`, { replace: true, localTrack: next });
    }
    assert.equal(page.writes(), 1);

    map.tracks.operation.track = readLocalTrackHistory(page.window, null);
    assert.deepEqual(captureLocalTrackHistory(page.window, map, null), reference);
    assert.equal(page.writes(), 1);
});

test('foreign owners, pasted URLs and shared map links do not inherit a private track', () => {
    const page = browser();
    const reference = captureLocalTrackHistory(page.window, {
        sharedTrack: { status: 'local', hash: null }, tracks: { operation: { track: track() } },
    }, 7);
    writeDuoHistory(page.window, page.window.location.href, { localTrack: reference });
    assert.equal(readLocalTrackHistory(page.window, 8), null);
    for (const href of ['https://rodnik.test/1/#map=11/55/37', 'https://rodnik.test/maps/weekend/', 'https://rodnik.test/maps/shared01']) {
        assert.equal(readLocalTrackHistory(page.window, 7, href), null);
    }
    writeDuoHistory(page.window, `https://rodnik.test/1/#map=10/55/37&track=${'a'.repeat(64)}`, { localTrack: reference });
    assert.equal(readLocalTrackHistory(page.window, 7), null);
});

test('published tracks and track removal clear private history metadata', () => {
    const page = browser();
    const map = { sharedTrack: { status: 'local', hash: null }, tracks: { operation: { track: track() } } };
    const reference = captureLocalTrackHistory(page.window, map, null);
    writeDuoHistory(page.window, page.window.location.href, { localTrack: reference });
    map.sharedTrack = { status: 'saved', hash: 'a'.repeat(64) };
    assert.equal(captureLocalTrackHistory(page.window, map, null), null);
    writeDuoHistory(page.window, page.window.location.href, { localTrack: null, replace: true });
    assert.equal(page.window.history.state.rodnik.localTrackId, undefined);
    assert.equal(readLocalTrackHistory(page.window, null), null);
});

test('in-tab restoration remains usable when session storage is unavailable', () => {
    const page = browser();
    page.window.sessionStorage = { setItem() { throw new Error('Storage denied'); }, getItem() { throw new Error('Storage denied'); } };
    const original = track();
    const reference = captureLocalTrackHistory(page.window, {
        sharedTrack: { status: 'local', hash: null }, tracks: { operation: { track: original } },
    }, null);
    writeDuoHistory(page.window, page.window.location.href, { localTrack: reference });
    assert.deepEqual(readLocalTrackHistory(page.window, null), original);
});
