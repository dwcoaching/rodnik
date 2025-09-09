import { buffer, simplify, featureCollection, union } from '@turf/turf';
import GeoJSON from 'ol/format/GeoJSON';

export default class Buffer {
    constructor() {
        this.clear()
    }

    clear() {
        this.track = featureCollection([])
        this.trackSimplified = featureCollection([])
        this.buffer = null
    }

    setTrack(features) {
        this.clear()

        this.track = (new GeoJSON()).writeFeaturesObject(window.rodnikMap.trackLayer.getSource().getFeatures(), {
            dataProjection: 'EPSG:4326',
            featureProjection: 'EPSG:3857'
         })

        this.makeSimplifiedTrack()
        this.makeBuffer()

        if (window.rodnikMap.debug) {
            window.rodnikMap.trackSimplifiedLayer.getSource().setFromTurf(this.trackSimplified)
            window.rodnikMap.bufferLayer.getSource().setFromTurf(this.buffer)
        }
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

        if (this.buffer.features.length > 1) {
            this.buffer = this.buffer.features.reduce((joined, feature) => {
                return union(joined, feature)
            })
        }
    }
}
