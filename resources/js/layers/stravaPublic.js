import { Tile as TileLayer } from 'ol/layer';
import { XYZ } from 'ol/source';

export default class StravaPublicLayer extends TileLayer {
    constructor() {
        super({
            source: new XYZ({
                maxZoom: 12,
                url:
                    'https://heatmap-external-{a-c}.strava.com/tiles/all/hot/{z}/{x}/{y}.png?px=256'
            }),
            zIndex: 10,
        });
    }
}
