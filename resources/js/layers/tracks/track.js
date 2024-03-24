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
    }
}
