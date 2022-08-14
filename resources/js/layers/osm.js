import { Tile as TileLayer } from 'ol/src/layer';
import { OSM } from 'ol/src/source';

export default class OSMLayer extends TileLayer {
    constructor() {
        super({
            source: new OSM(),
        });
    }
}
