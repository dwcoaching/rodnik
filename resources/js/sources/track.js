import VectorSource from 'ol/source/Vector'
import GPX from 'ol/format/GPX'

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
