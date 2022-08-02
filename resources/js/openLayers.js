import Map from 'ol/src/Map';
import View from 'ol/src/View';
import Feature from 'ol/src/Feature';
import { toStringXY } from 'ol/src/coordinate';
import {Circle as CircleStyle, Fill, Stroke, Style} from 'ol/src/style';
import {OSM, XYZ, Vector as VectorSource} from 'ol/src/source';
import {Tile as TileLayer, Vector as VectorLayer} from 'ol/src/layer';
import {Point} from 'ol/src/geom';
import { fromLonLat } from 'ol/src/proj';
import GeoJSON from 'ol/src/format/GeoJSON';
import { Select } from 'ol/src/interaction';
import {ScaleLine, defaults as defaultControls} from 'ol/src/control';

const intermittentStroke = {
    'yes': {
        'lineDash': [5]
    },
    'no': {
        'lineDash': [0]
    },
    'unknown': {
        'lineDash': [0]
    }
};

const drinkingStroke = {
    'yes': {
        color: [14, 169, 255, 1],
        width: 1,
    },
    'no': {
        color: '#888888',
        width: 1,
    },
    'unknown': {
        color: [14, 169, 255, 1],
        width: 1,
    },
    'conditional': {
        color: [14, 169, 255, 1],
        width: 1,
    }
};

const drinkingFill = {
    'yes': {
        color: [67, 191, 225, 0.1],
    },
    'no': {
        color: [100, 100, 100, 0.1],
    },
    'unknown': {
        color: [67, 191, 225, 0.1],
    },
    'conditional': {
        color: [67, 191, 225, 0.1],
    }
};

export default class OpenLayersMap {
    radius = 12;
    width  = 1;

    constructor(elementId) {
        this.elementId = elementId;

        this.tileLayer = new TileLayer({
            source: new OSM()
        });

        this.style = new Style({
            image: new CircleStyle({
                radius: this.radius,
                fill: new Fill({color: [67, 191, 225, 0.1]}),
                stroke: new Stroke({
                    color: '#67BFFF',
                    width: this.width,
                    //lineDash: [6],
                }),
            }),
        });

        this.intermittentStyle = new Style({
            image: new CircleStyle({
                radius: this.radius,
                fill: new Fill({color: [67, 191, 225, 0.1]}),
                stroke: new Stroke({
                    color: '#67BFFF',
                    width: this.width,
                    //lineDash: [6],
                }),
            }),
        });

        this.greenStyle = new Style({
            image: new CircleStyle({
                radius: this.radius,
                //fill: new Fill({color: 'black'}),
                stroke: new Stroke({
                    color: '#009900',
                    width: this.width,
                }),
            }),
        });

        this.styleSelector = (feature, resolution) => {

            if (feature.get('intermittent')) {
                return this.intermittentStyle;
            }

            return this.style;


            let strokeParameters = {
                //width: this.width
            };

            strokeParameters = {...strokeParameters, ...intermittentStroke[feature.get('intermittent')]};
            strokeParameters = {...strokeParameters, ...drinkingStroke[feature.get('drinking')]};
            let fillParameters = drinkingFill[feature.get('drinking')];

            return new Style({
                image: new CircleStyle({
                    radius: this.radius,
                    fill: new Fill(fillParameters),
                    stroke: new Stroke(strokeParameters),
                })
            });
        }

        this.selectedStyle = new Style({
            image: new CircleStyle({
                radius: this.radius,
                //fill: new Fill({color: 'black'}),
                stroke: new Stroke({
                    color: '#ff0000',
                    width: this.width,
                }),

            }),
        });

        this.vectorLayer = new VectorLayer({
            source: new VectorSource({
                format: new GeoJSON(),
                url: '/springs.json'
                // features: [
                //     new Feature({
                //         geometry: new Point(fromLonLat([37, 55])),
                //         id: 123,
                //         name: 'blabla',
                //         intermittent: 'no'
                //     }),
                //     new Feature({
                //         geometry: new Point(fromLonLat([38, 56])),
                //         id: 234,
                //         name: 'jajaja',
                //         intermittent: 'yes'
                //     })
                // ]
            }),
            style: this.styleSelector
        });

        this.view = new View({
            center: fromLonLat([34, 39.5]),
            zoom: 6
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
            layers: [this.tileLayer, this.vectorLayer],
            view: this.view,
        });

        // const select = new Select({
        //     style: new Style({
        //         image: new CircleStyle({
        //             radius: 15,
        //             //fill: new Fill({color: 'black'}),
        //             stroke: new Stroke({
        //                 color: '#ff0000',
        //                 width: 4,
        //             }),
        //         }),
        //     }),
        //     hitTolerance: 15,
        //     layers: [this.vectorLayer]
        // });

        // select.on('select', function(e) {
        //     let features = e.selected;

        //     if (features.length > 0) {
        //         const event = new CustomEvent('spring-selected', {detail: {id: features[0].values_.id}});
        //         window.dispatchEvent(event);
        //     }

        //     console.log(features);
        // })

        // this.map.addInteraction(select);

        this.map.on('click', (e) => {
            let features = this.map.getFeaturesAtPixel(e.pixel, {
                hitTolerance: 15,
                layerFilter: function(candidate) {
                    return candidate instanceof VectorLayer;
                }
            });

            if (features.length > 0) {
                if (this.previouslySelectedFeature) {
                    this.previouslySelectedFeature.setStyle(this.styleSelector(this.previouslySelectedFeature));
                }

                this.previouslySelectedFeature = features[0];

                features[0].setStyle(this.selectedStyle);

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


    // const source = new VectorSource();

    // const geoMarker = new Feature({
    //   type: 'geoMarker',
    //   geometry: new Point(toStringXY([37.41, 8.82]))
    // });

    // const vectorLayer = new VectorLayer({
    //     source: new VectorSource({
    //         features: [geoMarker],
    //     }),
    //     style: new Style({
    //         image: new CircleStyle({
    //             radius: 7,
    //             fill: new Fill({color: 'black'}),
    //             stroke: new Stroke({
    //                 color: 'white',
    //                 width: 2,
    //             }),
    //         }),
    //     })
    // });


}
