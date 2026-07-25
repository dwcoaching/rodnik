import VectorSource from 'ol/source/Vector'
import GPX from 'ol/format/GPX'
import trans from '@/i18n'

export default class TrackSource extends VectorSource {
    constructor() {
        super({
            format: new GPX(),
        })
    }

    setFromGPXString(string) {
        const features = this.createFeatures(string)

        if (features.length) {
            this.clear()
            this.addFeatures(features)
            window.rodnikMap.buffer.setTrack(features)
        } else {
            alert(trans('please_upload_gpx', 'Please upload a GPX file'))
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
