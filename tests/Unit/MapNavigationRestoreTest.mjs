import assert from 'node:assert/strict';
import { registerHooks } from 'node:module';
import test from 'node:test';
import View from 'ol/View.js';
import VectorSource from 'ol/source/Vector.js';
import Feature from 'ol/Feature.js';
import Point from 'ol/geom/Point.js';
import VectorLayer from 'ol/layer/Vector.js';
import { get as getProjection, fromLonLat, toLonLat } from 'ol/proj.js';
import SpringsFinalSource from '../../resources/js/sources/final.js';
import SpringsUserSource from '../../resources/js/sources/user.js';

const browserImports = registerHooks({
    resolve(specifier, context, nextResolve) {
        if (specifier === 'exifr') {
            return nextResolve('exifr/dist/full.esm.mjs', context);
        }
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
const { default: OpenLayersMap } = await import('../../resources/js/openLayers.js');
browserImports.deregister();

test('map history restoration finishes after local state while polygon persistence remains unresolved', async (t) => {
    const previousAlpine = globalThis.Alpine;
    const layout = { fullscreen: false, minimized: false };
    globalThis.Alpine = { store: () => layout, nextTick: () => Promise.resolve() };
    t.after(() => { globalThis.Alpine = previousAlpine; });

    const source = new VectorSource();
    const persistence = new Promise(() => {});
    let persistedFeatures = null;
    let rendered = false;
    const map = Object.assign(Object.create(OpenLayersMap.prototype), {
        navigationRestoreGeneration: 0,
        disposed: false,
        view: new View({ center: [0, 0], zoom: 3 }),
        sourceState: { name: 'osm' },
        filters: { spring: true, along: false },
        overlays: { osmTraces: false },
        trackLayer: { getSource: () => source, isUploaded: { value: false } },
        buffer: {
            setTrack(features) {
                persistedFeatures = features;
                this.persistencePromise = persistence;
            },
        },
        map: { updateSize() {}, renderSync() { rendered = true; } },
        source(name) { this.sourceState.name = name; },
        updateOverlays() {},
        updateFilterStyles() {},
    });
    const state = {
        center: [1000000, 5000000], projection: 'EPSG:3857', zoom: 12,
        sourceName: 'satellite', filters: { spring: false, along: true },
        overlays: { osmTraces: true }, fullscreen: true, minimized: false,
        track: {
            type: 'FeatureCollection',
            features: [{ type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: [[10, 45], [11, 46]] } }],
        },
    };

    let finished = false;
    const restoration = map.restoreNavigationState(state).then(() => { finished = true; });
    await new Promise(setImmediate);

    assert.equal(finished, true, 'navigation must not wait for the track network request');
    await restoration;
    assert.equal(map.buffer.persistencePromise, persistence);
    assert.equal(persistedFeatures.length, 1);
    assert.equal(source.getFeatures().length, 1);
    assert.equal(map.trackLayer.isUploaded.value, true);
    assert.deepEqual(map.view.getCenter(), state.center);
    assert.equal(map.view.getZoom(), 12);
    assert.deepEqual(map.filters, state.filters);
    assert.deepEqual(map.overlays, state.overlays);
    assert.equal(layout.fullscreen, true);
    assert.equal(rendered, true);
    assert.equal(map.restoringNavigationState, false);
});

test('a location response after leaving the map cannot restart tracking on its disposed owner', t => {
    const navigatorDescriptor = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    let finishLocation;
    let animations = 0;
    let trackingStarts = 0;
    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        value: { geolocation: { getCurrentPosition(callback) { finishLocation = callback; } } },
    });
    t.after(() => {
        if (navigatorDescriptor) Object.defineProperty(globalThis, 'navigator', navigatorDescriptor);
        else delete globalThis.navigator;
    });
    const map = Object.assign(Object.create(OpenLayersMap.prototype), {
        disposed: false,
        geolocation: { getPosition: () => null },
        view: { animate() { animations++; } },
        watchMe() { trackingStarts++; },
    });
    map.locateMe();
    map.disposed = true;
    finishLocation({ coords: { longitude: 37, latitude: 55 } });

    assert.equal(animations, 0);
    assert.equal(trackingStarts, 0);
});

