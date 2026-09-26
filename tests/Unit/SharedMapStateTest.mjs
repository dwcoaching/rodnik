import assert from 'node:assert/strict';
import { registerHooks } from 'node:module';
import test from 'node:test';
import View from 'ol/View.js';
import VectorSource from 'ol/source/Vector.js';
import Feature from 'ol/Feature.js';
import LineString from 'ol/geom/LineString.js';
import { fromLonLat } from 'ol/proj.js';
import Tracks from '../../resources/js/tracks.js';
import { normalizeSharedMapState } from '../../resources/js/sharedMapState.js';
import { initialMapConfiguration } from '../../resources/js/mapUrlState.js';

const imports = registerHooks({
    resolve(specifier, context, nextResolve) {
        if (specifier === 'exifr') return nextResolve('exifr/dist/full.esm.mjs', context);
        if (specifier.startsWith('@/')) {
            return nextResolve(new URL(`../../resources/js/${specifier.slice(2).replace(/(?:\.js)?$/, '.js')}`, import.meta.url).href, context);
        }
        if (specifier.startsWith('ol/') && !specifier.endsWith('.js')) return nextResolve(`${specifier}.js`, context);
        return nextResolve(specifier, context);
    },
});
const { default: OpenLayersMap } = await import('../../resources/js/openLayers.js');
const { default: TrackLayer } = await import('../../resources/js/layers/tracks/track.js');
const { default: TrackSource } = await import('../../resources/js/sources/track.js');
const { default: trackStyle } = await import('../../resources/js/styles/track.js');
imports.deregister();

const saved = () => normalizeSharedMapState({
    version: 1, center: [37.123456789, 55.987654321], zoom: 12.325,
    sourceName: 'satellite', filters: { spring: false, with_reports: true, along: true },
    overlays: { osmTraces: true }, page: { user: 17, spring: 45 }, fullscreen: true,
});

function setup(t) {
    const previous = { Alpine: globalThis.Alpine, window: globalThis.window };
    const layout = { fullscreen: false, minimized: true };
    globalThis.Alpine = { nextTick: async () => {} };
    globalThis.window = new EventTarget();
    t.after(() => Object.assign(globalThis, previous));
    const source = new VectorSource();
    const trackLoads = [];
    const map = Object.assign(Object.create(OpenLayersMap.prototype), {
        sharedConfig: { layout: () => layout }, sharedRestoreGeneration: 0,
        view: new View({ center: [0, 0], zoom: 3 }), sourceState: { name: 'osm' },
        filters: { all: true }, overlays: {}, queryParameters: {},
        trackLayer: { getSource: () => source, isUploaded: { value: false }, clear() { source.clear(); } },
        tracks: { load(reference) { trackLoads.push(reference); return new Promise(() => {}); } },
        buffer: { setTrack() {} },
        map: { setView() {}, updateSize() {}, renderSync() {} },
        source(name) { this.sourceState.name = name; }, updateOverlays() {}, updateFilterStyles() {},
    });
    globalThis.window.rodnikMap = map;
    return { map, layout, source, trackLoads };
}

test('saved map states preserve canonical filters and omit derived or transient values', () => {
    const state = saved();
    assert.equal(state.filters.spring, false);
    assert.equal(state.filters.water_well, true);
    assert.equal(state.filters.with_reports, true);
    assert.equal(state.filters.all, undefined);
    assert.deepEqual(state.page, { spring: 45, user: 17, location: null });
    assert.equal(state.minimized, false);
    assert.throws(() => normalizeSharedMapState({ ...state, version: 2 }), /invalid/);
    assert.throws(() => normalizeSharedMapState({ ...state, center: [Infinity, 20] }), /invalid/);
});

test('obsolete shared filters reset to current defaults without changing report presence', () => {
    const state = normalizeSharedMapState({ ...saved(), filters: { spring: false, confirmed: true } });

    assert.equal(state.filters.spring, false);
    assert.equal(state.filters.with_reports, false);
    assert.equal(Object.hasOwn(state.filters, 'confirmed'), false);

    const current = normalizeSharedMapState({ ...state, filters: { ...state.filters, with_reports: true, confirmed: true } });
    assert.equal(current.filters.with_reports, true);
    assert.equal(Object.hasOwn(current.filters, 'confirmed'), false);
});

test('restoration applies exact camera and layout without waiting for the track network', async t => {
    const { map, layout, trackLoads } = setup(t);
    const state = saved();
    await map.restoreSharedState(state, { track: 'a'.repeat(64) });
    assert.deepEqual(map.view.getCenter(), fromLonLat(state.center));
    assert.equal(map.view.getZoom(), state.zoom);
    assert.equal(map.filters.all, false);
    assert.equal(map.filters.along, true);
    assert.equal(map.filters.with_reports, true);
    assert.deepEqual(layout, { fullscreen: true, minimized: false });
    assert.equal(map.sourceState.name, 'satellite');
    assert.deepEqual(trackLoads, ['a'.repeat(64)]);
    assert.equal(map.restoringSharedState, false);
    const captured = map.captureSharedState();
    assert.ok(Math.abs(captured.center[0] - state.center[0]) < 1e-12);
    assert.ok(Math.abs(captured.center[1] - state.center[1]) < 1e-12);
    assert.deepEqual(captured.filters, state.filters);
});

