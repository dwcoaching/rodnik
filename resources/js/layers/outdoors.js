import { Tile as TileLayer } from 'ol/layer';
import { XYZ } from 'ol/source';

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
