import { Vector as VectorLayer } from 'ol/layer';
import { Vector as VectorSource } from 'ol/source';
import GeoJSON from 'ol/format/GeoJSON';
import { tile } from 'ol/loadingstrategy';
import { createXYZ } from 'ol/tilegrid';
import style from '@/styles/final.js';
import { toLonLat } from 'ol/proj';

export default class GeolocationLayer extends VectorLayer {
    constructor(accuracyFeature, positionFeature) {
        super({
            source: new VectorSource({
                features: [accuracyFeature, positionFeature]
            }),
            zIndex: 1000
        })
    }
}
