import VectorSource from 'ol/source/Vector'
import GeoJSON from 'ol/format/GeoJSON'
import { multiLineString } from '@turf/helpers';
import { buffer, simplify } from '@turf/turf';

export default class BufferSource extends VectorSource {
    constructor() {
        super({
            format: new GeoJSON(),
        })
    }

    setFromTurf(turf) {
        this.clear()
        const features = this.createFeatures(turf)

        if (features.length) {
            this.addFeatures(features)
        } else {
//            alert('Please upload a GPX file')
        }
    }

    createFeatures(turf) {
        const features = (new GeoJSON()).readFeatures(turf, {
            dataProjection: 'EPSG:4326',
            featureProjection: 'EPSG:3857'
        })

        return features
    }
}
