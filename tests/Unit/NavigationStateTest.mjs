import assert from 'node:assert/strict';
import test from 'node:test';
import { localizedNavigationUrl, resourceNavigationUrl, NavigationSnapshots, NavigationRequests } from '../../resources/js/navigationState.js';

test('selecting a source retains locale and encoded independent context', () => {
    const url = resourceNavigationUrl('https://rodnik.test/ru/users/64/?map=a%26b&track=first%2Bsecond#view=8/1/2', { spring: 123, user: 64 });
    assert.equal(url.pathname, '/ru/123/');
    assert.equal(url.searchParams.get('user'), '64');
    assert.equal(url.searchParams.get('map'), 'a&b');
    assert.equal(url.searchParams.get('track'), 'first+second');
    assert.equal(url.hash, '#view=8/1/2');
});

test('clearing a selection removes its identity while preserving independent context', () => {
    const url = resourceNavigationUrl('https://rodnik.test/123/?user=64&location=1&redirect=false&map=abc', {});
    assert.equal(url.pathname, '/');
    assert.equal(url.search, '?map=abc');
});

test('selecting an author uses their primary route', () => {
    assert.equal(resourceNavigationUrl('https://rodnik.test/ru/123/', { user: 64 }).href, 'https://rodnik.test/ru/users/64/');
});

test('language round trips preserve identity, encoded context, and fragment', () => {
    const original = 'https://rodnik.test/123/?user=64&map=a%26b#view=8/1/2';
    const russian = localizedNavigationUrl(original, 'ru');
    assert.equal(russian.pathname, '/ru/123/');
    assert.equal(localizedNavigationUrl(russian, 'en').href, original);
});

test('Russian home matches the canonical route without a redirect', () => {
    assert.equal(localizedNavigationUrl('https://rodnik.test/', 'ru').pathname, '/ru');
    assert.equal(resourceNavigationUrl('https://rodnik.test/ru/123/', {}).pathname, '/ru');
});

test('snapshots remain independent after live state changes and survive more than ten visits', () => {
    const data = new Map();
    const storage = { getItem: key => data.get(key), setItem: (key, value) => data.set(key, value) };
    const snapshots = new NavigationSnapshots(storage);
    const state = { map: { center: [10, 20], filters: { with_reports: false } }, scroll: [0, 900] };
    snapshots.set('first', state);
    state.map.center[0] = 100;
    state.map.filters.with_reports = true;
    for (let i = 0; i < 15; i++) snapshots.set(String(i), state);
    const reloaded = new NavigationSnapshots(storage);
    assert.deepEqual(reloaded.get('first'), { map: { center: [10, 20], filters: { with_reports: false } }, scroll: [0, 900] });
});

test('history still restores in memory when storage is blocked or full', () => {
    const snapshots = new NavigationSnapshots({ getItem() { throw Error(); }, setItem() { throw Error(); } });
    snapshots.set('entry', { map: { zoom: 12 } });
    assert.deepEqual(snapshots.get('entry'), { map: { zoom: 12 } });
    assert.equal(snapshots.get('missing'), null);
});

test('many history entries store unchanged track geometry only once', () => {
    const data = new Map();
    let trackWrites = 0;
    const storage = {
        getItem: key => data.get(key),
        setItem(key, value) { data.set(key, value); if (key.includes('.track.')) trackWrites++; },
    };
    const snapshots = new NavigationSnapshots(storage);
    const track = { type: 'FeatureCollection', features: [{ geometry: { coordinates: [[1, 2], [3, 4]] } }] };
    for (let i = 0; i < 20; i++) snapshots.set(String(i), { map: { zoom: i, track } });
    assert.equal(trackWrites, 1);
    assert.equal(data.get('rodnik.navigation.0').includes('coordinates'), false);
    assert.deepEqual(new NavigationSnapshots(storage).get('0'), { map: { zoom: 0, track } });
    assert.deepEqual(snapshots.get('19'), { map: { zoom: 19, track } });
});

test('a newer navigation cancels only the older page request', () => {
    const requests = new NavigationRequests();
    const componentController = new AbortController();
    const oldOptions = { signal: new AbortController().signal };
    requests.start();
    requests.attach(oldOptions);
    requests.start();
    const currentOptions = {};
    requests.attach(currentOptions);
    assert.equal(oldOptions.signal.aborted, true);
    assert.equal(currentOptions.signal.aborted, false);
    assert.equal(componentController.signal.aborted, false);
    assert.equal(requests.owns(oldOptions.signal.reason), true);
    assert.equal(requests.owns(new Error('unrelated')), false);
});

test('Back can cancel the pending page request before native history restoration', () => {
    const requests = new NavigationRequests();
    requests.start();
    const options = {};
    requests.attach(options);
    requests.cancel();
    assert.equal(options.signal.aborted, true);
    assert.equal(requests.owns(options.signal.reason), true);
});


test('resource and language navigation retain whole-world report scope under the short query name', () => {
    for (const url of [resourceNavigationUrl('https://rodnik.test/?whole_world=1&campaign=walk', { spring: 123 }),
        localizedNavigationUrl('https://rodnik.test/123/?whole_world=1&campaign=walk', 'ru')]) {
        assert.equal(url.searchParams.get('w'), '1');
        assert.equal(url.searchParams.has('whole_world'), false);
        assert.equal(url.searchParams.get('campaign'), 'walk');
    }
});

test('explicit area scope remains available until report preferences consume it', () => {
    const url = resourceNavigationUrl('https://rodnik.test/?whole_world=0', {});
    assert.equal(url.searchParams.get('w'), '0');
    assert.equal(url.searchParams.has('whole_world'), false);
});