test('late user-source loads cannot recenter a restored map', async t => {
    const { map } = setup(t);
    await map.restoreSharedState({ ...saved(), page: { user: 17 } });
    map.previousQueryParameters = {};
    let fits = 0;
    map.locateWorld = () => fits++;
    map.featuresLoadEnd();
    assert.equal(fits, 0);
    map.duoVisit({ user: 18 });
    map.featuresLoadEnd();
    assert.equal(fits, 1);
});

test('returning to the source selected by the initial URL can recenter after visiting another source', async t => {
    const { map, layout } = setup(t);
    map.reportCoordinates = {};
    await map.restoreSharedState({ ...saved(), page: { spring: 45 } });
    map.duoVisit({ spring: 46, user: null, location: null, coordinates: [21, 41] });
    assert.equal(map.preserveMapView, false);
    assert.deepEqual(map.queryParameters.coordinates, [21, 41]);

    map.duoVisit({ spring: 45, user: null, location: null, coordinates: [20, 40] });
    assert.equal(map.preserveMapView, false);
    assert.deepEqual(map.queryParameters.coordinates, [20, 40]);
    assert.deepEqual(layout, { fullscreen: false, minimized: false });

    map.duoVisit({ spring: 45, user: null, location: null, coordinates: [20, 40], preserveMapView: true });
    assert.equal(map.preserveMapView, true);
    assert.equal(map.queryParameters.coordinates, null);
});

test('shared track geometry can arrive after panning without changing the view', async t => {
    const { map, source } = setup(t);
    await map.restoreSharedState(saved());
    map.view.setCenter(fromLonLat([10, 40]));
    map.view.setZoom(7.2);
    const center = [...map.view.getCenter()];
    map.applySharedTrack({ type: 'FeatureCollection', features: [
        { type: 'Feature', properties: { name: 'Waypoint' }, geometry: { type: 'Point', coordinates: [25, 45] } },
    ] });
    assert.deepEqual(map.view.getCenter(), center);
    assert.equal(map.view.getZoom(), 7.2);
    assert.equal(source.getFeatures()[0].get('name'), 'Waypoint');
    assert.equal(map.trackLayer.isUploaded.value, true);
});

test('imported and recovered GPX tracks render before automatic upload starts', async t => {
    const { map } = setup(t);
    const previousStorage = globalThis.localStorage;
    globalThis.localStorage = { getItem: () => '<gpx />', setItem() {} };
    t.after(() => { globalThis.localStorage = previousStorage; });
    let requests = 0;
    let uiReady = Promise.withResolvers();
    map.tracks = new Tracks({
        endpoint: '/tracks',
        reactive: value => value,
        defer: () => uiReady.promise,
        fetch: async (url, options) => {
            requests++;
            const payload = JSON.parse(options.body);
            const track = JSON.parse(payload.track);
            assert.equal(track.features[0].geometry.coordinates[0].length, 4, 'precise elevation and timing survive uploading');
            return Response.json({ id: requests, hash: payload.hash, token: 'AbCd123456' });
        },
    });
    map.sharedTrack = map.tracks.state;
    const source = new TrackSource();
    source.createFeatures = () => [new Feature({
        name: 'Private route',
        geometry: new LineString([[1000, 2000, 123, 1750000000], [2000, 3000, 124, 1750000060]]),
    })];
    map.trackLayer = Object.assign(Object.create(TrackLayer.prototype), {
        getSource: () => source,
        isUploaded: { value: false },
    });

    for (const [index, restore] of [() => map.trackLayer.load('<gpx />'), () => map.trackLayer.restoreFromLocalStorage()].entries()) {
        uiReady = Promise.withResolvers();
        restore();
        await new Promise(setImmediate);
        assert.equal(source.getFeatures().length, 1);
        assert.equal(map.trackLayer.isUploaded.value, true);
        assert.equal(map.sharedTrack.status, 'saving');
        assert.equal(map.sharedTrack.hash, null);
        assert.equal(requests, index);
        uiReady.resolve();
        await map.ensureSharedTrack();
        assert.equal(map.sharedTrack.status, 'saved');
        assert.equal(map.sharedTrack.token, 'AbCd123456');
        assert.equal(requests, index + 1);
    }
});

