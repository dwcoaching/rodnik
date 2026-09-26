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
        let content;
        try { content = localStorage.getItem('uploadedGPXTrack'); } catch { return; }
        if (content) {
            this.isUploaded.value = this.getSource().setFromGPXString(content) !== false;
        }
    }

    clearFromLocalStorage() {
        try { localStorage.removeItem('uploadedGPXTrack'); } catch { /* Storage may be unavailable. */ }
    }

    clear({ persist = true } = {}) {
        this.getSource().clear()
        window.rodnikMap.tracks?.clear();
        window.rodnikMap.buffer.clear()
        if (persist) this.clearFromLocalStorage()
        this.isUploaded.value = false
        window.rodnikMap.notifySharedStateChange?.();
    }

    isUploaded() {
        return this.getSource().getFeatures().length > 0
    }

    load(content, { name = null } = {}) {
        if (this.getSource().setFromGPXString(content, { name }) === false) return;

        if (this.getSource().getFeatures().length) {
            window.rodnikMap.view.fit(this.getSource().getExtent())
            window.rodnikMap.view.setZoom(window.rodnikMap.view.getZoom() - 0.5);

            // window.rodnikMap.filters.along = true

            this.isUploaded.value = true

            try {
                localStorage.setItem('uploadedGPXTrack', content)
            } catch (error) {
                this.clearFromLocalStorage()
            }
        } else {
            // this.clear();
        }
    }
}
