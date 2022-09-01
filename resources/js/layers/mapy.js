import { Tile as TileLayer } from 'ol/layer';
import { XYZ } from 'ol/source';

export default class Mapy extends TileLayer {
    constructor() {
        super({
            source: new XYZ({
                url:
                    'https://m{1-4}.mapserver.mapy.cz/turist-m/retina/{z}-{x}-{y}',
                tilePixelRatio: 2,
            }),
            zIndex: 1,
        });
    }
}
