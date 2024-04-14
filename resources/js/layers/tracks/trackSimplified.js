import { Vector as VectorLayer } from 'ol/layer';
import style from '@/styles/trackSimplified.js';
import TrackSimplifiedSource from '@/sources/trackSimplified.js';

export default class BufferLayer extends VectorLayer {
    constructor() {
        super({
            style: style,
            source: new TrackSimplifiedSource(),
            zIndex: 300,
        })
    }
}
