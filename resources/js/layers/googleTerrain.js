import { Tile as TileLayer } from 'ol/layer';
import { XYZ } from 'ol/source';

export default class GoogleTerrain extends TileLayer {
    constructor() {
        super({
            source: new XYZ({
                url: 'https://mt0.google.com/vt/lyrs=p&hl=en&x={x}&y={y}&z={z}'
            }),
            zIndex: 1,
        });
    }
}
