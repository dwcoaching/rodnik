import { booleanPointInPolygon } from '@turf/turf';
import { point } from '@turf/helpers';

export default (feature) => {
    if (! window.rodnikMap.filters.spring && feature.get('type') == 'Spring') {
        return false
    }

    if (! window.rodnikMap.filters.water_well && feature.get('type') == 'Water well') {
        return false
    }

    if (! window.rodnikMap.filters.water_tap && feature.get('type') == 'Water tap') {
        return false
    }

    if (! window.rodnikMap.filters.drinking_water && feature.get('type') == 'Drinking water source') {
        return false
    }

    if (! window.rodnikMap.filters.fountain && feature.get('type') == 'Fountain') {
        return false
    }

    if (! window.rodnikMap.filters.other && feature.get('type') == 'Water source') {
        return false
    }

    if (window.rodnikMap.filters.with_reports && !(feature.get('hasReports') > 0)) {
        return false
    }

    if (window.rodnikMap.filters.along && ! window.rodnikMap.buffer.buffer) {
        return false
    }

    if (window.rodnikMap.filters.along
            && window.rodnikMap.trackLayer.getSource().getFeatures().length
            && window.rodnikMap.buffer.buffer) {

        var transformedCoordinates = feature.getGeometry().clone().transform('EPSG:3857', 'EPSG:4326').getCoordinates();
        var turfPoint = point(transformedCoordinates);

        return booleanPointInPolygon(turfPoint, window.rodnikMap.buffer.buffer)
        // buffered polygon approach is at least 20 times faster than using pointToLineDistance
    }

    return true
}
