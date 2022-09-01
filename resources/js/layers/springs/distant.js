import { Vector as VectorLayer } from 'ol/layer';
import { Vector as VectorSource } from 'ol/source';
import GeoJSON from 'ol/format/GeoJSON';
import { tile } from 'ol/loadingstrategy';
import { createXYZ } from 'ol/tilegrid';
import style from '@/styles/distant';
import { toLonLat } from 'ol/proj';
import SphericalMercator from '@mapbox/sphericalmercator';

var merc = new SphericalMercator({
    antimeridian: false
});

var zoom = 0;

export default class SpringsDistantLayer extends VectorLayer {
    constructor() {
        super({
            minZoom: 0,
            maxZoom: 6,
            source: new VectorSource({
                format: new GeoJSON(),
                strategy: tile(createXYZ({
                    maxZoom: zoom,
                    minZoom: zoom,
                })),
                url: (extent, resolution, projection) => {

                    let from = toLonLat([extent[0], extent[1]]);
                    let to = toLonLat([extent[2], extent[3]]);

                    let xy = merc.xyz([from[0], from[1], to[0], to[1]], zoom);

                    return '/tiles/' + zoom + '/' + (xy.minX) + '/' + (xy.minY) + '.json';

                    // return '/springs.json'
                    //     + '?latitude_from=' + parseFloat(from[1]).toPrecision(5)
                    //     + '&latitude_to=' + to[1]
                    //     + '&longitude_from=' + from[0]
                    //     + '&longitude_to=' + to[0]
                    //     + '&limit=1000'
                    //     + '&extent_lon_from=' + extent[0]
                    //     + '&extent_lon_to=' + extent[2]
                    //     + '&extent_lat_from=' + extent[1]
                    //     + '&extent_lat_to=' + extent[3]
                    //     + '&zoom=' + 0;
                }
            }),
            style: style,
            zIndex: 100,
        });
    }
}
