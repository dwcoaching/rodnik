import VectorSource from 'ol/source/Vector'
import GPX from 'ol/format/GPX'
import trans from '@/i18n'

export default class TrackSource extends VectorSource {
    constructor() {
        super({
            format: new GPX(),
        })
    }

    setFromGPXString(string, { name = null } = {}) {
        const features = this.createFeatures(string)

        if (features.length) {
            this.clear()
            this.addFeatures(features)
            window.rodnikMap.trackLayer.isUploaded.value = true;
            window.rodnikMap.trackChanged?.(name);
            window.rodnikMap.buffer.setTrack(features)
            return true;
        } else {
            alert(trans('please_upload_gpx', 'Please upload a GPX file'))
            return false;
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
