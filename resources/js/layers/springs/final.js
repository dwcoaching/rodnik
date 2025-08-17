import { Vector as VectorLayer } from 'ol/layer';
import { Vector as VectorSource } from 'ol/source';
import GeoJSON from 'ol/format/GeoJSON';
import { tile } from 'ol/loadingstrategy';
import { createXYZ } from 'ol/tilegrid';
import style from '@/styles/final.js';
import { toLonLat } from 'ol/proj';
import SphericalMercator from '@mapbox/sphericalmercator';
import SpringsFinalSource from '@/sources/final.js';

var merc = new SphericalMercator({
    antimeridian: false
});

export default class SpringsFinalLayer extends VectorLayer {
    constructor() {
        super({
            minZoom: 9,
            //source: new SpringsFinalSource(),
            style: style,
            zIndex: 500,
            opacity: 0.8
        });
    }

    updateStyle() {
        this.setStyle(style);
    }
}
