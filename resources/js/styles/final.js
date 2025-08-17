import { Circle as CircleStyle, Fill, Stroke, Style, Text } from 'ol/style';
import visible from '@/filters/visible.js'

let radius = 12;
let hiddenStyle = new Style({});

let style = new Style({
    image: new CircleStyle({
        radius: radius,
        fill: new Fill({color: [51, 169, 255, 0.1]}),
        stroke: new Stroke({
            color: '#33A9FF',
            width: 1,
        }),
    }),
    zIndex: 5,
});

// Pre-create static parts of styles
const reportedOuterStyle = new Style({
    image: new CircleStyle({
        radius: radius,
        stroke: new Stroke({
            color: 'rgba(255, 153, 0, 1)',
            width: 1,
        }),
    }),
    zIndex: 70,
});

const reportedInnerStyle = new Style({
    image: new CircleStyle({
        radius: radius,
        fill: new Fill({color: [255, 180, 0, 0.8]}),
    }),
    zIndex: 60,
});

const goodWaterOuterStyle = new Style({
    image: new CircleStyle({
        radius: radius,
        stroke: new Stroke({
            color: 'rgba(0, 102, 0, 0.66)',
            width: 1,
        }),
    }),
    zIndex: 90,
});

const goodWaterInnerStyle = new Style({
    image: new CircleStyle({
        radius: radius,
        fill: new Fill({color: [0, 153, 0, 0.5]}),
    }),
    zIndex: 80,
});

const badWaterOuterStyle = new Style({
    image: new CircleStyle({
        radius: radius,
        stroke: new Stroke({
            color: '#FF0000',
            width: 1,
        }),
    }),
    zIndex: 50,
});

const badWaterInnerStyle = new Style({
    image: new CircleStyle({
        radius: radius,
        fill: new Fill({color: [255, 0, 0, 0.5]}),
    }),
    zIndex: 40,
});

const notFoundStyle = [
    new Style({
        image: new CircleStyle({
            radius: radius,
            stroke: new Stroke({
                color: [255, 0, 0, 1],
                width: 1,
            }),
        }),
        zIndex: 11,
    }),
    new Style({
        text: new Text({
            text: '✕',
            font: 'bold 13px Arial',
            fill: new Fill({
                color: [255, 0, 0, 1]
            }),
            offsetY: 1
        }),
        zIndex: 10,
    })
];

// Cache for text styles to avoid recreating them
const textStyleCache = new Map();
const whiteTextFill = new Fill({ color: [255, 255, 255, 1] });

function getTextStyle(reportCount) {
    if (!textStyleCache.has(reportCount)) {
        textStyleCache.set(reportCount, new Style({
            text: new Text({
                text: String(reportCount),
                font: 'bold 12px Arial',
                fill: whiteTextFill,
                offsetY: 0,
            }),
            zIndex: 100,
        }));
    }
    return textStyleCache.get(reportCount);
}

function reportedStyle(feature) {
    return [reportedOuterStyle, reportedInnerStyle, getTextStyle(feature.get('hasReports'))];
}

function goodWaterStyle(feature) {
    return [goodWaterOuterStyle, goodWaterInnerStyle, getTextStyle(feature.get('hasReports'))];
}

function badWaterStyle(feature) {
    return [badWaterOuterStyle, getTextStyle(feature.get('hasReports')), badWaterInnerStyle];
}

export default (feature) => {
    if (! visible(feature)) {
        return hiddenStyle
    }

    if (feature.get('notFound') > 0) {
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
