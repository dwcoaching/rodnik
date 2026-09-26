import GeoJSON from 'ol/format/GeoJSON.js';

const snapshots = new WeakMap();

function freeze(value) {
    if (value && typeof value === 'object' && !Object.isFrozen(value)) {
        Object.values(value).forEach(freeze);
        Object.freeze(value);
    }
    return value;
}

function preserveTimeCoordinates(serialized, geometry) {
    if (!serialized || !geometry) return;
    if (geometry.getType() === 'GeometryCollection') {
        geometry.getGeometries().forEach((child, index) => preserveTimeCoordinates(serialized.geometries[index], child));
    } else if (geometry.getLayout() === 'XYM') {
        const withMissingElevation = coordinates => typeof coordinates[0] === 'number'
            ? [coordinates[0], coordinates[1], null, coordinates[2]] : coordinates.map(withMissingElevation);
        serialized.coordinates = withMissingElevation(serialized.coordinates);
    }
}

export function trackGeoJson(features, projection) {
    const track = new GeoJSON().writeFeaturesObject(features, {
        dataProjection: 'EPSG:4326',
        featureProjection: projection,
    });
    track.features.forEach((feature, index) => preserveTimeCoordinates(feature.geometry, features[index].getGeometry()));
    return track;
}

export function captureTrackNavigationState(source, projection) {
    const revision = source.getRevision();
    const previous = snapshots.get(source);
    if (previous?.revision === revision && previous.projection === projection) return previous.track;

    const track = freeze(structuredClone(trackGeoJson(source.getFeatures(), projection)));
    snapshots.set(source, { revision, projection, track });
    return track;
}
