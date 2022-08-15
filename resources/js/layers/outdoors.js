import { Tile as TileLayer } from 'ol/src/layer';
import { XYZ } from 'ol/src/source';

export default class Mapy extends TileLayer {
    constructor() {
        super({
            source: new XYZ({
                url: 'https://{a-d}.tile.thunderforest.com/outdoors/{z}/{x}/{y}.png'
            }),
            zIndex: 1,
        });
    }
}
