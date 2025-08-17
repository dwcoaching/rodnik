import { Circle as CircleStyle, Fill, Stroke, Style, Text } from 'ol/style';
import visible from '@/filters/visible.js'

let radius = 12;
let width = 1;

let withText = true;

let hiddenStyle = new Style({});

let style = new Style({
    image: new CircleStyle({
        radius: radius,
        fill: new Fill({color: [51, 169, 255, 0.1]}),
        stroke: new Stroke({
            color: '#33A9FF',
            width: width,
        }),
    }),
    zIndex: 5,
});

let reportedStyle = (feature) => {
    let styles = [];

    styles.push(
        new Style({
            image: new CircleStyle({
                radius: 12,
                stroke: new Stroke({
                    color: 'rgba(255, 153, 0, 1)',
                    width: 1,
                    //lineDash: [6],
                }),
                fill: new Fill({color: [255, 180, 0, 0.95]}),
            }),
            zIndex: 70,
        })
    );

    if (withText) {
        styles.push(
            new Style({
                text: new Text({
                    text: String(feature.get('hasReports') || 0),
                    font: 'bold 12px Arial',
                    fill: new Fill({
                        color: [255, 255, 255, 1]
                    }),
                    offsetY: 0
                }),
                zIndex: 100,
            }),
        );
    }

    return styles;
};

let goodWaterStyle = (feature) => {
    let styles = [];

    styles.push(
        new Style({
            image: new CircleStyle({
                radius: 12,
                fill: new Fill({color: [0, 102, 0, 0.1]}),
                stroke: new Stroke({
                    color: 'rgba(0, 102, 0, 0.66)',
                    width: 1,
                    //lineDash: [6],
                }),
                fill: new Fill({color: [0, 153, 0, 0.5]}),
            }),
            zIndex: 90,
        })
    );

    if (withText) {
        styles.push(
            new Style({
                text: new Text({
                    text: String(feature.get('hasReports') || 0),
                    font: 'bold 12px Arial',
                    fill: new Fill({
                        color: [255, 255, 255, 1]
                    }),
                    offsetY: 0,
                }),
                zIndex: 100,
            }),
        );
    }

    return styles;
};

let badWaterStyle = (feature) => {
    let styles = [];

    styles.push(
        new Style({
            image: new CircleStyle({
                radius: 12,
                fill: new Fill({color: [255, 0, 0, 0.5]}),
                stroke: new Stroke({
                    color: 'rgba(255, 0, 0, 0.66)',
                    width: 1,
                    //lineDash: [6],
                }),
            }),
            zIndex: 90,
        })
    );

    if (withText) {
        styles.push(
            new Style({
                text: new Text({
                    text: String(feature.get('hasReports') || 0),
                    font: 'bold 12px Arial',
                    fill: new Fill({
                        color: [255, 255, 255, 1]
                    }),
                    offsetY: 0,
                }),
                zIndex: 100,
            }),
        );
    }

    return styles;
};

let notFoundStyle = [
    new Style({
        image: new CircleStyle({
            radius: 12,
            //fill: new Fill({color: [255, 211, 0, 0.25]}), // 255, 211, 0
            stroke: new Stroke({
                color: [255, 0, 0, 1],
                width: 1,
                //lineDash: [6],
            }),
        }),
        zIndex: 11, // Between bad and good water
    }),
    new Style({
        text: new Text({
            text: '✕',
            font: 'bold 16px Arial',
            fill: new Fill({
                color: [255, 0, 0, 1]
            }),
            offsetY: 1
        }),
        zIndex: 10, // Same as good water outer circle
    })
];

export default (feature, resolution) => {
    if (! visible(feature)) {
        return hiddenStyle
    }

    if (feature.get('notFound') ) {
        return notFoundStyle;
    }

    if (feature.get('score') > 0) {
        return goodWaterStyle(feature);
    }

    if (feature.get('score') < 0) {
        return badWaterStyle(feature);
    }

    if (feature.get('hasReports') > 0) {
        return reportedStyle(feature);
    }

    return style;
}
