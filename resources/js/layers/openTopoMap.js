import { Tile as TileLayer } from 'ol/layer';
import { XYZ } from 'ol/source';

export default class OpenTopoMap extends TileLayer {
    constructor() {
        super({
            source: new XYZ({
                url: 'https://{a-c}.tile.opentopomap.org/{z}/{x}/{y}.png'
            }),
            zIndex: 1,
        });
    }
}
