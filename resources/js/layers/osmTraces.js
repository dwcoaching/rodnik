import { Tile as TileLayer } from 'ol/layer';
import { XYZ } from 'ol/source';

export default class OSMTracesLayer extends TileLayer {
    constructor() {
        super({
            source: new XYZ({
                url:
                    'https://gps-{a-c}.tile.openstreetmap.org/lines/{z}/{x}/{y}.png'
            }),
            zIndex: 10,
        });
    }
}
