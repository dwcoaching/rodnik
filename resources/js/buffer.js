import { bbox, buffer, simplify, featureCollection, union } from '@turf/turf';
import GeoJSON from 'ol/format/GeoJSON';
import TrackPolygons from './trackPolygons.js';

export default class Buffer {
    constructor() {
        this.trackPolygon = new TrackPolygons()
        this.revision = 0
        this.clear(false)
    }

    clear(notify = true) {
        this.revision++
        this.trackPolygon.clear()
        this.persistencePromise = null
        this.track = featureCollection([])
        this.trackSimplified = featureCollection([])
        this.buffer = null

        if (notify) this.notifyTrackChange()
    }

    setTrack(features) {
        this.clear(false)

        this.track = (new GeoJSON()).writeFeaturesObject(window.rodnikMap.trackLayer.getSource().getFeatures(), {
            dataProjection: 'EPSG:4326',
            featureProjection: 'EPSG:3857'
         })

        this.makeSimplifiedTrack()
        this.makeBuffer()
        this.saveTrackPolygon()
        this.notifyTrackChange()

        if (window.rodnikMap.debug) {
            window.rodnikMap.trackSimplifiedLayer.getSource().setFromTurf(this.trackSimplified)
            window.rodnikMap.bufferLayer.getSource().setFromTurf(this.buffer)
        }
    }

    saveTrackPolygon() {
        if (!this.buffer?.geometry) {
            this.trackPolygon.clear()
            return Promise.resolve(null)
        }

        const pending = this.trackPolygon.save(this.buffer)

        if (pending !== this.persistencePromise) {
            const revision = this.revision
            const status = this.trackPolygon.status
            this.persistencePromise = pending
            this.notifyPolygonStateChange()
            pending.then(() => {
                if (status === 'saving'
                    && revision === this.revision
                    && pending === this.persistencePromise
                    && pending === this.trackPolygon.promise) {
                    this.notifyPolygonStateChange()
                }
            })
        }

        return pending
    }

    notifyTrackChange() {
        window.rodnikMap.updateFilterStyles?.()
        window.dispatchEvent(new CustomEvent('map-track-changed'))
    }

    notifyPolygonStateChange() {
        window.dispatchEvent(new CustomEvent('map-track-polygon-state-changed', {
            detail: {
                revision: this.revision,
                status: this.trackPolygon.status,
                hash: this.trackPolygon.hash,
            },
        }))
    }

    filterOutPoints(track) {
        let result = structuredClone(track);
        result.features = result.features.filter((feature) => feature.geometry.type !== 'Point')
        return result;
    }
    

    makeSimplifiedTrack() {
        this.trackSimplified = simplify(this.filterOutPoints(this.track), {
            tolerance: 0.002,
            highQuality: false,
            mutate: true,
        })
    }

    makeBuffer() {
        this.buffer = buffer(this.trackSimplified, 500, {
            units: 'meters',
            steps: 8,
        })

        this.buffer = simplify(this.buffer, {
            tolerance: 0.0005,
            highQuality: false,
            mutate: true,
        })

        // Union is slow when there are 1000s of individual points,
        // But it is required later to check whether the point is inside the polygon

        if (this.buffer.features.length > 0) {
            this.buffer = this.buffer.features.reduce((joined, feature) => {
                return union(joined, feature)
            })

            // Turf rejects points outside this cached bbox before scanning polygon rings.
            this.buffer.bbox = bbox(this.buffer)
        }
    }
}
