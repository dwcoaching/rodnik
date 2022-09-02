import { Vector as VectorLayer } from 'ol/layer';
import { Vector as VectorSource } from 'ol/source';
import GeoJSON from 'ol/format/GeoJSON';
import { tile } from 'ol/loadingstrategy';
import { createXYZ } from 'ol/tilegrid';
import style from '@/styles/final.js';
import { toLonLat } from 'ol/proj';
import SphericalMercator from '@mapbox/sphericalmercator';

var merc = new SphericalMercator({
    antimeridian: false
});

export default class SpringsFinalLayer extends VectorLayer {
    constructor() {
        super({
            minZoom: 9,
            source: new VectorSource({
                format: new GeoJSON(),
                strategy: tile(createXYZ({
                    maxZoom: 8,
                    minZoom: 8,
                })),
                url: (extent, resolution, projection) => {
                    let from = toLonLat([extent[0], extent[1]]);
                    let to = toLonLat([extent[2], extent[3]]);

                    let xy = merc.xyz([from[0], from[1], to[0], to[1]], 8);
                    return '/tiles/8/' + (xy.minX) + '/' + (xy.minY) + '.json';

                    // return '/springs.json'
                    //     + '?latitude_from=' + parseFloat(from[1]).toPrecision(5)
                    //     + '&latitude_to=' + to[1]
                    //     + '&longitude_from=' + from[0]
                    //     + '&longitude_to=' + to[0]
                    //     + '&extent_lon_from=' + extent[0]
                    //     + '&extent_lon_to=' + extent[2]
                    //     + '&extent_lat_from=' + extent[1]
                    //     + '&extent_lat_to=' + extent[3]
                    //     + '&zoom=' + 8;
                }
            }),
            style: style,
            zIndex: 500,
        });

        this.getSource().on('featuresloadend', (event) => {
            window.rodnikMap.featuresLoadEnd();
        });
    }

    updateStyle() {
        this.setStyle(style);
    }
}
