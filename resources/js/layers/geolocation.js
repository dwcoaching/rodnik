import { Vector as VectorLayer } from 'ol/src/layer';
import { Vector as VectorSource } from 'ol/src/source';
import GeoJSON from 'ol/src/format/GeoJSON';
import { tile } from 'ol/src/loadingstrategy';
import { createXYZ } from 'ol/src/tilegrid';
import style from '@/styles/final.js';
import { toLonLat } from 'ol/src/proj';

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
