import VectorSource from 'ol/source/Vector'
import GPX from 'ol/format/GPX'
import { multiLineString } from '@turf/helpers';

export default class TrackSource extends VectorSource {
    constructor() {
        super({
            format: new GPX(),
        })
    }

    setFromGPXString(string) {
        this.clear()
        const features = this.createFeatures(string)

        if (features.length) {
            this.addFeatures(features)


            var transformedCoordinates = window.rodnikMap.trackLayer.getSource().getFeatures()[0].getGeometry().clone().transform('EPSG:3857', 'EPSG:4326').getCoordinates();
            window.turfMultiLineString = multiLineString(transformedCoordinates);
        } else {
            alert('Please upload a GPX file')
        }
    }

    createFeatures(string) {
        const features = (new GPX()).readFeatures(string, {
            dataProjection: 'EPSG:4326',
            featureProjection: 'EPSG:3857'
        })

        return features
    }
}