test('a GPX file read finishing after navigation cannot replace the next map track', t => {
    const previousReader = globalThis.FileReader;
    const readers = [];
    globalThis.FileReader = class {
        constructor() { readers.push(this); }
        readAsText() {}
    };
    t.after(() => { globalThis.FileReader = previousReader; });
    const loaded = [];
    const map = Object.assign(Object.create(OpenLayersMap.prototype), {
        disposed: false,
        trackLayer: { load: content => loaded.push(content) },
    });
    map.upload({ type: 'application/gpx+xml' });
    map.disposed = true;
    readers[0].onload({ target: { result: '<gpx />' } });

    assert.deepEqual(loaded, []);
});

function userNavigationMap({ user = 4889, spring = 1392968, location = null, features = true } = {}) {
    const source = new VectorSource();
    source.user = user;
    source.getUser = () => source.user;
    source.setUser = userId => { source.user = userId; source.clear(); };
    const loadFeatures = () => source.addFeatures([
        new Feature(new Point([0, 0])),
        new Feature(new Point([10000, 30000])),
    ]);
    if (features) loadFeatures();
    let activeSource = source;
    const layer = {
        setMinZoom() {},
        setVisible() {},
        setSource(value) { activeSource = value; },
        getSource: () => activeSource,
    };
    const layout = { fullscreen: false, minimized: false };
    const map = Object.assign(Object.create(OpenLayersMap.prototype), {
        disposed: false,
        queryParameters: { spring, user, location, coordinates: null },
        reportCoordinates: {},
        filters: { spring: false, with_reports: true },
        overlays: { osmTraces: true },
        view: new View({ center: [10000, 30000], zoom: 14 }),
        map: { getSize: () => [1000, 700] },
        springsFinalLayer: layer,
        springsApproximatedLayer: layer,
        springsDistantLayer: layer,
        wateredSpringsApproximatedLayer: layer,
        wateredSpringsDistantLayer: layer,
        springsUserSource: source,
        getLayout: () => layout,
        notifySharedStateChange() {},
    });
    map.view.setViewportSize([1000, 700]);
    return { map, source, loadFeatures };
}

for (const user of [null, 4889]) {
    test(`${user ? 'user' : 'global'} report card preserves the camera when its source is in view`, () => {
        const { map } = userNavigationMap({ user, spring: null });
        const coordinates = toLonLat([10500, 30500]);
        const center = [...map.view.getCenter()];
        const zoom = map.view.getZoom();

        map.duoVisit({ spring: 1392969, user, location: null, coordinates, preserveMapViewIfVisible: true });

        assert.equal(map.preserveMapView, true);
        assert.equal(map.queryParameters.spring, 1392969);
        assert.equal(map.queryParameters.user, user);
        assert.equal(map.queryParameters.coordinates, null);
        assert.deepEqual(map.reportCoordinates[1392969], coordinates);
        assert.deepEqual(map.view.getCenter(), center);
        assert.equal(map.view.getZoom(), zoom);
    });

    test(`${user ? 'user' : 'global'} offscreen report card schedules the same source focus as an external link`, () => {
        const coordinates = [37, 55];
        const visit = { spring: 1392969, user, location: null, coordinates };
        const { map } = userNavigationMap({ user, spring: null });
        const { map: externalMap } = userNavigationMap({ user, spring: null });

        map.duoVisit({ ...visit, preserveMapViewIfVisible: true });
        externalMap.duoVisit(visit);

        assert.equal(map.preserveMapView, false);
        assert.deepEqual(map.queryParameters, externalMap.queryParameters);
        assert.deepEqual(map.queryParameters.coordinates, coordinates);
        assert.deepEqual(map.getLayout(), externalMap.getLayout());
        assert.deepEqual(map.filters, { spring: false, with_reports: true });
        assert.deepEqual(map.overlays, { osmTraces: true });

        const animations = [];
        map.view.animate = animation => animations.push(animation);
        map.locate(map.queryParameters.coordinates);
        assert.deepEqual(animations, [{ center: fromLonLat(coordinates), zoom: 14, duration: 250 }]);
    });
}

