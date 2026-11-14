import assert from 'node:assert/strict';
import { registerHooks } from 'node:module';
import test from 'node:test';
import { inView } from 'ol/layer/Layer.js';
import SpringsRasterLayer from '../../resources/js/layers/springs/raster.js';
import {
    normalizeOverviewManifest, normalizeSourceOpacity, readSourceOpacity, saveSourceOpacity,
    sourceOverviewUrl, sourceTypeMask,
} from '../../resources/js/sourceOverview.js';

const browserImports = registerHooks({
    resolve(specifier, context, nextResolve) {
        if (specifier === 'exifr') return nextResolve('exifr/dist/full.esm.mjs', context);
        if (specifier.startsWith('@/')) {
            const path = specifier.slice(2).replace(/(?:\.js)?$/, '.js');
            return nextResolve(new URL(`../../resources/js/${path}`, import.meta.url).href, context);
        }
        if (specifier.startsWith('ol/') && !specifier.endsWith('.js')) return nextResolve(`${specifier}.js`, context);
        return nextResolve(specifier, context);
    },
});
const { default: OpenLayersMap } = await import('../../resources/js/openLayers.js');
browserImports.deregister();

const manifest = {
    revision: 'a'.repeat(24), max_zoom: 10, tile_size: 512, tile_pixel_ratio: 2,
    url: `/source-tiles/${'a'.repeat(24)}/{z}/{x}/{y}.png`,
};
const aggregateNames = ['springsApproximatedLayer', 'springsDistantLayer', 'wateredSpringsApproximatedLayer', 'wateredSpringsDistantLayer'];
const layer = () => ({
    visible: true, minZoom: 9, maxZoom: 9,
    setVisible(value) { this.visible = value; },
    setMinZoom(value) { this.minZoom = value; },
    setMaxZoom(value) { this.maxZoom = value; },
});

function overviewMap(t) {
    const map = Object.assign(Object.create(OpenLayersMap.prototype), {
        disposed: false, overviewRequestGeneration: 0, overviewManifest: null,
        queryParameters: { user: null }, filters: {}, buffer: {},
        sourceOverview: { ready: false, active: false, opacity: 0.85 },
        springsRasterLayer: new SpringsRasterLayer(),
        springsFinalLayer: layer(), reportSelectionLayer: layer(),
    });
    for (const name of aggregateNames) map[name] = layer();
    t.after(() => {
        clearTimeout(map.overviewRetryTimer);
        map.overviewRequest?.abort();
        map.springsRasterLayer.dispose();
    });
    return map;
}

test('source filters preserve each type, reports and route selection in raster URLs', () => {
    assert.equal(sourceTypeMask({}), 63);
    assert.equal(sourceTypeMask({ spring: false, water_tap: false, other: false }), 26);
    assert.equal(sourceTypeMask(Object.fromEntries(['spring', 'water_well', 'water_tap', 'drinking_water', 'fountain', 'other'].map(key => [key, false]))), 0);
    const url = sourceOverviewUrl(manifest, { water_well: false, with_reports: true, along: true }, 'b'.repeat(64));
    assert.equal(url, `${manifest.url}?types=61&reports=1&polygon=${'b'.repeat(64)}`);
    assert.equal(sourceOverviewUrl(manifest, { along: true }), null);
    assert.equal(sourceOverviewUrl(manifest, { along: true }, 'invalid'), null);
    assert.equal(sourceOverviewUrl(manifest, {}, 'b'.repeat(64)), `${manifest.url}?types=63`);
});

test('invalid or external raster manifests cannot replace a usable map', () => {
    assert.deepEqual(normalizeOverviewManifest(manifest), manifest);
    for (const invalid of [null, {}, { ...manifest, max_zoom: -1 }, { ...manifest, tile_pixel_ratio: 0 },
        { ...manifest, url: '//example.com/{z}/{x}/{y}.png' }, { ...manifest, url: '/tiles.png' }]) {
        assert.equal(normalizeOverviewManifest(invalid), null);
    }
});

