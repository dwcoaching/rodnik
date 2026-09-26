import assert from 'node:assert/strict';
import test from 'node:test';
import Feature from 'ol/Feature.js';
import LineString from 'ol/geom/LineString.js';
import GeoJSON from 'ol/format/GeoJSON.js';
import GeometryCollection from 'ol/geom/GeometryCollection.js';
import Point from 'ol/geom/Point.js';
import VectorSource from 'ol/source/Vector.js';
import { captureTrackNavigationState, trackGeoJson } from '../../resources/js/trackNavigationState.js';

const makeTrack = () => new Feature({
    geometry: new LineString([[1000000, 5000000], [1001000, 5001000]]),
    links: [{ href: 'https://example.com/track', text: 'Original track' }],
});

test('unchanged tracks reuse the same deeply immutable snapshot without rereading their features', () => {
    const feature = makeTrack();
    const source = new VectorSource({ features: [feature] });
    const originalRead = source.getFeatures.bind(source);
    let reads = 0;
    source.getFeatures = () => { reads++; return originalRead(); };

    const first = captureTrackNavigationState(source, 'EPSG:3857');
    const second = captureTrackNavigationState(source, 'EPSG:3857');

    assert.equal(first, second);
    assert.equal(reads, 1);
    assert.throws(() => { first.features[0].geometry.coordinates[0][0] = 0; }, TypeError);
    assert.throws(() => { first.features[0].properties.links[0].text = 'Changed'; }, TypeError);
    assert.equal(Object.isFrozen(feature.get('links')), false);
});

test('geometry edits and clearing the actual source invalidate its cached snapshot', () => {
    const feature = makeTrack();
    const source = new VectorSource({ features: [feature] });
    const first = captureTrackNavigationState(source, 'EPSG:3857');
    const originalCoordinates = structuredClone(first.features[0].geometry.coordinates);

    feature.getGeometry().setCoordinates([[2000000, 6000000], [2001000, 6001000]]);
    const changed = captureTrackNavigationState(source, 'EPSG:3857');
    assert.notEqual(first, changed);
    assert.notDeepEqual(changed.features[0].geometry.coordinates, originalCoordinates);
    assert.deepEqual(first.features[0].geometry.coordinates, originalCoordinates);

    source.clear();
    const cleared = captureTrackNavigationState(source, 'EPSG:3857');
    assert.notEqual(changed, cleared);
    assert.deepEqual(cleared.features, []);
    assert.equal(captureTrackNavigationState(source, 'EPSG:3857'), cleared);
});

test('sources and feature projections have independent snapshots', () => {
    const firstSource = new VectorSource({ features: [makeTrack()] });
    const secondSource = new VectorSource({ features: [makeTrack()] });
    const projected = captureTrackNavigationState(firstSource, 'EPSG:3857');
    const geographic = captureTrackNavigationState(firstSource, 'EPSG:4326');

    assert.notEqual(projected, captureTrackNavigationState(secondSource, 'EPSG:3857'));
    assert.notEqual(projected, geographic);
    assert.deepEqual(geographic.features[0].geometry.coordinates[0], [1000000, 5000000]);
    assert.notDeepEqual(projected.features[0].geometry.coordinates[0], [1000000, 5000000]);
});

test('GPX timestamps without elevation survive GeoJSON upload and navigation snapshots', () => {
    const coordinates = [[30, 40, 1750000000], [31, 41, 1750000060]];
    const feature = new Feature({ name: 'Timed route', geometry: new LineString(coordinates, 'XYM') });
    const source = new VectorSource({ features: [feature] });
    const uploaded = trackGeoJson(source.getFeatures(), 'EPSG:4326');
    const expected = [[30, 40, null, 1750000000], [31, 41, null, 1750000060]];
    assert.deepEqual(uploaded.features[0].geometry.coordinates, expected);
    assert.deepEqual(feature.getGeometry().getCoordinates(), coordinates);
    assert.equal(feature.getGeometry().getLayout(), 'XYM');

    const snapshot = captureTrackNavigationState(source, 'EPSG:4326');
    assert.deepEqual(snapshot, uploaded);
    const restored = new GeoJSON().readFeatures(JSON.stringify(snapshot));
    assert.equal(restored[0].getGeometry().getLayout(), 'XYZM');
    assert.deepEqual(restored[0].getGeometry().getCoordinates(), expected);
    assert.deepEqual(trackGeoJson(restored, 'EPSG:4326'), uploaded);
});

test('mixed waypoint and route layouts keep timestamps separate from elevations', () => {
    const feature = new Feature(new GeometryCollection([
        new Point([30, 40, 1750000000], 'XYM'),
        new LineString([[30, 40, 123], [31, 41, 124]], 'XYZ'),
        new LineString([[30, 40, 123, 1750000000], [31, 41, 124, 1750000060]], 'XYZM'),
    ]));
    const geometries = trackGeoJson([feature], 'EPSG:4326').features[0].geometry.geometries;
    assert.deepEqual(geometries[0].coordinates, [30, 40, null, 1750000000]);
    assert.deepEqual(geometries[1].coordinates, [[30, 40, 123], [31, 41, 124]]);
    assert.deepEqual(geometries[2].coordinates, [[30, 40, 123, 1750000000], [31, 41, 124, 1750000060]]);
});
