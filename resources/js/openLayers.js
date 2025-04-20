import Map from 'ol/Map';
import View from 'ol/View';
import Feature from 'ol/Feature';
import { Circle as CircleStyle, Fill, Stroke, Style } from 'ol/style';
import { OSM, XYZ, Vector as VectorSource} from 'ol/source';
import { Tile as TileLayer, Vector as VectorLayer } from 'ol/layer';
import { fromLonLat, toLonLat } from 'ol/proj';
import GeoJSON from 'ol/format/GeoJSON';
import { ScaleLine, defaults as defaultControls } from 'ol/control';
import GPX from 'ol/format/GPX';
import visible from '@/filters/visible.js'
import Buffer from '@/buffer.js'

import { createXYZ } from 'ol/tilegrid';
import { tile } from 'ol/loadingstrategy';
import Geolocation from 'ol/Geolocation';
import Point from 'ol/geom/Point';

import OSMLayer from '@/layers/osm';
import OpenTopoMapLayer from '@/layers/openTopoMap';
import MapyLayer from '@/layers/mapy';
import OutdoorsLayer from '@/layers/outdoors';
import GoogleTerrainLayer from '@/layers/googleTerrain';
import GoogleSatelliteLayer from '@/layers/googleSatellite';
import SpringsFinalLayer from '@/layers/springs/final';
import SpringsApproximatedLayer from '@/layers/springs/approximated';
import SpringsDistantLayer from '@/layers/springs/distant';
import WateredSpringsApproximatedLayer from '@/layers/springs/wateredApproximated';
import WateredSpringsDistantLayer from '@/layers/springs/wateredDistant';
import TrackLayer from '@/layers/tracks/track';
import BufferLayer from '@/layers/tracks/buffer';
import TrackSimplifiedLayer from '@/layers/tracks/trackSimplified';

import StravaPublicLayer from '@/layers/stravaPublic';
import OSMTracesLayer from '@/layers/osmTraces';

import finalStyle from '@/styles/final';
import selectedStyle from '@/styles/selected';
import { getInitialCenter, getInitialZoom, getInitialSourceName, saveLastCenter, saveLastZoom, saveLastSourceName } from '@/initial';

import GeolocationLayer from '@/layers/geolocation';

import SpringsFinalSource from '@/sources/final.js';
import SpringsUserSource from '@/sources/user.js';

export default class OpenLayersMap {

    constructor(elementId) {
        this.debug = false

        this.finalZoom = 9;
        this.approximatedZoom = 6;

        this.elementId = elementId;

        this.filters = Alpine.reactive({
            all: true,
            spring: true,
            water_well: true,
            water_tap: true,
            drinking_water: true,
            fountain: true,
            other: true,
            confirmed: false,
            along: false,
        });

        this.overlays = {
            stravaPublic: false,
            osmTraces: false
        };

        this.currentOverlays = {...this.overlays};

        this.osmLayer = new OSMLayer();
        this.mapyLayer = new MapyLayer();
        this.outdoorsLayer = new OutdoorsLayer();
        this.openTopoMapLayer = new OpenTopoMapLayer();
        this.googleTerrainLayer = new GoogleTerrainLayer();
        this.googleSatelliteLayer = new GoogleSatelliteLayer();

        this.stravaPublicLayer = new StravaPublicLayer();
        this.osmTracesLayer = new OSMTracesLayer();

        this.currentLayer = this.osmLayer;

        this.springsFinalLayer = new SpringsFinalLayer();
        this.springsApproximatedLayer = new SpringsApproximatedLayer();
        this.springsDistantLayer = new SpringsDistantLayer();
        this.wateredSpringsApproximatedLayer = new WateredSpringsApproximatedLayer();
        this.wateredSpringsDistantLayer = new WateredSpringsDistantLayer();

        this.springsFinalSource = new SpringsFinalSource();
        this.springsUserSource = new SpringsUserSource();

        this.trackLayer = new TrackLayer()
        this.bufferLayer = new BufferLayer()
        this.trackSimplifiedLayer = new TrackSimplifiedLayer()

        this.featureToBeSelected = null;
        this.selectedFeature = null;
        this.featureIdToBeSelected = null;

        this.buffer = new Buffer();

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

        this.map = new Map({
            controls: defaultControls().extend([this.scaleControl]),
            target: this.elementId,
            layers: [
                this.wateredSpringsDistantLayer,
                this.wateredSpringsApproximatedLayer,
                this.springsDistantLayer,
                this.springsApproximatedLayer,
                this.springsFinalLayer,
                this.trackLayer,
                this.bufferLayer,
                this.trackSimplifiedLayer,
            ],
            view: this.view,
            moveTolerance: 5,
        });

        this.source(getInitialSourceName())

        this.map.on('moveend', (e) => {
            if (this.queryParameters.location) {
                this.mapMoved(this.getCoordinates());
            }

            saveLastCenter(this.map.getView().getCenter());
            saveLastZoom(this.map.getView().getZoom());
        });

        this.map.on('click', (e) => {
            // if (this.queryParameters.location) {
            //     return
            // }

            let features = this.map.getFeaturesAtPixel(e.pixel, {
                hitTolerance: 2,
                layerFilter: function(candidate) {
                    return candidate instanceof SpringsFinalLayer;
                }
            });

            if (features.length > 0) {
                this.selectFeature(features[0])
            } else {
                this.deselectFeature()
            }
        });

        this.map.on('pointerdrag', (e) => {
            if (this.queryParameters.location) {
                this.mapMoved(this.getCoordinates());
            }
        });

        this.queryParameters = Alpine.reactive({
            springId: null,
            userId: null,
            location: false,
            coordinates: null,
        })

        this.previousQueryParameters = JSON.parse(JSON.stringify(this.queryParameters))

        Alpine.effect(() => {
            this.queryParameters

            this.springsSource(this.queryParameters.userId)

            if (this.queryParameters.springId > 0) {
                this.highlightFeatureById(this.queryParameters.springId)
            } else {
                this.dehighlightFeature()
            }

            if (this.queryParameters.coordinates) {
                this.locate(this.queryParameters.coordinates);
                this.queryParameters.coordinates = null
            }
        })
    }

