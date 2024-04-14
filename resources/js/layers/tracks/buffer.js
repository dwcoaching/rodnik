import { Vector as VectorLayer } from 'ol/layer';
import style from '@/styles/buffer.js';
import BufferSource from '@/sources/buffer.js';

export default class BufferLayer extends VectorLayer {
    constructor() {
        super({
            style: style,
            source: new BufferSource(),
            zIndex: 300,
        })
    }
}
