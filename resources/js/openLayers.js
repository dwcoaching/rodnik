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

export default class OpenLayersMap {

    constructor(elementId) {
        this.finalZoom = 9;
        this.approximatedZoom = 6;

        this.elementId = elementId;

        this.filters = {
            all: true,
            spring: true,
            water_well: true,
            water_tap: true,
            drinking_water: true,
            fountain: true,
            other: true,
        };

        this.overlays = {
            stravaPublic: false,
            osmTraces: false
        };

        this.currentOverlays = {...this.overlays};

        this.osmLayer = new OSMLayer();
        this.mapyLayer = new MapyLayer();
        this.outdoorsLayer = new OutdoorsLayer();
        this.googleTerrainLayer = new GoogleTerrainLayer();

        this.stravaPublicLayer = new StravaPublicLayer();
        this.osmTracesLayer = new OSMTracesLayer();

        this.currentLayer = this.osmLayer;

        this.springsFinalLayer = new SpringsFinalLayer();
        this.springsApproximatedLayer = new SpringsApproximatedLayer();
        this.springsDistantLayer = new SpringsDistantLayer();

        this.springsFinalSource = new SpringsFinalSource();
        this.springsUserSource = new SpringsUserSource();

        this.featureToBeSelected = null;
        this.selectedFeature = null;
        this.featureIdToBeSelected = null;

        this.view = new View({
            center: getInitialCenter(),
            zoom: getInitialZoom(),
            enableRotation: false,
        });

        this.geolocation = new Geolocation({
            trackingOptions: {
                enableHighAccuracy: true,
            },
            projection: this.view.getProjection(),
        });

        this.scaleControl = new ScaleLine({
            units: 'metric',
            bar: false,
            steps: 4,
            text: true,
            minWidth: 100,
        });

        this.fullScreenControl = new FullScreen();

        this.map = new Map({
            controls: defaultControls().extend([this.scaleControl, this.fullScreenControl]),
            target: this.elementId,
            layers: [this.osmLayer, this.springsDistantLayer, this.springsApproximatedLayer, this.springsFinalLayer],
            view: this.view,
        });

        this.map.on('moveend', (e) => {
            saveLastCenter(this.map.getView().getCenter());
            saveLastZoom(this.map.getView().getZoom());
        });

        this.map.on('click', (e) => {
            // if (this.map.getView().getZoom() < 10) {
            //     return false;
            // }

            let features = this.map.getFeaturesAtPixel(e.pixel, {
                hitTolerance: 2,
                layerFilter: function(candidate) {
                    return candidate instanceof SpringsFinalLayer;
                }
            });

            if (features.length > 0) {
                if (this.previouslySelectedFeature) {
                    if (features[0].get('id') == this.previouslySelectedFeature.get('id')) {
                        return false;
                    } else {
                        this.previouslySelectedFeature.setStyle(finalStyle);
                    }
                }

                this.selectFeature(features[0]);

                let springId = features[0].get('id');
                window.history.pushState({springId: springId}, 'Rodnik.today', window.location.origin + '/' + springId);

                const event = new CustomEvent('spring-selected', {detail: {id: springId}});
                window.dispatchEvent(event);
            } else {
                if (this.previouslySelectedFeature) {
                    this.previouslySelectedFeature.setStyle(finalStyle);
                    this.previouslySelectedFeature = null;

                    window.history.pushState({springId: null}, 'Rodnik.today', window.location.origin + '/');

                    const event = new CustomEvent('spring-unselected');
                    window.dispatchEvent(event);
                }
            }
        });
    }

    featuresLoadEnd() {
        let id = this.featureIdToBeSelected;

        if (id) {
            this.featureIdToBeSelected = null;
            this.showFeature(id);
        }
    }

    locateMe() {
        if (this.geolocation.getPosition()) {
            this.view.animate(
                {
                    center: this.geolocation.getPosition(),
                    zoom: 18,
                    duration: 250
                }
            );
        } else {
            navigator.geolocation.getCurrentPosition((position) => {
                this.view.animate(
                    {
                        center: fromLonLat([position.coords.longitude, position.coords.latitude]),
                        zoom: 18,
                        duration: 250
                    }
                );

                this.watchMe();
            }, (error) => {
                console.log(error);
            });
        }
    }

