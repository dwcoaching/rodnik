import { pointToLineDistance, booleanPointInPolygon } from '@turf/turf';
import { point, lineString, multiLineString } from '@turf/helpers';
import GeoJSON from 'ol/format/GeoJSON';
import { transform } from 'ol/proj';

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

    if (window.rodnikMap.filters.confirmed && ! feature.get('waterConfirmed')) {
        return false
    }

    if (window.rodnikMap.trackLayer.getSource().getFeatures().length) {
        var transformedCoordinates = feature.getGeometry().clone().transform('EPSG:3857', 'EPSG:4326').getCoordinates();
        var turfPoint = point(transformedCoordinates);

        const t1 = performance.now()
        const a = booleanPointInPolygon(turfPoint, window.turfBuffered)
        const t2 = performance.now()
        window.visibletimer += t2 - t1
        console.log(window.visibletimer)
        return a

        /**
        const t1 = performance.now()

        let closestDistance = Infinity; // Start with a very high value to ensure any real distance is smaller

        // Loop through each LineString in the MultiLineString
        window.turfMultiLineString.geometry.coordinates.forEach(line => {
          const turfLineString = lineString(line); // Convert each line to a LineString feature
          const distance = pointToLineDistance(turfPoint, turfLineString, {units: 'meters'}); // Calculate distance
          if (distance < closestDistance) {
            closestDistance = distance; // Update closest distance if current distance is smaller
          }
        });

        let a = true
        //console.log(closestDistance)
        if (closestDistance > 1000) {
            a = false
        }

        const t2 = performance.now()
        window.visibletimer += t2 - t1
        console.log(window.visibletimer)
        return a
        **/
    }

    return true
}
