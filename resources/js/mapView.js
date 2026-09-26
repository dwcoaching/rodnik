import View from 'ol/View.js';
import { get as getProjection } from 'ol/proj.js';

export default function createMapView({ center, zoom }) {
    return new View({
        center,
        zoom,
        enableRotation: false,
        extent: getProjection('EPSG:3857').getExtent(),
        smoothExtentConstraint: false,
        smoothResolutionConstraint: false,
    });
}
