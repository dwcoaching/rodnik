import assert from 'node:assert/strict';
import test from 'node:test';
import { initialMapConfiguration, mapStateUrl, parseMapUrlState } from '../../resources/js/mapUrlState.js';
import { sharedMapFilterDefaults, sharedMapSources } from '../../resources/js/sharedMapState.js';

const defaults = () => ({
    version: 1,
    center: [30.456768, 36.328095],
    zoom: 12,
    sourceName: 'osm',
    filters: { ...sharedMapFilterDefaults },
    overlays: { stravaPublic: false, osmTraces: false },
    fullscreen: false,
    minimized: false,
});

test('ordinary map links retain resource identity and compact the camera in readable coordinates', () => {
    const state = { ...defaults(), center: [30.45676812345, 36.3280949], zoom: 12.3456 };
    const url = mapStateUrl('https://rodnik.test/ru/1392969/?user=64&context=a%26b#old', state);
    assert.ok(url instanceof URL);
    assert.equal(url.href, 'https://rodnik.test/ru/1392969/?user=64&context=a%26b#m=12.35/36.328095/30.456768');
    assert.deepEqual(parseMapUrlState(url), { state: { ...defaults(), zoom: 12.35 }, trackToken: null });
});

test('a camera-only URL explicitly restores defaults and clears any previous track', () => {
    assert.deepEqual(parseMapUrlState('https://rodnik.test/#map=12/36.328095/30.456768'), {
        state: defaults(), trackToken: null,
    });
});

test('every supported layer survives serialization and parsing', () => {
    for (const sourceName of sharedMapSources) {
        const state = { ...defaults(), sourceName };
        assert.deepEqual(parseMapUrlState(mapStateUrl('https://rodnik.test/', state)), { state, trackToken: null });
    }
});

test('filters, overlays, a track reference, and saved layout flags round trip without storing raw geometry', () => {
    const state = {
        ...defaults(), sourceName: 'satellite',
        filters: { ...sharedMapFilterDefaults, spring: false, water_well: false, with_reports: true, along: true },
        overlays: { stravaPublic: true, osmTraces: true },
        fullscreen: true, minimized: true,
        page: { user: 64, spring: 1392969 },
        track: { type: 'FeatureCollection', features: [] },
    };
    const url = mapStateUrl('https://rodnik.test/1392969/?user=64', state, 'AbCd123456');
    assert.equal(url.hash, '#m=12/36.328095/30.456768&l=satellite&f=tap,drinking,fountain,other,reports,along&o=strava,traces&t=' + 'AbCd123456' + '&v=full,min');
    const { page, track, ...expected } = state;
    assert.deepEqual(parseMapUrlState(url), { state: expected, trackToken: 'AbCd123456' });
    assert.equal(url.href.includes('FeatureCollection'), false);
});

test('turning every source type off remains distinct from the default of showing all types', () => {
    const state = { ...defaults(), filters: Object.fromEntries(Object.keys(sharedMapFilterDefaults).map(name => [name, false])) };
    const url = mapStateUrl('https://rodnik.test/', state);
    assert.equal(url.hash.endsWith('&f=none'), true);
    assert.deepEqual(parseMapUrlState(url), { state, trackToken: null });
});

test('unsupported settings safely fall back and unknown fragment parameters are ignored', () => {
    const url = 'https://rodnik.test/#map=12/36.328095/30.456768&layer=bogus&filters=bogus&reports=true&along=2&overlays=bogus&fullscreen=yes&minimized=-1&track=not-a-track&unrelated=1';
    assert.deepEqual(parseMapUrlState(url), { state: defaults(), trackToken: null });
});

test('obsolete names do not discard recognized filters or overlays in otherwise valid links', () => {
    const parsed = parseMapUrlState('https://rodnik.test/#map=12/36.328095/30.456768&filters=spring,obsolete&overlays=osmTraces,obsolete');
    assert.deepEqual(parsed.state.filters, {
        spring: true, water_well: false, water_tap: false, drinking_water: false,
        fountain: false, other: false, with_reports: false, along: false,
    });
    assert.deepEqual(parsed.state.overlays, { stravaPublic: false, osmTraces: true });
});

