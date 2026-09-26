import { Vector as VectorSource } from 'ol/source.js';
import GeoJSON from 'ol/format/GeoJSON.js';
import { tile } from 'ol/loadingstrategy.js';
import { createXYZ } from 'ol/tilegrid.js';
import { toLonLat } from 'ol/proj.js';
import SphericalMercator from '@mapbox/sphericalmercator';

let merc = new SphericalMercator({
    antimeridian: false
});

let zoom = 8;

export default class SpringsFinalSource extends VectorSource {
    constructor(onFeaturesLoadEnd = () => {}) {
        super({
            format: new GeoJSON(),
            strategy: tile(createXYZ({
                maxZoom: 8,
                minZoom: 8,
            })),
            url: (extent, resolution, projection) => {
                let from = toLonLat([extent[0], extent[1]]);
                let to = toLonLat([extent[2], extent[3]]);

                let xy = merc.xyz([from[0], from[1], to[0], to[1]], zoom);
                return '/tiles/' + zoom + '/' + (xy.minX) + '/' + (xy.minY) + '.json';
            }
        });

        this.on('featuresloadend', onFeaturesLoadEnd);
    }
}
