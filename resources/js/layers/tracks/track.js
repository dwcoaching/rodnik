import { Vector as VectorLayer } from 'ol/layer';
import style from '@/styles/track.js';
import TrackSource from '@/sources/track.js';

export default class TrackLayer extends VectorLayer {
    constructor() {
        super({
            style: style,
            source: new TrackSource(),
            zIndex: 400,
        })

        this.isUploaded = Alpine.reactive({value: this.isUploaded()})
    }

    restoreFromLocalStorage() {
        const content = localStorage.getItem('uploadedGPXTrack')
        if (content) {
            this.getSource().setFromGPXString(content)
            this.isUploaded.value = true
        }
    }

    clearFromLocalStorage() {
        localStorage.removeItem('uploadedGPXTrack')
    }

    clear() {
        this.getSource().clear()
        this.clearFromLocalStorage()
        this.isUploaded.value = false
    }

    isUploaded() {
        return this.getSource().getFeatures().length > 0
    }

    load(content) {
        this.getSource().setFromGPXString(content)

        if (this.getSource().getFeatures().length) {
            window.rodnikMap.view.fit(this.getSource().getExtent())
            window.rodnikMap.view.setZoom(window.rodnikMap.view.getZoom() - 0.5);

            // window.rodnikMap.filters.along = true

            localStorage.setItem('uploadedGPXTrack', content)
            this.isUploaded.value = true
        } else {
            this.clear();
        }
    }
}
