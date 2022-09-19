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

export default class OpenPicker {
    constructor(element, oldCoordinates) {
        this.osmLayer = new OSMLayer();

        let hasOldCoordinates = oldCoordinates.length ? true : false;
        let center = hasOldCoordinates ? fromLonLat(oldCoordinates) : getInitialCenter();

        if (hasOldCoordinates) {
            this.springLayer = new VectorLayer({
                source: new VectorSource({
                    features: [
                        new Feature({
                            geometry: new Point(fromLonLat(oldCoordinates)),
                        }),
                    ]
                }),
                style: new Style({
                    image: new CircleStyle({
                        radius: 12,
                        fill: new Fill({color: [0, 0, 0, 0]}),
                        stroke: new Stroke({
                            color: [0, 0, 0, 0.33],
                            width: 2,
                        }),
                    })
                }),
                zIndex: 500,
            });
        }

        this.view = new View({
            center: center,
            zoom: getInitialZoom(),
            //zoom: 18,
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
            target: element,
            layers: [this.osmLayer],
            view: this.view,
        });

        if (hasOldCoordinates) {
            this.map.addLayer(this.springLayer);
        }

        this.map.on('moveend', (e) => {
            let coordinates = toLonLat(this.view.getCenter());
            coordinates[0] = coordinates[0].toFixed(6);
            coordinates[1] = coordinates[1].toFixed(6);
            coordinates = coordinates.reverse().join(', ');

            this.mapMoved(coordinates);

            saveLastCenter(this.map.getView().getCenter());
            saveLastZoom(this.map.getView().getZoom());
        });

        this.map.on('pointerdrag', (e) => {
            let coordinates = toLonLat(this.view.getCenter());
            coordinates[0] = coordinates[0].toFixed(6);
            coordinates[1] = coordinates[1].toFixed(6);
            coordinates = coordinates.reverse().join(', ');

            this.mapMoved(coordinates);
        });

        this.map.on('click', (e) => {
            let pixel = this.map.getEventPixel(e.originalEvent);
            let coordinates = toLonLat(this.map.getCoordinateFromPixel(pixel));
            this.updateCoordinates(coordinates);
        });
    }

    updateCoordinates(coordinates) {
        let zoom = this.view.getZoom() + 1.5;
        if (zoom > 21) {
            zoom = 21;
        }

        saveLastCenter(coordinates);
        saveLastZoom(zoom);

        this.view.animate(
            {
                center: fromLonLat(coordinates),
                zoom: zoom,
                duration: 100
            }
        );
    }

    mapMoved(coordinates) {
        const event = new CustomEvent('map-moved', {detail: {coordinates: coordinates}});
        window.dispatchEvent(event);
    }
}
