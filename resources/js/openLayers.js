import Map from 'ol/Map';
import Feature from 'ol/Feature';
import { Circle as CircleStyle, Fill, Stroke, Style } from 'ol/style';
import { OSM, XYZ, Vector as VectorSource} from 'ol/source';
import { Tile as TileLayer, Vector as VectorLayer } from 'ol/layer';
import { fromLonLat, toLonLat } from 'ol/proj';
import { containsCoordinate } from 'ol/extent';
import GeoJSON from 'ol/format/GeoJSON';
import { ScaleLine } from 'ol/control';
import GPX from 'ol/format/GPX';
import visible from '@/filters/visible.js'
import Buffer from '@/buffer.js'
import DragAndDrop from 'ol/interaction/DragAndDrop.js';

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
import locateByPhoto from '@/utils/locateByPhoto';

import StravaPublicLayer from '@/layers/stravaPublic';
import OSMTracesLayer from '@/layers/osmTraces';

import finalStyle from '@/styles/final';
import selectedStyle, { selectedStyle as selectionOutline } from '@/styles/selected';
import { getInitialCenter, getInitialZoom, getInitialSourceName, saveLastCenter, saveLastZoom, saveLastSourceName } from '@/initial';

import GeolocationLayer from '@/layers/geolocation';

import SpringsFinalSource from '@/sources/final.js';
import SpringsUserSource from '@/sources/user.js';
import trans from '@/i18n';
import localizedControls from '@/localizedControls';
import Tracks from './tracks.js';
import createMapView from './mapView.js';
import { captureTrackNavigationState, trackGeoJson } from './trackNavigationState.js';
import { afterMapUiReady, normalizeSharedMapState, sharedMapFilters, sharedMapPage, sharedMapSources } from './sharedMapState.js';

export default class OpenLayersMap {