    getCoordinates() {
        let coordinates = toLonLat(this.view.getCenter());
        coordinates[0] = coordinates[0].toFixed(6);
        coordinates[1] = coordinates[1].toFixed(6);
        return coordinates.reverse().join(', ');
    }

    featuresLoadEnd() {
        let id = this.featureIdToBeSelected;

        if (id) {
            this.featureIdToBeSelected = null;
            this.highlightFeatureById(id);
        }

        if (! this.queryParameters.springId && this.queryParameters.userId && this.queryParameters.userId != this.previousQueryParameters.userId) {
            this.locateWorld()
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

    download() {
        if (this.view.getZoom() < 9
            && this.springsFinalLayer.getSource() instanceof SpringsFinalSource) {
            alert('Please zoom in to export GPX')
            return false
        }

        const extent = this.map.getView().calculateExtent(this.map.getSize())

        const features = this.springsFinalLayer.getSource().getFeaturesInExtent(extent)
        const renamedFeatures = features.map((feature) => {
            if (! feature.getProperties().name) {
                feature.setProperties({
                    name: feature.getProperties().type
                })
            }
            return feature
        })

        const visibleFeatures = features.filter((feature) => {
            return visible(feature)
        })

        const gpx = new GPX()
        const gpxString = gpx.writeFeatures(visibleFeatures, {
            dataProjection: 'EPSG:4326', // GPX standard projection
            featureProjection: this.map.getView().getProjection() // Your map's projec
        })

        const blob = new Blob([`<?xml version="1.0" encoding="utf-8"?>\n${gpxString}`], { type: 'application/gpx+xml;charset=utf-8;' })
        const url = URL.createObjectURL(blob)

        const date = (new Date()).toISOString().slice(0, 19).replace('T', '--').replaceAll(':', '-');
        const link = document.createElement('a')
        link.href = url
        link.download = `rodnik-${date}.gpx`
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
    }

    upload(file) {
        if (file) {
            const reader = new FileReader()
            reader.readAsText(file);
            reader.onload = (e) => {
                const content = e.target.result
                this.trackLayer.getSource().setFromGPXString(content)

                if (this.trackLayer.getSource().getFeatures().length) {
                    this.view.fit(this.trackLayer.getSource().getExtent())
                    this.view.setZoom(this.view.getZoom() - 0.5);

                    // window.rodnikMap.filters.along = true
                }
            }
        }
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
              case 'openTopoMap' :
                this.map.removeLayer(this.currentLayer);
                this.currentLayer = this.openTopoMapLayer;
                this.map.addLayer(this.currentLayer);
                break;
            case 'terrain' :
                this.map.removeLayer(this.currentLayer);
                this.currentLayer = this.googleTerrainLayer;
                this.map.addLayer(this.currentLayer);
                break;
            case 'satellite' :
                this.map.removeLayer(this.currentLayer);
                this.currentLayer = this.googleSatelliteLayer;
                this.map.addLayer(this.currentLayer);
                break;
        }

        saveLastSourceName(name)
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

    highlightFeatureById(id) {
        let feature = window.rodnikMap.springsFinalLayer.getSource().getFeatureById(id);
        if (feature) {
            this.highlightFeature(feature);
        } else {
            this.featureIdToBeSelected = id;
        }
    }

    locateFeature(feature) {
        this.view.animate(
            {
                center: feature.getGeometry().flatCoordinates,
                duration: 250
            }
        );
    }

    locate(coordinates) {
        // const zoom = this.view.getZoom() < this.finalZoom ? 14 : this.view.getZoom()
        const zoom = 14

        this.view.animate(
            {
                center: fromLonLat(coordinates),
                zoom: zoom,
                duration: 250
            }
        );
    }

    locateWithZoom(coordinates) {
        let zoom = this.view.getZoom();

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

    zoom(zoom) {
        saveLastZoom(zoom);

        this.view.animate(
            {
                zoom: zoom,
                duration: 100
            }
        );
    }

    locateWorld() {
        this.view.fit(this.springsFinalLayer.getSource().getExtent());
        
        let naturalZoom = Math.floor(this.view.getZoom() - 1)
        let sensibleZoom = 8
        
        this.view.setZoom(naturalZoom > sensibleZoom ? sensibleZoom : naturalZoom);
    }

    highlightFeature(feature) {
        if (this.previouslyHighlightedFeature) {
            if (feature.get('id') == this.previouslyHighlightedFeature.get('id')) {
                return false;
            } else {
                this.previouslyHighlightedFeature.setStyle(finalStyle);
            }
        }

        this.dehighlightPreviousFeature();

        this.previouslyHighlightedFeature = feature;
        feature.setStyle(selectedStyle);

        if (this.fullscreen) {
            this.setFullscreen(false);
            this.locateFeature(feature);
        }
    }

    selectFeature(feature) {
        window.dispatchEvent(new CustomEvent('duo-visit', {
            detail: {
                springId: feature.get('id'),
                userId: this.queryParameters.userId,
                location: false,
            }
        }));
    }

    dehighlightFeature() {
        if (this.previouslyHighlightedFeature) {
            this.previouslyHighlightedFeature.setStyle(finalStyle);
            this.previouslyHighlightedFeature = null;
        }
    }

    deselectFeature() {
        window.dispatchEvent(new CustomEvent('duo-visit', {
            detail: {
                springId: 0,
                userId: this.queryParameters.userId,
            }
        }));
    }

    dehighlightPreviousFeature() {
        if (this.previouslyHighlightedFeature) {
            this.previouslyHighlightedFeature.setStyle(finalStyle);
        }

        this.previouslyHighlightedFeature = null;
    }

    springsSource(userId) {
        if (userId > 0) {
            this.mode = 'user';

            this.springsFinalLayer.setMinZoom(0);
            this.springsApproximatedLayer.setVisible(false);
            this.springsDistantLayer.setVisible(false);
            this.wateredSpringsApproximatedLayer.setVisible(false);
            this.wateredSpringsDistantLayer.setVisible(false);

            if (this.springsUserSource.getUser() == userId) {
                this.springsFinalLayer.setSource(this.springsUserSource)
                if (! this.queryParameters.springId && this.previousQueryParameters.userId != userId) {
                    this.locateWorld()
                }
            } else {
                this.springsUserSource.setUser(userId);
                this.springsFinalLayer.setSource(this.springsUserSource);
            }
        } else {
            this.mode = 'global';

            this.springsFinalLayer.setMinZoom(9);
            this.springsApproximatedLayer.setVisible(true);
            this.springsDistantLayer.setVisible(true);
            this.wateredSpringsApproximatedLayer.setVisible(true);
            this.wateredSpringsDistantLayer.setVisible(true);

            this.springsFinalLayer.setSource(this.springsFinalSource);
        }
    }

    setFullscreen(value) {
        this.fullscreen = value;
    }

    mapMoved(coordinates) {
        const event = new CustomEvent('map-moved', {detail: {coordinates: coordinates}});
        window.dispatchEvent(event);
    }

    duoVisit(queryParameters) {
        this.previousQueryParameters = JSON.parse(JSON.stringify(this.queryParameters))
        Object.assign(this.queryParameters, queryParameters);
    }
}
