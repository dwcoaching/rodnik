import VectorSource from 'ol/source/Vector'
import GPX from 'ol/format/GPX'
import { multiLineString } from '@turf/helpers';
import { buffer, simplify } from '@turf/turf';

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
            const t1 = performance.now();
            var transformedCoordinates = window.rodnikMap.trackLayer.getSource().getFeatures()[0].getGeometry().clone().transform('EPSG:3857', 'EPSG:4326').getCoordinates();
            window.turfMultiLineString = simplify(multiLineString(transformedCoordinates), {
                tolerance: 0.01,
                highQuality: false
            })
            //window.turfMultiLineString = multiLineString(transformedCoordinates)

            window.turfBuffered = simplify(buffer(window.turfMultiLineString, 1000, {
                units: 'meters',
                steps: 8
            }),
            {
                tolerance: 0.005,
                highQuality: false,
                mutate: true,
            })
            //window.turfBuffered = buffer(window.turfMultiLineString, 1000, {units: 'meters'})
            window.rodnikMap.bufferLayer.getSource().setFromTurf(turfBuffered)
            window.visibletimer = 0;
            const t2 = performance.now();
            console.log(t2 - t1)
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