test('card focus expands a minimized map even when its source is within the stored viewport', () => {
    const { map } = userNavigationMap({ spring: null });
    const coordinates = toLonLat(map.view.getCenter());
    map.getLayout().minimized = true;

    map.duoVisit({ spring: 1392969, coordinates, preserveMapViewIfVisible: true });

    assert.equal(map.preserveMapView, false);
    assert.equal(map.getLayout().minimized, false);
    assert.deepEqual(map.queryParameters.coordinates, coordinates);
});

test('explicit map preservation and shared-state restoration override conditional card focus', () => {
    for (const restoring of [false, true]) {
        const { map } = userNavigationMap({ spring: null });
        map.restoringSharedState = restoring;
        map.duoVisit({
            spring: 1392969, coordinates: [37, 55],
            preserveMapView: !restoring, preserveMapViewIfVisible: true,
        });

        assert.equal(map.preserveMapView, true);
        assert.equal(map.queryParameters.coordinates, null);
        assert.deepEqual(map.view.getCenter(), [10000, 30000]);
    }
});

test('marker selection and background deselection preserve the camera and user context', t => {
    const previousWindow = globalThis.window;
    globalThis.window = new EventTarget();
    t.after(() => { globalThis.window = previousWindow; });
    const { map } = userNavigationMap();
    const feature = new Feature({ id: 1392969, geometry: new Point([50000, 60000]) });
    const events = [];
    window.addEventListener('duo-visit', event => events.push(event.detail));
    map.fullscreen = true;
    map.getLayout().fullscreen = true;
    map.getLayout().minimized = true;
    const center = [...map.view.getCenter()];
    const zoom = map.view.getZoom();

    map.selectFeature(feature);
    assert.deepEqual(events[0], { spring: 1392969, user: 4889, location: null, preserveMapView: true });
    map.duoVisit({ ...events[0], coordinates: [45, 60] });
    map.highlightFeature(feature);
    assert.equal(map.queryParameters.coordinates, null);
    assert.equal(map.previouslyHighlightedFeature, feature);

    map.deselectFeature();
    assert.deepEqual(events[1], { spring: null, user: 4889, preserveMapView: true });
    map.duoVisit({ ...events[1], location: null, coordinates: null });
    map.springsSource(4889);
    map.fitUserOverview();

    assert.deepEqual(map.view.getCenter(), center);
    assert.equal(map.view.getZoom(), zoom);
    assert.equal(map.fullscreen, true);
    assert.equal(map.getLayout().minimized, true);
    assert.equal(map.userOverviewNeedsFit, false);
});

test('returning from a source to the same user overview fits cached sources once', () => {
    const { map, source } = userNavigationMap();

    map.duoVisit({ spring: null, user: 4889, location: null });
    map.springsSource(4889);

    assert.deepEqual(map.view.getCenter(), [5000, 15000]);
    assert.equal(map.view.getZoom(), 8);
    assert.equal(map.springsFinalLayer.getSource(), source);
    assert.deepEqual(map.filters, { spring: false, with_reports: true });
    assert.deepEqual(map.overlays, { osmTraces: true });

    map.view.setCenter([25000, 40000]);
    map.view.setZoom(11);
    map.featuresLoadEnd();
    map.springsSource(4889);

    assert.deepEqual(map.view.getCenter(), [25000, 40000]);
    assert.equal(map.view.getZoom(), 11, 'later source callbacks must not undo the visitor’s next map movement');
});

test('returning to a user overview waits for pending source data before fitting', () => {
    const { map, loadFeatures } = userNavigationMap({ features: false });

    map.duoVisit({ spring: null, user: 4889, location: null });
    assert.doesNotThrow(() => map.springsSource(4889));
    assert.doesNotThrow(() => map.featuresLoadEnd());
    assert.equal(map.view.getZoom(), 14);
    assert.equal(map.userOverviewNeedsFit, true);

    loadFeatures();
    map.featuresLoadEnd();

    assert.deepEqual(map.view.getCenter(), [5000, 15000]);
    assert.equal(map.view.getZoom(), 8);
    assert.equal(map.userOverviewNeedsFit, false);
});

test('explicit view preservation keeps the current camera when returning to a user overview', () => {
    const { map } = userNavigationMap();

    map.duoVisit({ spring: null, user: 4889, location: null, preserveMapView: true });
    map.springsSource(4889);
    map.featuresLoadEnd();

    assert.deepEqual(map.view.getCenter(), [10000, 30000]);
    assert.equal(map.view.getZoom(), 14);
});