    watchMe() {
        this.geolocation.setTracking(true);

        const accuracyFeature = new Feature();
        this.geolocation.on('change:accuracyGeometry', () => {
            accuracyFeature.setGeometry(this.geolocation.getAccuracyGeometry());
        });

        this.geolocation.on('error', function (error) {
            console.log(error)
        });

        const positionFeature = new Feature();
        positionFeature.setStyle(
            new Style({
                image: new CircleStyle({
                    radius: 6,
                    fill: new Fill({
                        color: '#000000',
                    }),
                    stroke: new Stroke({
                        color: '#fff',
                        width: 2,
                    }),
                }),
            })
        );

        accuracyFeature.setStyle(
            new Style({
                fill: new Fill({
                    color: [255, 255, 255, 0.5],
                }),
                stroke: new Stroke({
                    color: '#00000',
                    width: 2,
                }),
            })
        );

        this.geolocation.on('change:position', () => {
        const coordinates = this.geolocation.getPosition();
            positionFeature.setGeometry(coordinates ? new Point(coordinates) : null);
        });

        this.geolocationLayer = new GeolocationLayer(accuracyFeature, positionFeature);

        this.map.addLayer(this.geolocationLayer);
    }

    source(name) {
        switch(name) {
            case 'osm':
                this.map.removeLayer(this.currentLayer);
                this.currentLayer = this.osmLayer;
                this.map.addLayer(this.currentLayer);
                break;
            case 'mapy':
                this.map.removeLayer(this.currentLayer);
                this.currentLayer = this.mapyLayer;
                this.map.addLayer(this.currentLayer);
                break;
            case 'outdoors' :
                this.map.removeLayer(this.currentLayer);
                this.currentLayer = this.outdoorsLayer;
                this.map.addLayer(this.currentLayer);
                break;
            case 'terrain' :
                this.map.removeLayer(this.currentLayer);
                this.currentLayer = this.googleTerrainLayer;
                this.map.addLayer(this.currentLayer);
                break;
        }
    }

    updateOverlays() {
        if (this.overlays.stravaPublic) {
            if (! this.currentOverlays.stravaPublic) {
                this.map.addLayer(this.stravaPublicLayer);
                this.currentOverlays.stravaPublic = true;
            }
        } else {
            if (this.currentOverlays.stravaPublic) {
                this.map.removeLayer(this.stravaPublicLayer);
                this.currentOverlays.stravaPublic = false;
            }
        }

        if (this.overlays.osmTraces) {
            if (! this.currentOverlays.osmTraces) {
                this.map.addLayer(this.osmTracesLayer);
                this.currentOverlays.osmTraces = true;
            }
        } else {
            if (this.currentOverlays.osmTraces) {
                this.map.removeLayer(this.osmTracesLayer);
                this.currentOverlays.osmTraces = false;
            }
        }
    }

    showFeature(id) {
        let feature = window.rodnikMap.springsFinalLayer.getSource().getFeatureById(id);

        if (feature) {
            this.locateFeature(feature);
        } else {
            this.featureIdToBeSelected = id;
        }
    }

    locateFeature(feature) {
        this.selectFeature(feature);

        this.view.animate(
            {
                center: feature.getGeometry().flatCoordinates,
                zoom: 14,
                duration: 250
            }
        );
    }

    locate(coordinates) {
        this.view.animate(
            {
                center: fromLonLat(coordinates),
                zoom: 14,
                duration: 250
            }
        );
    }

    locateWorld() {
        this.view.fit(this.view.getProjection().getExtent());
    }

    selectFeature(feature) {
        this.unselectPreviousFeature();

        this.previouslySelectedFeature = feature;
        feature.setStyle(selectedStyle);
    }

    unselectPreviousFeature() {
        if (this.previouslySelectedFeature) {
            this.previouslySelectedFeature.setStyle(finalStyle);
        }

        this.previouslySelectedFeature = null;
    }

    springsSource(userId) {
        if (userId) {
            this.mode = 'user';
            // let url = window.location.origin + '/users/' + userId;
            // window.history.pushState({userId: userId}, 'Rodnik.today', url);
            // ym(window.ymCode, 'hit', url);

            this.springsFinalLayer.setMinZoom(0);
            this.springsApproximatedLayer.setVisible(false);
            this.springsDistantLayer.setVisible(false);

            this.springsUserSource.setUser(userId);
            this.springsFinalLayer.setSource(this.springsUserSource);
        } else {
            this.mode = 'global';
            // let url = window.location.origin + '/';
            // window.history.pushState(null, 'Rodnik.today', url);
            // ym(window.ymCode, 'hit', url);

            this.springsFinalLayer.setMinZoom(9);
            this.springsApproximatedLayer.setVisible(true);
            this.springsDistantLayer.setVisible(true);

            this.springsFinalLayer.setSource(this.springsFinalSource);

        }
    }
}
