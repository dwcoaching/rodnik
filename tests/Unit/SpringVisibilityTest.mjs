import assert from 'node:assert/strict';
import { registerHooks } from 'node:module';
import test from 'node:test';
import Feature from 'ol/Feature.js';
import Point from 'ol/geom/Point.js';
import { fromLonLat } from 'ol/proj.js';
import visible from '../../resources/js/filters/visible.js';
import { sharedMapFilters } from '../../resources/js/sharedMapState.js';

const imports = registerHooks({
    resolve(specifier, context, nextResolve) {
        if (specifier.startsWith('@/')) {
            return nextResolve(new URL(`../../resources/js/${specifier.slice(2)}`, import.meta.url).href, context);
        }
        if (specifier.startsWith('ol/') && !specifier.endsWith('.js')) return nextResolve(`${specifier}.js`, context);
        return nextResolve(specifier, context);
    },
});
const styles = Object.fromEntries(await Promise.all(['distant', 'approximated', 'final', 'selected'].map(async name => [
    name, (await import(`../../resources/js/styles/${name}.js`)).default,
])));
imports.deregister();

function setup(t, filters = {}) {
    const previousWindow = globalThis.window;
    const map = {
        filters: sharedMapFilters(filters),
        buffer: { buffer: null },
        trackLayer: { getSource: () => ({ getFeatures: () => [{}] }) },
    };
    globalThis.window = { rodnikMap: map };
    t.after(() => { globalThis.window = previousWindow; });
    return map;
}

const spring = properties => new Feature({
    type: 'Spring', hasReports: 0, score: 0,
    geometry: new Point(fromLonLat([1, 1])), ...properties,
});

const isDrawn = style => (Array.isArray(style) ? style : [style]).some(item => item.getImage() || item.getText());

test('report presence accepts every water assessment and excludes sources without reports', t => {
    const map = setup(t, { with_reports: true });

    for (const score of [-2, 0, 2]) {
        assert.equal(visible(spring({ hasReports: 1, score })), true);
    }
    assert.equal(visible(spring({ hasReports: 3, notFound: true })), true);
    for (const hasReports of [0, null, undefined, -1]) {
        assert.equal(visible(spring({ hasReports })), false);
    }

    map.filters.with_reports = false;
    assert.equal(visible(spring()), true);
});

test('report presence composes with source types and the track polygon', t => {
    const map = setup(t, { with_reports: true, spring: false, along: true });
    const feature = spring({ type: 'Water well', hasReports: 1 });
    assert.equal(visible(feature), false, 'a missing track must keep the track filter restrictive');

    map.buffer.buffer = {
        type: 'Feature', properties: {}, geometry: {
            type: 'Polygon', coordinates: [[[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]]],
        },
    };
    assert.equal(visible(feature), true);
    assert.equal(visible(spring({ hasReports: 1 })), false, 'disabled spring type remains hidden');
    assert.equal(visible(spring({ type: 'Water well' })), false, 'an in-track source still needs reports');
    feature.setGeometry(new Point(fromLonLat([3, 3])));
    assert.equal(visible(feature), false);
});

test('report filtering hides blue sources at every zoom level and in the selected source style', t => {
    const map = setup(t, { with_reports: true });

    for (const [name, style] of Object.entries(styles)) {
        assert.equal(isDrawn(style(spring())), false, `${name} must hide unreported sources`);
        for (const score of [-2, 0, 2]) {
            assert.equal(isDrawn(style(spring({ hasReports: 1, score }))), true, `${name} must show reported score ${score}`);
        }
    }

    map.filters.with_reports = false;
    for (const [name, style] of Object.entries(styles)) {
        assert.equal(isDrawn(style(spring())), true, `${name} must restore unreported sources when disabled`);
    }
});