test('automatic GPX upload preserves times without inventing missing elevations', async t => {
    const { map, source } = setup(t);
    source.addFeature(new Feature({
        name: 'Timed walk',
        geometry: new LineString([[30, 40, 1750000000], [31, 41, 1750000060]], 'XYM').transform('EPSG:4326', map.view.getProjection()),
    }));
    let uploaded;
    map.tracks = { replace(track) { uploaded = track; } };
    map.trackChanged();
    const coordinates = uploaded.features[0].geometry.coordinates;
    assert.equal(coordinates[0][2], null);
    assert.equal(coordinates[0][3], 1750000000);
    assert.equal(coordinates[1][2], null);
    assert.equal(coordinates[1][3], 1750000060);
    assert.equal(source.getFeatures()[0].getGeometry().getLayout(), 'XYM');
});

test('legacy shared properties cannot replace geometry or crash label rendering', t => {
    const { map, source } = setup(t);
    const track = { type: 'FeatureCollection', features: [
        { type: 'Feature', properties: { geometry: 'invalid', name: 123 }, geometry: { type: 'Point', coordinates: [25, 45] } },
        { type: 'Feature', properties: { geometry: null, desc: true }, geometry: { type: 'Point', coordinates: [26, 46] } },
    ] };

    assert.doesNotThrow(() => map.applySharedTrack(track));
    const features = source.getFeatures();
    assert.deepEqual(features.map(feature => feature.getGeometry().getCoordinates()), [fromLonLat([25, 45]), fromLonLat([26, 46])]);
    assert.deepEqual(features.map(feature => trackStyle(feature).getText().getText()), ['123', 'true']);
    assert.equal(track.features[0].properties.geometry, 'invalid', 'the server record remains unchanged');
});

test('pending upload history restores geometry and resumes uploading without changing the map view', async t => {
    const { map, source, layout } = setup(t);
    await map.restoreSharedState(saved(), { track: null });
    let requests = 0;
    map.tracks = new Tracks({ endpoint: '/tracks', reactive: value => value, defer: async () => {},
        fetch: async (url, options) => { requests++; return Response.json({ id: 1, hash: JSON.parse(options.body).hash, token: 'AbCd123456' }); } });
    map.sharedTrack = map.tracks.state;
    const before = map.captureSharedState();
    map.restoreLocalTrack({ type: 'FeatureCollection', features: [
        { type: 'Feature', properties: { name: 'Private route' }, geometry: { type: 'LineString', coordinates: [[25, 45], [26, 46]] } },
    ] });
    await new Promise(setImmediate);

    assert.equal(source.getFeatures().length, 1);
    assert.deepEqual(map.captureSharedState(), before);
    assert.equal(layout.fullscreen, before.fullscreen);
    await map.ensureSharedTrack();
    assert.equal(map.sharedTrack.status, 'saved');
    assert.equal(map.sharedTrack.token, 'AbCd123456');
    assert.equal(requests, 1);
});

test('different viewport sizes keep a shared low zoom and polar center unchanged', async t => {
    const { map } = setup(t);
    const state = { ...saved(), center: [22, 80], zoom: 1.25 };
    for (const size of [[360, 780], [1920, 1080], [1920, 40], [390, 300]]) {
        map.map.updateSize = () => map.view.setViewportSize(size);
        await map.restoreSharedState(state);
        assert.deepEqual(map.view.getCenter(), fromLonLat(state.center));
        assert.equal(map.view.getZoom(), state.zoom);
        map.view.setViewportSize([size[1], size[0]]);
        assert.deepEqual(map.view.getCenter(), fromLonLat(state.center));
        assert.equal(map.view.getZoom(), state.zoom);
    }
});

test('fullscreen and minimized layout flags round-trip independently', async t => {
    const { map, layout } = setup(t);
    await map.restoreSharedState({ ...saved(), fullscreen: true, minimized: true });
    assert.deepEqual(layout, { fullscreen: true, minimized: true });
    assert.equal(map.captureSharedState().minimized, true);
});

test('opening a normal map URL removes an existing track and retains the server-selected source', async t => {
    const { map, source, trackLoads } = setup(t);
    map.applySharedTrack({ type: 'FeatureCollection', features: [
        { type: 'Feature', properties: {}, geometry: { type: 'Point', coordinates: [25, 45] } },
    ] });
    assert.equal(source.getFeatures().length, 1);

    const config = initialMapConfiguration('https://rodnik.test/45/#map=8/40/20', { spring: 45 });
    await map.restoreSharedState(config.state, { track: config.track });

    assert.equal(source.getFeatures().length, 0);
    assert.deepEqual(trackLoads, [null]);
    assert.deepEqual(map.queryParameters, { spring: 45, user: null, location: null, coordinates: null });
    assert.deepEqual(map.view.getCenter(), fromLonLat([20, 40]));
    assert.equal(map.view.getZoom(), 8);
});
