import { Tile as TileLayer } from 'ol/layer';
import { XYZ } from 'ol/source';

export default class Outdoors extends TileLayer {
    constructor() {
        super({
            source: new XYZ({
                url: 'https://{a-c}.tile.thunderforest.com/outdoors/{z}/{x}/{y}.png'
            }),
            zIndex: 1,
        });
    }
}