test('switching to a new user fits loaded sources while selecting a source cancels a pending fit', () => {
    const { map, loadFeatures } = userNavigationMap({ spring: null });

    map.duoVisit({ spring: null, user: 4890, location: null });
    map.springsSource(4890);
    assert.equal(map.userOverviewNeedsFit, true);
    loadFeatures();
    map.featuresLoadEnd();
    assert.equal(map.view.getZoom(), 8);

    map.duoVisit({ spring: null, user: 4891, location: null });
    map.springsSource(4891);
    map.duoVisit({ spring: 1392969, user: 4891, location: null });
    map.view.setZoom(14);
    loadFeatures();
    map.featuresLoadEnd();
    assert.equal(map.view.getZoom(), 14);
    assert.equal(map.userOverviewNeedsFit, false);
});

test('a user location workflow fits the overview only after leaving location mode', () => {
    const { map } = userNavigationMap({ spring: null });

    map.duoVisit({ spring: null, user: 4889, location: 1 });
    map.springsSource(4889);
    map.featuresLoadEnd();
    assert.equal(map.view.getZoom(), 14);

    map.duoVisit({ spring: null, user: 4889, location: null });
    map.springsSource(4889);
    assert.equal(map.view.getZoom(), 8);
});

const aggregateLayers = ['springsApproximatedLayer', 'springsDistantLayer', 'wateredSpringsApproximatedLayer', 'wateredSpringsDistantLayer'];

function sourceRefreshMap(user = null) {
    const source = new SpringsFinalSource();
    source.addFeature(new Feature({ geometry: new Point([100, 200]), id: 1 }));
    const userSource = new SpringsUserSource();
    userSource.setUser(user);
    userSource.cache.set(17, { stale: true });
    const highlighted = source.getFeatures()[0];
    const map = Object.assign(Object.create(OpenLayersMap.prototype), {
        disposed: false,
        queryParameters: { spring: 1, user, location: null },
        userOverviewNeedsFit: true,
        filters: { spring: false, with_reports: true },
        overlays: { osmTraces: true },
        sourceState: { name: 'terrain' },
        trackLayer: { geometry: ['current track'] },
        buffer: { geometry: ['current buffer'] },
        sharedTrack: { hash: 'a'.repeat(64), token: 'AbCd123456', status: 'saved' },
        view: new View({ center: [100, 200], zoom: 12 }),
        springsFinalSource: source,
        springsUserSource: userSource,
        springsFinalLayer: new VectorLayer({ source: user ? userSource : source }),
        reportCoordinates: { 1: [37, 55] },
        reportSelectionFeature: new Feature(new Point([100, 200])),
        previouslyHighlightedFeature: highlighted,
        featureIdToBeSelected: null,
        featureLoads: 0,
        featuresLoadEnd() { this.featureLoads++; },
    });
    for (const name of aggregateLayers) {
        map[name] = new VectorLayer({
            source: new VectorSource({ features: [new Feature(new Point([100, 200]))] }),
            visible: false,
        });
    }
    return map;
}

for (const user of [null, 17]) {
    test(`mutation refresh replaces stale source data in ${user ? 'user' : 'global'} mode while preserving the live map`, () => {
        const map = sourceRefreshMap(user);
        const previousFinal = map.springsFinalSource;
        const previousAggregates = aggregateLayers.map(name => map[name].getSource());
        const layers = aggregateLayers.map(name => map[name]);
        const stable = Object.fromEntries(['filters', 'overlays', 'sourceState', 'trackLayer', 'buffer', 'sharedTrack', 'view', 'springsUserSource'].map(name => [name, map[name]]));

        map.refreshSpringData();

        assert.notEqual(map.springsFinalSource, previousFinal);
        assert.equal(previousFinal.disposed, true);
        assert.deepEqual(map.springsFinalSource.getFeatures(), []);
        assert.equal(map.springsFinalLayer.getSource(), user ? map.springsUserSource : map.springsFinalSource);
        aggregateLayers.forEach((name, index) => {
            assert.equal(map[name], layers[index]);
            assert.equal(map[name].getVisible(), false);
            assert.notEqual(map[name].getSource(), previousAggregates[index]);
            assert.equal(previousAggregates[index].disposed, true);
            assert.deepEqual(map[name].getSource().getFeatures(), []);
            assert.ok(map[name].getSource().getUrl());
        });
        for (const [name, value] of Object.entries(stable)) assert.equal(map[name], value, `${name} remains the live object`);
        assert.deepEqual(map.view.getCenter(), [100, 200]);
        assert.equal(map.view.getZoom(), 12);
        assert.equal(map.springsUserSource.getUser(), user);
        assert.equal(map.springsUserSource.cache.size, 0);
        assert.equal(map.userOverviewNeedsFit, true);
        assert.equal(map.previouslyHighlightedFeature, null);
        assert.equal(map.featureIdToBeSelected, 1);
        assert.equal(map.reportSelectionFeature.getGeometry(), null);
        assert.deepEqual(map.reportCoordinates, {});
    });
}