    constructor(elementId, config = {}) {
        this.debug = false
        this.disposed = false;
        this.navigationRestoreGeneration = 0;
        this.trackImportGeneration = 0;
        this.sharedConfig = config;
        this.initialSharedState = config.state ? normalizeSharedMapState(config.state) : null;
        this.sharedRestoreGeneration = 0;
        this.restoringSharedState = false;
        this.preserveMapView = Boolean(this.initialSharedState);
        this.userOverviewNeedsFit = false;

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
            with_reports: false,
            along: false,
        });

        this.overlays = Alpine.reactive({
            stravaPublic: false,
            osmTraces: false
        });
        this.sourceState = Alpine.reactive({ name: 'osm' });

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
        this.reportCoordinates = {};
        this.reportSelectionFeature = new Feature();
        this.reportSelectionLayer = new VectorLayer({
            source: new VectorSource({ features: [this.reportSelectionFeature] }),
            style: selectionOutline,
            maxZoom: this.finalZoom,
            zIndex: 1000,
        });
        this.springsApproximatedLayer = new SpringsApproximatedLayer();
        this.springsDistantLayer = new SpringsDistantLayer();
        this.wateredSpringsApproximatedLayer = new WateredSpringsApproximatedLayer();
        this.wateredSpringsDistantLayer = new WateredSpringsDistantLayer();

        this.springsFinalSource = new SpringsFinalSource(() => this.featuresLoadEnd());
        this.springsUserSource = new SpringsUserSource(() => this.featuresLoadEnd());

        this.trackLayer = new TrackLayer()
        this.bufferLayer = new BufferLayer()
        this.trackSimplifiedLayer = new TrackSimplifiedLayer()

        this.featureToBeSelected = null;
        this.selectedFeature = null;
        this.featureIdToBeSelected = null;

        this.buffer = new Buffer();
        this.tracks = new Tracks({
            apply: track => this.applySharedTrack(track),
            changed: () => this.trackPersistenceChanged(),
        });
        this.sharedTrack = this.tracks.state;

        this.view = createMapView({
            center: this.initialSharedState ? fromLonLat(this.initialSharedState.center) : getInitialCenter(),
            zoom: this.initialSharedState?.zoom ?? getInitialZoom(),
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
            controls: localizedControls().extend([this.scaleControl]),
            target: this.elementId,
            layers: [
                this.wateredSpringsDistantLayer,
                this.wateredSpringsApproximatedLayer,
                this.springsDistantLayer,
                this.springsApproximatedLayer,
                this.springsFinalLayer,
                this.reportSelectionLayer,
                this.trackLayer,
                this.bufferLayer,
                this.trackSimplifiedLayer,
            ],
            view: this.view,
            moveTolerance: 5,
        });

        this.source(this.initialSharedState?.sourceName ?? getInitialSourceName())

        this.map.on('moveend', (e) => {
            if (this.queryParameters.location) {
                this.mapMoved(this.getCoordinates());
            }

            saveLastCenter(this.map.getView().getCenter());
            saveLastZoom(this.map.getView().getZoom());
            window.dispatchEvent(new CustomEvent('map-viewport-changed'));
            this.notifySharedStateChange();
        });

        this.map.on('click', (e) => {
            // if (this.queryParameters.location) {
            //     return
            // }

            let features = this.map.getFeaturesAtPixel(e.pixel, {
                hitTolerance: 2,
                layerFilter: (candidate) => {
                    return candidate instanceof SpringsFinalLayer || candidate === this.reportSelectionLayer;
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

        this.dragAndDrop = new DragAndDrop({
            formatConstructors: [GPX],
            target: this.map.getViewport()
        });

        this.dragAndDrop.on('addfeatures', (evt) => {
            this.upload(evt.file);
        });
          
        this.map.addInteraction(this.dragAndDrop);

        this.queryParameters = Alpine.reactive({
            spring: null,
            user: null,
            location: null,
            coordinates: null,
        })

        this.previousQueryParameters = JSON.parse(JSON.stringify(this.queryParameters))

        this.queryEffect = Alpine.effect(() => {
            this.queryParameters

            this.springsSource(this.queryParameters.user)

            const reportCoordinates = this.reportCoordinates[this.queryParameters.spring];
            this.reportSelectionLayer.setVisible(!this.queryParameters.user);
            this.reportSelectionFeature.setProperties({
                id: this.queryParameters.spring,
                geometry: reportCoordinates ? new Point(fromLonLat(reportCoordinates)) : null,
            });

            if (this.queryParameters.spring > 0) {
                this.highlightFeatureById(this.queryParameters.spring)
            } else {
                this.dehighlightFeature()
            }

            if (this.queryParameters.coordinates) {
                this.locate(this.queryParameters.coordinates);
                this.queryParameters.coordinates = null
            }
        })

        this.ready = afterMapUiReady().then(async () => {
            if (this.disposed || this.navigationRestoreGeneration > 0) return;
            if (this.initialSharedState) {
                await this.restoreSharedState(this.initialSharedState, { track: this.sharedConfig.track ?? null });
            } else if (this.sharedConfig.track) {
                this.loadSharedTrack(this.sharedConfig.track, { fit: true });
            } else {
                this.trackLayer.restoreFromLocalStorage();
            }
            if (!this.disposed && this.sharedConfig.localTrack) this.restoreLocalTrack(this.sharedConfig.localTrack);
        });
        this.map.once('postrender', () => {

            const viewport = this.map.getViewport()

            viewport.addEventListener('dragenter', (e) => {
                e.preventDefault()
                e.stopPropagation()
                this.showDropHint()
            })

            viewport.addEventListener('dragleave', (e) => {
                e.preventDefault()
                e.stopPropagation()
                this.hideDropHint()
            })
            
            viewport.addEventListener('dragover', (e) => {
                e.preventDefault()
                e.stopPropagation()
                this.showDropHint()
            })
            
            viewport.addEventListener('drop', async (e) => {
                e.preventDefault()
                e.stopPropagation()
                this.hideDropHint()

                if (e.dataTransfer.files.length > 0) {
                    let photo = e.dataTransfer.files.item(0)

                    locateByPhoto(photo, (result) => {
                        if (this.disposed) return;
                        this.locateWithIntelligentZoom([result.longitude, result.latitude])
                        if (! this.queryParameters.location) {
                            window.dispatchEvent(
                                new CustomEvent('duo-visit',
                                    {
                                        detail: {
                                            location: 1
                                        }
                                    }
                                )
                            )
                        }
                    })
                }
            })  
        })
    }

    showDropHint() { this.map.getTargetElement().classList.add('drop-active'); }
    hideDropHint() { this.map.getTargetElement().classList.remove('drop-active'); }

    getLayout() {
        return this.sharedConfig?.layout?.() ?? Alpine.store('mapLayout');
    }

    captureNavigationState() {
        const layout = this.getLayout();
        return {
            center: [...this.view.getCenter()],
            projection: this.view.getProjection().getCode(),
            zoom: this.view.getZoom(),
            sourceName: this.sourceState.name,
            filters: { ...this.filters },
            overlays: { ...this.overlays },
            fullscreen: layout.fullscreen,
            minimized: layout.minimized,
            track: captureTrackNavigationState(this.trackLayer.getSource(), this.view.getProjection().getCode()),
            trackReference: this.sharedTrack?.token
                ? { hash: this.sharedTrack.hash, token: this.sharedTrack.token, name: this.sharedTrack.name } : null,
        };
    }

    async restoreNavigationState(state) {
        this.trackImportGeneration++;
        this.fitSharedTrack = false;
        const generation = ++this.navigationRestoreGeneration;
        this.sharedRestoreGeneration++;
        this.restoringSharedState = false;
        this.restoringNavigationState = true;
        this.preserveMapView = true;
        this.view.cancelAnimations();
        this.source(state.sourceName);
        Object.assign(this.filters, state.filters);
        Object.assign(this.overlays, state.overlays);
        this.updateOverlays();
        Object.assign(this.getLayout(), { fullscreen: state.fullscreen, minimized: state.minimized });
        this.fullscreen = state.fullscreen;
        const features = new GeoJSON().readFeatures(state.track ?? { type: 'FeatureCollection', features: [] }, {
            dataProjection: 'EPSG:4326', featureProjection: this.view.getProjection(),
        });
        const source = this.trackLayer.getSource();
        source.clear();
        source.addFeatures(features);
        this.trackLayer.isUploaded.value = features.length > 0;
        if (features.length) this.buffer.setTrack(features);
        else this.buffer.clear();
        if (state.trackReference) this.tracks?.load(state.trackReference);
        else this.tracks?.replace(state.track);
        await Alpine.nextTick();
        if (this.disposed || generation !== this.navigationRestoreGeneration) return;
        this.map.updateSize();
        this.view.cancelAnimations();
        this.view.setCenter(state.center);
        this.view.setZoom(state.zoom);
        this.updateFilterStyles();
        this.map.renderSync();
        this.restoringNavigationState = false;
    }

    refreshSpringData() {
        if (this.disposed) return;
        this.dehighlightFeature();
        this.featureIdToBeSelected = this.queryParameters.spring || null;
        this.reportCoordinates = {};
        this.reportSelectionFeature.setGeometry(null);

        const previousFinalSource = this.springsFinalSource;
        this.springsFinalSource = new SpringsFinalSource(() => this.featuresLoadEnd());
        this.springsUserSource.invalidateCache();
        this.springsFinalLayer.setSource(this.queryParameters.user ? this.springsUserSource : this.springsFinalSource);
        previousFinalSource.dispose();

        for (const [layer, Layer] of [
            [this.springsApproximatedLayer, SpringsApproximatedLayer],
            [this.springsDistantLayer, SpringsDistantLayer],
            [this.wateredSpringsApproximatedLayer, WateredSpringsApproximatedLayer],
            [this.wateredSpringsDistantLayer, WateredSpringsDistantLayer],
        ]) {
            const previousSource = layer.getSource();
            // Fresh sources isolate late tile responses while retaining each layer's loading strategy.
            const replacement = new Layer();
            const source = replacement.getSource();
            replacement.setSource(null);
            replacement.dispose();
            layer.setSource(source);
            previousSource.dispose();
        }
    }

    refreshLocale() {
        const translations = window.rodnikMapTranslations ?? {};
        const translate = key => key.replace(/^map\./, '').split('.').reduce((value, part) => value?.[part], translations);
        this.map.getTargetElement().querySelectorAll('[data-map-i18n]').forEach(element => {
            const value = translate(element.dataset.mapI18n);
            if (typeof value === 'string') element.textContent = value;
        });
        this.map.getTargetElement().querySelectorAll('[data-map-i18n-title]').forEach(element => {
            const value = translate(element.dataset.mapI18nTitle);
            if (typeof value === 'string') element.title = value;
        });
        this.map.getControls().clear();
        localizedControls().forEach(control => this.map.addControl(control));
        this.map.addControl(this.scaleControl);
    }

    dispose() {
        this.disposed = true;
        this.sharedRestoreGeneration++;
        this.navigationRestoreGeneration++;
        Alpine.release(this.queryEffect);
        this.springsFinalSource.dispose();
        this.springsUserSource.cancelRequests();
        this.tracks.clear();
        this.buffer.clear(false);
        this.geolocation.setTracking(false);
        this.map.setTarget(null);
        this.map.dispose();
    }

    captureSharedState() {
        const layout = this.getLayout();
        return normalizeSharedMapState({
            version: 1,
            center: toLonLat(this.view.getCenter()),
            zoom: this.view.getZoom(),
            sourceName: this.sourceState.name,
            filters: sharedMapFilters(this.filters),
            overlays: { ...this.overlays },
            page: sharedMapPage(this.queryParameters),
            fullscreen: layout.fullscreen ?? this.fullscreen ?? false,
            minimized: layout.minimized ?? false,
        });
    }

    async restoreSharedState(state, { track } = {}) {
        this.trackImportGeneration++;
        this.fitSharedTrack = false;
        const saved = normalizeSharedMapState(state);
        const generation = ++this.sharedRestoreGeneration;
        this.restoringSharedState = true;
        this.preserveMapView = true;
        this.view.cancelAnimations();
        this.source(saved.sourceName);
        Object.assign(this.filters, saved.filters, {
            all: ['spring', 'water_well', 'water_tap', 'drinking_water', 'fountain', 'other'].every(key => saved.filters[key]),
        });
        Object.assign(this.overlays, saved.overlays);
        this.updateOverlays();
        Object.assign(this.getLayout(), { fullscreen: saved.fullscreen, minimized: saved.minimized });
        this.fullscreen = saved.fullscreen;
        this.previousQueryParameters = { ...saved.page };
        Object.assign(this.queryParameters, saved.page, { coordinates: null });
        if (track !== undefined) {
            this.trackLayer.clear({ persist: false });
            this.tracks.load(track);
        }
        await Alpine.nextTick();
        if (this.disposed || generation !== this.sharedRestoreGeneration) return;
        this.map.updateSize();
        this.view.cancelAnimations();
        this.view.setCenter(fromLonLat(saved.center));
        this.view.setZoom(saved.zoom);
        this.updateFilterStyles();
        this.map.renderSync();
        this.restoringSharedState = false;
        window.dispatchEvent(new CustomEvent('map-filters-changed'));
        window.dispatchEvent(new CustomEvent('map-viewport-changed'));
        window.dispatchEvent(new CustomEvent('map-shared-state-restored'));
    }

    applySharedTrack(track) {
        const safeTrack = {
            ...track,
            features: track.features.map(feature => ({
                ...feature,
                properties: feature.properties === null ? null : Object.fromEntries(
                    Object.entries(feature.properties ?? {}).filter(([key]) => key !== 'geometry'),
                ),
            })),
        };
        const features = new GeoJSON().readFeatures(safeTrack, {
            dataProjection: 'EPSG:4326', featureProjection: this.view.getProjection(),
        });
        this.trackLayer.getSource().clear();
        this.trackLayer.getSource().addFeatures(features);
        this.trackLayer.isUploaded.value = features.length > 0;
        if (features.length) {
            this.buffer.setTrack(features);
            if (this.fitSharedTrack) {
                this.view.fit(this.trackLayer.getSource().getExtent(), { padding: [60, 60, 60, 60], maxZoom: 16 });
            }
        } else this.buffer.clear();
        this.fitSharedTrack = false;
    }

    restoreLocalTrack(track) {
        this.applySharedTrack(track);
        this.tracks.replace(track);
    }

    loadSharedTrack(reference, { fit = false } = {}) {
        this.trackImportGeneration++;
        this.trackLayer.clear({ persist: false });
        this.fitSharedTrack = fit;
        return this.tracks.load(reference);
    }

    trackChanged(name = null) {
        const track = trackGeoJson(this.trackLayer.getSource().getFeatures(), this.view.getProjection());
        this.tracks.replace(track, { name });
        this.notifySharedStateChange();
    }

    trackPersistenceChanged() {
        if (this.sharedTrack?.status === 'saved') this.trackLayer.clearFromLocalStorage();
        if (this.sharedTrack?.status === 'missing' && this.filters.along) {
            this.filters.along = false;
            this.updateFilterStyles();
            window.dispatchEvent(new CustomEvent('map-filters-changed'));
        }
        window.dispatchEvent(new CustomEvent('map-track-persistence-changed'));
    }

    ensureSharedTrack() {
        return this.tracks.ensure();
    }

    retrySharedTrack() {
        return this.tracks.retry();
    }

    recoverSharedTrack(token, options) {
        return this.tracks.recoverMissing(token, options);
    }

    notifySharedStateChange() {
        if (!this.disposed && !this.restoringSharedState && !this.restoringNavigationState) window.dispatchEvent(new CustomEvent('map-state-changed'));
    }

    getCoordinates() {
        let coordinates = toLonLat(this.view.getCenter());
        coordinates[0] = coordinates[0].toFixed(6);
        coordinates[1] = coordinates[1].toFixed(6);
        return coordinates.reverse().join(', ');
    }

    getViewportBounds() {
        const size = this.map.getSize();
        if (!size || !size[0] || !size[1]) return null;

        const extent = this.view.calculateExtent(size);
        const [west, south] = toLonLat(extent.slice(0, 2));
        const [east, north] = toLonLat(extent.slice(2, 4));
        const world = this.view.getProjection().getExtent();
        const wholeWorld = extent[2] - extent[0] >= world[2] - world[0];

        return {
            west: wholeWorld ? -180 : west,
            south: Math.max(-90, south),
            east: wholeWorld ? 180 : east,
            north: Math.min(90, north),
        };
    }

    featuresLoadEnd() {
        if (this.disposed) return;
        let id = this.featureIdToBeSelected;

        if (id) {
            this.featureIdToBeSelected = null;
            this.highlightFeatureById(id);
        }

        this.fitUserOverview();
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
                if (this.disposed) return;
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
        if (this.disposed) return;
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
            alert(trans('zoom_in_to_export', 'Please zoom in to export GPX'))
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
            
            feature.setProperties({
                link: `${window.rodnikPublicBaseUrl}/${feature.getProperties().id}`
            })
            
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
            const generation = this.trackImportGeneration = (this.trackImportGeneration ?? 0) + 1;
            this.fitSharedTrack = false;
            if (file.type.startsWith('image/')) {
                locateByPhoto(file, (result) => {
                    if (this.disposed || generation !== this.trackImportGeneration) return;
                    this.locate([result.longitude, result.latitude])
                    if (! this.queryParameters.location) {
                        window.dispatchEvent(
                            new CustomEvent('duo-visit',
                                {
                                    detail: {
                                        location: 1
                                    }
                                }
                            )
                        )
                    }
                })
            } else {
                const reader = new FileReader()
                reader.readAsText(file);
                reader.onload = (e) => {
                    if (this.disposed || generation !== this.trackImportGeneration) return;
                    const content = e.target.result
                    this.trackLayer.load(content, { name: file.name?.replace(/\.gpx$/i, '') })
                }
            }
            
            
        }
    }

    source(name) {
        if (!sharedMapSources.includes(name)) name = 'osm';
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

        this.sourceState.name = name;
        saveLastSourceName(name)
        this.notifySharedStateChange();
    }

    updateFilterStyles() {
        [
            this.springsFinalLayer,
            this.springsApproximatedLayer,
            this.springsDistantLayer,
            this.wateredSpringsApproximatedLayer,
            this.wateredSpringsDistantLayer,
        ].forEach((layer) => layer.changed());
    }

    updateFilters() {
        this.updateFilterStyles();
        window.dispatchEvent(new CustomEvent('map-filters-changed'));
        this.notifySharedStateChange();
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
        this.notifySharedStateChange();
    }

    highlightFeatureById(id) {
        let feature = this.springsFinalLayer.getSource().getFeatureById(id);
        this.featureIdToBeSelected = feature ? null : id;
        if (feature) {
            this.highlightFeature(feature);
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

    locateWithIntelligentZoom(coordinates) {
        const zoom = this.view.getZoom() < this.finalZoom ? 14 : this.view.getZoom()

        this.view.animate(
            {
                center: fromLonLat(coordinates),
                zoom: zoom,
                duration: 250
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
        const extent = this.springsFinalLayer.getSource().getExtent();
        if (!extent.every(Number.isFinite)) return false;
        this.view.fit(extent);
        
        let naturalZoom = Math.floor(this.view.getZoom() - 1)
        let sensibleZoom = 8
        
        this.view.setZoom(naturalZoom > sensibleZoom ? sensibleZoom : naturalZoom);
    }

    fitUserOverview() {
        if (this.userOverviewNeedsFit && !this.preserveMapView && this.queryParameters.user
            && !this.queryParameters.spring && !this.queryParameters.location) {
            if (this.locateWorld() !== false) this.userOverviewNeedsFit = false;
        }
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

        if (this.fullscreen && !this.preserveMapView) {
            this.setFullscreen(false);
            this.locateFeature(feature);
        }
    }

    selectFeature(feature) {
        window.dispatchEvent(new CustomEvent('duo-visit', {
            detail: {
                spring: feature.get('id'),
                user: this.queryParameters.user > 0 ? this.queryParameters.user : null,
                location: null,
                preserveMapView: true,
            }
        }));
    }

    dehighlightFeature() {
        this.featureIdToBeSelected = null;
        if (this.previouslyHighlightedFeature) {
            this.previouslyHighlightedFeature.setStyle(finalStyle);
            this.previouslyHighlightedFeature = null;
        }
    }

    deselectFeature() {
        window.dispatchEvent(new CustomEvent('duo-visit', {
            detail: {
                spring: null,
                user: this.queryParameters.user > 0 ? this.queryParameters.user : null,
                preserveMapView: true,
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
                this.fitUserOverview();
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
        this.getLayout().fullscreen = value;
        this.notifySharedStateChange();
    }

    mapMoved(coordinates) {
        const event = new CustomEvent('map-moved', {detail: {coordinates: coordinates}});
        window.dispatchEvent(event);
    }

    containsCoordinates(coordinates) {
        if (!Array.isArray(coordinates) || coordinates.length !== 2 || !coordinates.every(Number.isFinite)
            || this.getLayout().minimized) return false;
        const size = this.map.getSize();
        if (!size?.[0] || !size?.[1]) return false;

        return containsCoordinate(this.view.calculateExtent(size), fromLonLat(coordinates));
    }

    duoVisit({ preserveMapView = false, preserveMapViewIfVisible = false, ...queryParameters }) {
        this.preserveMapView = Boolean(preserveMapView || this.restoringSharedState
            || (preserveMapViewIfVisible && this.containsCoordinates(queryParameters.coordinates)));
        const nextPage = { ...this.queryParameters, ...queryParameters };
        this.userOverviewNeedsFit = !this.preserveMapView && Boolean(nextPage.user)
            && !nextPage.spring && !nextPage.location
            && (nextPage.user != this.queryParameters.user || Boolean(this.queryParameters.spring || this.queryParameters.location));
        if (queryParameters.coordinates && queryParameters.spring) {
            this.reportCoordinates[queryParameters.spring] = queryParameters.coordinates;
        }
        if (this.preserveMapView) {
            queryParameters.coordinates = null;
        } else if (queryParameters.spring || queryParameters.location) {
            this.setFullscreen(false);
            this.getLayout().minimized = false;
        }

        this.previousQueryParameters = JSON.parse(JSON.stringify(this.queryParameters))
        Object.assign(this.queryParameters, queryParameters);
        this.notifySharedStateChange();
    }
}
