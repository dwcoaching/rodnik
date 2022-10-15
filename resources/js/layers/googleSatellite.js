import { Tile as TileLayer } from 'ol/layer';
import { XYZ } from 'ol/source';

export default class GoogleSatellite extends TileLayer {
    constructor() {
        super({
            source: new XYZ({
                url:
                    'https://mt{0-3}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}'
            }),
            zIndex: 1,
        });
    }
}