test('retina tiles retain a 256 CSS pixel grid and render at world zoom through zoom 10', t => {
    const raster = new SpringsRasterLayer();
    t.after(() => raster.dispose());
    raster.setOverview(manifest, {});
    raster.setVisible(true);
    const source = raster.getSource();
    assert.equal(source.getTileGrid().getTileSize(0), 256);
    assert.equal(source.getTilePixelRatio(1), 2);
    assert.equal(source.getTileGrid().getMaxZoom(), 10);
    assert.equal(inView(raster.getLayerState(), { zoom: 0, resolution: 156543 }), true);
    assert.equal(inView(raster.getLayerState(), { zoom: 10, resolution: 153 }), true);
    assert.equal(inView(raster.getLayerState(), { zoom: 10.01, resolution: 152 }), false);
    assert.equal(source.getUrls()[0], `${manifest.url}?types=63`);
    raster.setOverview(manifest, {});
    assert.equal(raster.getSource(), source, 'unchanged filters retain decoded tiles');
    raster.setOverview(manifest, { with_reports: true });
    assert.notEqual(raster.getSource(), source);
    assert.equal(source.disposed, true);
});

test('overview handover replaces sampled layers, covers two extra zoom levels and retains personal maps', t => {
    const map = overviewMap(t);
    map.updateSourceOverview();
    assert.equal(map.springsFinalLayer.minZoom, 9);
    assert.equal(map.springsRasterLayer.getVisible(), false);
    assert.ok(aggregateNames.every(name => map[name].visible));

    map.overviewManifest = manifest;
    map.updateSourceOverview();
    assert.equal(map.springsRasterLayer.getVisible(), true);
    assert.equal(map.springsFinalLayer.minZoom, 10);
    assert.equal(map.reportSelectionLayer.maxZoom, 10);
    assert.ok(aggregateNames.every(name => !map[name].visible));

    map.queryParameters.user = 17;
    map.updateSourceOverview();
    assert.equal(map.springsRasterLayer.getVisible(), false);
    assert.equal(map.springsFinalLayer.minZoom, 0);
    assert.ok(aggregateNames.every(name => !map[name].visible));
});

test('an Along track filter never displays global raster while its polygon is saving or missing', t => {
    const map = overviewMap(t);
    map.overviewManifest = manifest;
    map.filters.along = true;
    map.buffer = { buffer: {}, trackPolygon: { status: 'saving', hash: null } };
    map.updateSourceOverview();
    assert.equal(map.springsRasterLayer.getVisible(), false);
    assert.ok(aggregateNames.every(name => map[name].visible));
    map.buffer.trackPolygon = { status: 'saved', hash: 'c'.repeat(64) };
    map.updateSourceOverview();
    assert.equal(map.springsRasterLayer.getVisible(), true);
    assert.ok(map.springsRasterLayer.getSource().getUrls()[0].endsWith(`polygon=${'c'.repeat(64)}`));
    map.buffer.buffer = null;
    map.updateSourceOverview();
    assert.equal(map.springsRasterLayer.getVisible(), false);
});

test('only the latest manifest response can change a live map', async t => {
    const previousFetch = globalThis.fetch;
    const requests = [];
    globalThis.fetch = (_url, options) => new Promise(resolve => requests.push({ resolve, options }));
    t.after(() => { globalThis.fetch = previousFetch; });
    const map = overviewMap(t);
    const first = map.refreshSourceOverview();
    const second = map.refreshSourceOverview();
    assert.equal(requests[0].options.signal.aborted, true);
    requests[1].resolve({ ok: true, json: async () => manifest });
    await second;
    requests[0].resolve({ ok: true, json: async () => ({ ...manifest, max_zoom: 8 }) });
    await first;
    assert.equal(map.overviewManifest.max_zoom, 10);
    assert.equal(map.sourceOverview.active, true);
});

test('foreground polling picks up a worker revision after a mutation initially returns the old manifest', async t => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const previousFetch = globalThis.fetch;
    let currentManifest = manifest;
    let requests = 0;
    globalThis.fetch = async () => {
        requests++;
        return { ok: true, json: async () => currentManifest };
    };
    t.after(() => { globalThis.fetch = previousFetch; });
    const map = overviewMap(t);
    await map.refreshSourceOverview();
    const initialSource = map.springsRasterLayer.getSource();
    await map.refreshSourceOverview();
    assert.equal(requests, 2);
    assert.equal(map.springsRasterLayer.getSource(), initialSource, 'unchanged revisions retain decoded tiles');

    // The queued worker publishes after the mutation response has already arrived.
    currentManifest = { ...manifest, revision: 'b'.repeat(24), url: manifest.url.replace('a'.repeat(24), 'b'.repeat(24)) };
    t.mock.timers.tick(59_999);
    assert.equal(requests, 2);
    t.mock.timers.tick(1);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(requests, 3);
    assert.equal(map.overviewManifest.revision, 'b'.repeat(24));
    assert.notEqual(map.springsRasterLayer.getSource(), initialSource);
    assert.equal(initialSource.disposed, true);
    assert.ok(map.overviewRetryTimer, 'successful polling schedules the following refresh');
});

