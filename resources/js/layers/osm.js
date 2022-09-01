import { Tile as TileLayer } from 'ol/layer';
import { OSM } from 'ol/source';

export default class OSMLayer extends TileLayer {
    constructor() {
        super({
            source: new OSM(),
            zIndex: 1,
        });
    }
}
