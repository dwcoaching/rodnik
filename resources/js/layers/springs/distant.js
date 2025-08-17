    import { Vector as VectorLayer } from 'ol/layer';
import { Vector as VectorSource } from 'ol/source';
import GeoJSON from 'ol/format/GeoJSON';
import { tile } from 'ol/loadingstrategy';
import { createXYZ } from 'ol/tilegrid';
import style from '@/styles/distant';
import { toLonLat, get as getProjection } from 'ol/proj';
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
                url: '/tiles/0/0/0.json'
            }),
            style: style,
            zIndex: 100,
            opacity: 0.4
        });
    }
}