test('absent, incomplete, malformed, and out-of-range cameras are ignored without throwing', () => {
    const invalid = [
        '', '#about', '#layer=satellite&track=' + 'AbCd123456', '#map=', '#map=12/30',
        '#map=12/30/40/50', '#map=12//40', '#map=12/%20/40', '#map=12/NaN/40',
        '#map=Infinity/30/40', '#map=12/30/Infinity', '#map=12/0x10/40', '#map=12/1e2/40',
        '#map=29/30/40', '#map=-1/30/40', '#map=12/90.0001/40', '#map=12/-90.0001/40',
        '#map=12/30/180.0001', '#map=12/30/-180.0001', '#map=12/%26/40',
    ];
    for (const fragment of invalid) {
        assert.equal(parseMapUrlState(`https://rodnik.test/${fragment}`), null, fragment);
    }
    assert.equal(parseMapUrlState('not a URL'), null);
    assert.equal(parseMapUrlState(null), null);
});

test('camera boundaries and zero values are supported without negative zero or unnecessary decimals', () => {
    for (const state of [
        { ...defaults(), center: [-180, -90], zoom: 0 },
        { ...defaults(), center: [180, 90], zoom: 28 },
        { ...defaults(), center: [0, 0], zoom: 1.5 },
    ]) {
        assert.deepEqual(parseMapUrlState(mapStateUrl('https://rodnik.test/', state)), { state, trackToken: null });
    }
    assert.equal(mapStateUrl('https://rodnik.test/', { ...defaults(), center: [-0.00000001, -0.00000001], zoom: 0 }).hash, '#m=0/0/0');
});

test('malformed track references never enter the URL', () => {
    for (const track of ['a'.repeat(64), 'a'.repeat(63), 'a'.repeat(65), 'g'.repeat(64), '../track.json', { type: 'FeatureCollection' }]) {
        const url = mapStateUrl('https://rodnik.test/', defaults(), track);
        assert.equal(url.hash.includes('t='), false);
        assert.equal(parseMapUrlState(url).trackToken, null);
    }
});

test('initial URL state overrides a shared map and uses the normalized server resource selection', () => {
    const sharedMap = {
        state: { ...defaults(), center: [1, 2], zoom: 5, page: { spring: 123, user: 456 } },
        track: { token: 'AbCd123456' },
    };
    const config = initialMapConfiguration(
        'https://rodnik.test/maps/example#map=9/40/20&track=' + 'XyZ0987654',
        { spring: '1392969', user: null, location: null },
        sharedMap,
    );
    assert.deepEqual(config, {
        state: { ...defaults(), center: [20, 40], zoom: 9, page: { spring: 1392969, user: null, location: null } },
        track: 'XyZ0987654',
    });
});

test('a URL with no track requests an empty track rather than inheriting the saved map track', () => {
    const config = initialMapConfiguration('https://rodnik.test/users/64/#map=12/36.328095/30.456768', { user: 64 }, {
        state: defaults(), track: { token: 'AbCd123456' },
    });
    assert.deepEqual(config, {
        state: { ...defaults(), page: { spring: null, user: 64, location: null } },
        track: null,
    });
});

test('a missing or invalid URL camera retains existing saved-map and ordinary-visit behavior', () => {
    const sharedMap = { state: defaults(), track: { token: 'AbCd123456' } };
    for (const fragment of ['', '#map=invalid']) {
        assert.deepEqual(initialMapConfiguration(`https://rodnik.test/maps/example${fragment}`, {}, sharedMap), sharedMap);
        assert.deepEqual(initialMapConfiguration(`https://rodnik.test/${fragment}`, {}), { state: null, track: null });
    }
});


test('short track tokens preserve case while public view options use stable readable names', () => {
    const state = { ...defaults(), sourceName: 'satellite',
        filters: { ...sharedMapFilterDefaults, with_reports: true, along: true },
        overlays: { stravaPublic: true, osmTraces: true }, fullscreen: true, minimized: true };
    const url = mapStateUrl('https://rodnik.test/', state, 'AbCd123456');
    assert.equal(url.hash, '#m=12/36.328095/30.456768&l=satellite&f=spring,well,tap,drinking,fountain,other,reports,along&o=strava,traces&t=AbCd123456&v=full,min');
    assert.deepEqual(parseMapUrlState(url), { state, trackToken: 'AbCd123456' });
});

test('every named filter and overlay combination restores exactly', () => {
    const names = Object.keys(sharedMapFilterDefaults);
    for (let bits = 0; bits < 256; bits++) {
        const state = { ...defaults(), filters: Object.fromEntries(names.map((name, index) => [name, Boolean(bits & (1 << index))])),
            overlays: { stravaPublic: Boolean(bits & 1), osmTraces: Boolean(bits & 2) } };
        assert.deepEqual(parseMapUrlState(mapStateUrl('https://rodnik.test/', state)), { state, trackToken: null });
    }
});

