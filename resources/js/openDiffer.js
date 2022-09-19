import Map from 'ol/Map';
import View from 'ol/View';
import Feature from 'ol/Feature';
import { Circle as CircleStyle, Fill, Stroke, Style } from 'ol/style';
import { OSM, XYZ, Vector as VectorSource} from 'ol/source';
import { Tile as TileLayer, Vector as VectorLayer } from 'ol/layer';
import { fromLonLat, toLonLat } from 'ol/proj';
import GeoJSON from 'ol/format/GeoJSON';
import { ScaleLine, FullScreen, defaults as defaultControls } from 'ol/control';

import { createXYZ } from 'ol/tilegrid';
import { tile } from 'ol/loadingstrategy';
import Geolocation from 'ol/Geolocation';
import Point from 'ol/geom/Point';

import OSMLayer from '@/layers/osm';
import MapyLayer from '@/layers/mapy';
import OutdoorsLayer from '@/layers/outdoors';
import GoogleTerrainLayer from '@/layers/googleTerrain';
import SpringsFinalLayer from '@/layers/springs/final';
import SpringsApproximatedLayer from '@/layers/springs/approximated';
import SpringsDistantLayer from '@/layers/springs/distant';

import StravaPublicLayer from '@/layers/stravaPublic';
import OSMTracesLayer from '@/layers/osmTraces';


import finalStyle from '@/styles/final';
import selectedStyle from '@/styles/selected';
import { getInitialCenter, getInitialZoom, saveLastCenter, saveLastZoom } from '@/initial';

import GeolocationLayer from '@/layers/geolocation';

import SpringsFinalSource from '@/sources/final.js';
import SpringsUserSource from '@/sources/user.js';

export default class OpenDiffer {
    constructor(element, oldCoordinates, newCoordinates) {
        this.osmLayer = new OSMLayer();
        this.springLayer = new VectorLayer({
            source: new VectorSource({
                features: [
                    new Feature({
                        geometry: new Point(fromLonLat(oldCoordinates)),
                        type: 'old',
                    }),
                    new Feature({
                        geometry: new Point(fromLonLat(newCoordinates)),
                        type: 'new',
                    })
                ]
            }),
            style: (feature) => {
                if (feature.get('type') == 'old') {
                    return new Style({
                        image: new CircleStyle({
                            radius: 12,
                            //fill: new Fill({color: [204, 0, 0, 0.1]}),
                            stroke: new Stroke({
                                color: [0, 0, 0, 0.33],
                                width: 2,
                            }),
                        })
                    });
                }

                if (feature.get('type') == 'new') {
                    return new Style({
                        image: new CircleStyle({
                            radius: 12,
                            //fill: new Fill({color: [0, 204, 0, 0.1]}),
                            stroke: new Stroke({
                                color: [0, 0, 0, 1],
                                width: 2,
                            }),
                        })
                    });
                }
            },
            zIndex: 500,
        });

        this.view = new View({
            center: fromLonLat(newCoordinates),
            zoom: 18,
            enableRotation: false,
        });

        this.view.fit(this.springLayer.getSource().getExtent());

        this.scaleControl = new ScaleLine({
            units: 'metric',
            bar: false,
            steps: 4,
            text: true,
            minWidth: 100,
        });

        this.map = new Map({
            controls: defaultControls().extend([this.scaleControl]),
            target: element,
            layers: [this.osmLayer, this.springLayer],
            view: this.view,
        });
    }
}
