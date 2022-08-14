import Map from 'ol/src/Map';
import View from 'ol/src/View';
import Feature from 'ol/src/Feature';
import { Circle as CircleStyle, Fill, Stroke, Style } from 'ol/src/style';
import { OSM, XYZ, Vector as VectorSource} from 'ol/src/source';
import { Tile as TileLayer, Vector as VectorLayer } from 'ol/src/layer';
import { fromLonLat, toLonLat } from 'ol/src/proj';
import GeoJSON from 'ol/src/format/GeoJSON';
import { ScaleLine, defaults as defaultControls } from 'ol/src/control';
import { createXYZ } from 'ol/src/tilegrid';
import { tile } from 'ol/src/loadingstrategy';

import OSMLayer from '@/layers/osm';
import MapyLayer from '@/layers/mapy';
import OutdoorsLayer from '@/layers/outdoors';
import SpringsFinalLayer from '@/layers/springs/final';
import SpringsApproximatedLayer from '@/layers/springs/approximated';
import SpringsDistantLayer from '@/layers/springs/distant';

import finalStyle from '@/styles/final';
import selectedStyle from '@/styles/selected';

export default class OpenLayersMap {

    constructor(elementId) {
        this.elementId = elementId;

        this.osmLayer = new OSMLayer();
        this.mapyLayer = new MapyLayer();
        this.outdoorsLayer = new OutdoorsLayer();

        this.springsFinalLayer = new SpringsFinalLayer();
        this.springsApproximatedLayer = new SpringsApproximatedLayer();
        this.springsDistantLayer = new SpringsDistantLayer();

        this.view = new View({
            center: fromLonLat([37.5, 55.5]),
            zoom: 10,
            enableRotation: false,
        });

        this.scaleControl = new ScaleLine({
            units: 'metric',
            bar: false,
            steps: 4,
            text: true,
            minWidth: 100,
        });

        this.map = new Map({
            controls: defaultControls().extend([this.scaleControl]),
            target: this.elementId,
            layers: [this.osmLayer, this.springsDistantLayer, this.springsApproximatedLayer, this.springsFinalLayer],
            view: this.view,
        });

        this.map.on('click', (e) => {

            if (this.previouslySelectedFeature) {
                this.previouslySelectedFeature.setStyle(finalStyle);
            }

            if (this.map.getView().getZoom() < 10) {
                return false;
            }

            let features = this.map.getFeaturesAtPixel(e.pixel, {
                hitTolerance: 2,
                layerFilter: function(candidate) {
                    return candidate instanceof SpringsFinalLayer;
                }
            });

            if (features.length > 0) {
                this.previouslySelectedFeature = features[0];
                features[0].setStyle(selectedStyle);

                const event = new CustomEvent('spring-selected', {detail: {id: features[0].get('id')}});
                window.dispatchEvent(event);
            }
        });
    }

    switchToOpenTopoMap() {
        this.tileLayer.setSource(
            new XYZ({
                url: 'https://{a-c}.tile.opentopomap.org/{z}/{x}/{y}.png'
            })
        );
    }
}