test('invalid compact flags cannot silently hide all source types or invent a track', () => {
    for (const flags of ['-1', '100', '', '<invalid>']) {
        const parsed = parseMapUrlState(`https://rodnik.test/#m=12/36.328095/30.456768&f=${flags}&o=${flags}&v=${flags}&t=invalid`);
        assert.deepEqual(parsed, { state: defaults(), trackToken: null });
    }
});

test('normal copied URLs always leave saved-map paths and remove the track-only launch query', () => {
    assert.equal(mapStateUrl('https://rodnik.test/ru/maps/coastal-walk', { ...defaults(), page: { user: 12 } }, 'AbCd123456').href,
        'https://rodnik.test/ru/users/12/#m=12/36.328095/30.456768&t=AbCd123456');
    assert.equal(mapStateUrl('https://rodnik.test/?t=AbCd123456', defaults()).href,
        'https://rodnik.test/#m=12/36.328095/30.456768');
});

test('track-only library links load without overriding the camera until the track can be fitted', () => {
    for (const suffix of ['?t=AbCd123456', '#t=AbCd123456']) {
        assert.deepEqual(initialMapConfiguration(`https://rodnik.test/${suffix}`, {}), { state: null, track: 'AbCd123456' });
    }
});

test('a saved map with no track retains its view without a deletion notice', () => {
    assert.deepEqual(initialMapConfiguration('https://rodnik.test/maps/coastal-walk', {}, { state: defaults(), track: null }),
        { state: defaults(), track: null });
});


test('named values ignore unknown future items while preserving every recognized option', () => {
    const url = 'https://rodnik.test/#m=12/36.328095/30.456768&l=topo&f=well,removed-source,spring,reports,future-filter2&o=future-overlay,traces&v=future-layout,min';
    const parsed = parseMapUrlState(url);
    assert.equal(parsed.state.sourceName, 'openTopoMap');
    assert.deepEqual(parsed.state.filters, { spring: true, water_well: true, water_tap: false,
        drinking_water: false, fountain: false, other: false, with_reports: true, along: false });
    assert.deepEqual(parsed.state.overlays, { stravaPublic: false, osmTraces: true });
    assert.equal(parsed.state.fullscreen, false);
    assert.equal(parsed.state.minimized, true);
    assert.equal(mapStateUrl('https://rodnik.test/', parsed.state).hash,
        '#m=12/36.328095/30.456768&l=topo&f=spring,well,reports&o=traces&v=min');
});

test('named filters are independent of ordering and duplicate values', () => {
    const prefixes = ['f=spring,well,along&o=strava,traces&v=full,min', 'f=along,well,spring,well&o=traces,strava&v=min,full'];
    assert.deepEqual(...prefixes.map(value => parseMapUrlState(`https://rodnik.test/#m=12/36.328095/30.456768&${value}`)));
});

test('previous compact bitmask URLs retain their original fixed meanings and rewrite to names', () => {
    const old = 'https://rodnik.test/#m=12/36.328095/30.456768&l=p&f=2f&o=3&t=AbCd123456&v=3';
    const parsed = parseMapUrlState(old);
    assert.equal(parsed.state.sourceName, 'openTopoMap');
    assert.deepEqual(parsed.state.filters, { spring: true, water_well: true, water_tap: true,
        drinking_water: true, fountain: false, other: true, with_reports: false, along: false });
    assert.equal(mapStateUrl('https://rodnik.test/', parsed.state, parsed.trackToken).hash,
        '#m=12/36.328095/30.456768&l=topo&f=spring,well,tap,drinking,other&o=strava,traces&t=AbCd123456&v=full,min');
});

test('full map URLs replace legacy report scope query names with w without changing unrelated context', () => {
    const url = mapStateUrl('https://rodnik.test/?whole_world=1&campaign=walk', defaults());
    assert.equal(url.searchParams.get('w'), '1');
    assert.equal(url.searchParams.has('whole_world'), false);
    assert.equal(url.searchParams.get('campaign'), 'walk');
});


test('an unavailable named source type is ignored rather than showing unrelated types', () => {
    const parsed = parseMapUrlState('https://rodnik.test/#m=12/36.328095/30.456768&f=obsolete');
    assert.deepEqual(parsed.state.filters, Object.fromEntries(Object.keys(sharedMapFilterDefaults).map(name => [name, false])));
    assert.equal(mapStateUrl('https://rodnik.test/', parsed.state).hash, '#m=12/36.328095/30.456768&f=none');
    const mixed = parseMapUrlState('https://rodnik.test/#m=12/36.328095/30.456768&f=obsolete,spring');
    assert.equal(mixed.state.filters.spring, true);
    assert.equal(mixed.state.filters.water_well, false);
});