test('polling sleeps in hidden tabs, refreshes on return and stops with the map lifecycle', async t => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const previousFetch = globalThis.fetch;
    const previousDocument = globalThis.document;
    const previousWindow = globalThis.window;
    const previousAlpine = globalThis.Alpine;
    const document = Object.assign(new EventTarget(), { hidden: false });
    globalThis.document = document;
    globalThis.window = new EventTarget();
    globalThis.Alpine = { release() {} };
    let requests = 0;
    globalThis.fetch = async () => {
        requests++;
        return { ok: true, json: async () => manifest };
    };
    t.after(() => {
        globalThis.fetch = previousFetch;
        globalThis.document = previousDocument;
        globalThis.window = previousWindow;
        globalThis.Alpine = previousAlpine;
    });
    const map = overviewMap(t);
    map.overviewVisibilityListener = () => map.sourceOverviewVisibilityChanged();
    document.addEventListener('visibilitychange', map.overviewVisibilityListener);
    await map.refreshSourceOverview();
    document.hidden = true;
    document.dispatchEvent(new Event('visibilitychange'));
    t.mock.timers.tick(120_000);
    await map.refreshSourceOverview();
    assert.equal(requests, 1, 'neither the old timer nor a mutation requests tiles in a hidden tab');

    document.hidden = false;
    document.dispatchEvent(new Event('visibilitychange'));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(requests, 2, 'returning to the tab refreshes immediately');

    Object.assign(map, {
        sharedRestoreGeneration: 0, navigationRestoreGeneration: 0,
        springsFinalSource: { dispose() {} }, springsUserSource: { cancelRequests() {} },
        tracks: { clear() {} }, buffer: { clear() {} }, geolocation: { setTracking() {} },
        map: { setTarget() {}, dispose() {} },
    });
    map.dispose();
    let visibilityCallbacks = 0;
    map.sourceOverviewVisibilityChanged = () => { visibilityCallbacks++; };
    document.dispatchEvent(new Event('visibilitychange'));
    t.mock.timers.tick(120_000);
    assert.equal(visibilityCallbacks, 0, 'disposing removes the document listener');
    assert.equal(requests, 2, 'disposing cancels the successful refresh timer');
});

test('an unavailable build or tile error restores sampled layers and schedules a retry', async t => {
    const previousFetch = globalThis.fetch;
    globalThis.fetch = async () => ({ ok: false, status: 503 });
    t.after(() => { globalThis.fetch = previousFetch; });
    const map = overviewMap(t);
    await map.refreshSourceOverview();
    assert.equal(map.overviewManifest, null);
    assert.ok(map.overviewRetryTimer);
    assert.ok(aggregateNames.every(name => map[name].visible));
    map.overviewManifest = manifest;
    map.updateSourceOverview();
    const failedSource = map.springsRasterLayer.getSource();
    map.sourceOverviewFailed();
    assert.equal(failedSource.disposed, true);
    assert.equal(map.springsRasterLayer.getSource(), null);
    assert.equal(map.springsRasterLayer.getVisible(), false);
    assert.equal(map.springsFinalLayer.minZoom, 9);
    assert.ok(aggregateNames.every(name => map[name].visible));
    map.overviewManifest = manifest;
    map.updateSourceOverview();
    assert.notEqual(map.springsRasterLayer.getSource(), failedSource, 'retrying the same revision reloads failed image tiles');
});

test('opacity persists from transparent to fully opaque and tolerates unavailable storage', t => {
    const previousStorage = globalThis.localStorage;
    const values = new Map();
    globalThis.localStorage = { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) };
    t.after(() => { globalThis.localStorage = previousStorage; });
    assert.equal(readSourceOpacity(), 0.85);
    const map = overviewMap(t);
    map.setSourceOpacity('0');
    assert.equal(map.springsRasterLayer.getOpacity(), 0);
    assert.equal(readSourceOpacity(), 0);
    map.setSourceOpacity('0.67');
    assert.equal(map.sourceOverview.opacity, 0.67);
    assert.equal(readSourceOpacity(), 0.67);
    assert.equal(normalizeSourceOpacity(3), 1);
    assert.equal(normalizeSourceOpacity(-2), 0);
    globalThis.localStorage = { getItem() { throw new Error(); }, setItem() { throw new Error(); } };
    assert.equal(readSourceOpacity(), 0.85);
    assert.doesNotThrow(() => saveSourceOpacity(0.5));
});