test('tile responses started before a mutation cannot reintroduce stale markers into the refreshed map', t => {
    const previousRequest = globalThis.XMLHttpRequest;
    const requests = [];
    globalThis.XMLHttpRequest = class {
        open() {}
        send() { requests.push(this); }
        complete(id) {
            this.status = 200;
            this.responseText = JSON.stringify({
                type: 'FeatureCollection',
                features: [{ type: 'Feature', id, properties: { id }, geometry: { type: 'Point', coordinates: [37, 55] } }],
            });
            this.onload();
        }
    };
    t.after(() => { globalThis.XMLHttpRequest = previousRequest; });
    const map = sourceRefreshMap();
    const oldSource = map.springsFinalSource;
    const load = source => source.loadFeatures([1, 1, 2, 2], 1, getProjection('EPSG:3857'));
    load(oldSource);
    map.refreshSpringData();
    load(map.springsFinalSource);
    assert.equal(requests.length, 2);
    requests[1].complete('updated-location');
    requests[0].complete('old-location');

    assert.deepEqual(map.springsFinalLayer.getSource().getFeatures().map(feature => feature.getId()), ['updated-location']);
    assert.equal(map.featureLoads, 1);
});


test('only the latest selected GPX file may replace the displayed track', t => {
    const previousReader = globalThis.FileReader;
    const readers = [];
    globalThis.FileReader = class { constructor() { readers.push(this); } readAsText() {} };
    t.after(() => { globalThis.FileReader = previousReader; });
    const loaded = [];
    const map = Object.assign(Object.create(OpenLayersMap.prototype), {
        disposed: false, trackImportGeneration: 0,
        trackLayer: { load: (content, metadata) => loaded.push({ content, metadata }) },
    });
    map.upload({ type: 'application/gpx+xml', name: 'First.gpx' });
    map.upload({ type: 'application/gpx+xml', name: 'Coastal walk.gpx' });
    readers[1].onload({ target: { result: '<gpx>latest</gpx>' } });
    readers[0].onload({ target: { result: '<gpx>old</gpx>' } });
    assert.deepEqual(loaded, [{ content: '<gpx>latest</gpx>', metadata: { name: 'Coastal walk' } }]);
});

test('confirmed track deletion disables Along track so source markers remain visible', t => {
    const previousWindow = globalThis.window;
    globalThis.window = new EventTarget();
    t.after(() => { globalThis.window = previousWindow; });
    let styled = 0, filterEvents = 0;
    window.addEventListener('map-filters-changed', () => { filterEvents++; });
    const map = Object.assign(Object.create(OpenLayersMap.prototype), {
        sharedTrack: { status: 'loading', token: 'AbCd123456' }, filters: { along: true },
        updateFilterStyles() { styled++; },
    });
    map.trackPersistenceChanged();
    assert.equal(map.filters.along, true, 'pending downloads retain the requested filter');
    map.sharedTrack.status = 'failed';
    map.trackPersistenceChanged();
    assert.equal(map.filters.along, true, 'temporary errors keep the intended filter for retry');
    map.sharedTrack.status = 'missing';
    map.trackPersistenceChanged();
    assert.equal(map.filters.along, false);
    assert.equal(styled, 1);
    assert.equal(filterEvents, 1);
});
