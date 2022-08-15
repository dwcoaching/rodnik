import { Vector as VectorLayer } from 'ol/src/layer';
import { Vector as VectorSource } from 'ol/src/source';
import GeoJSON from 'ol/src/format/GeoJSON';
import { tile } from 'ol/src/loadingstrategy';
import { createXYZ } from 'ol/src/tilegrid';
import style from '@/styles/final.js';
import { toLonLat } from 'ol/src/proj';

export default class SpringsFinalLayer extends VectorLayer {
    constructor() {
        super({
            minZoom: 9,
            source: new VectorSource({
                format: new GeoJSON(),
                strategy: tile(createXYZ({
                    maxZoom: 8,
                    minZoom: 8,
                    tileSize: 1024,
                })),
                url: (extent, resolution, projection) => {
                    let from = toLonLat([extent[0], extent[1]]);
                    let to = toLonLat([extent[2], extent[3]]);

                    return '/springs.json'
                        + '?latitude_from=' + parseFloat(from[1]).toPrecision(5)
                        + '&latitude_to=' + to[1]
                        + '&longitude_from=' + from[0]
                        + '&longitude_to=' + to[0];
                }
            }),
            style: style,
            zIndex: 500,
        });
    }
}
