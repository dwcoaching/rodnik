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

// window.color = [210, 51, 255, 1]; // pink
// window.color = [0, 153, 0, 1]; // green
window.color = [255, 102, 51, 1]; // orange
 // window.color = [51, 169, 255]; // default
 // window.color = [51, 169, 255]; // default

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
        fill: new Fill({color: [255, 180, 0, 0.95]}),
    }),
    zIndex: 60,
});

const goodWaterOuterStyle = new Style({
    image: new CircleStyle({
        radius: radius,
        fill: new Fill({color: [0, 102, 0, 0.1]}),
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
        fill: new Fill({color: [255, 0, 0, 0.1]}),
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
            font: 'bold 16px Arial',
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

// Pre-create style arrays for zero reports to avoid allocation
const reportedStyleZero = [reportedOuterStyle, reportedInnerStyle];
const goodWaterStyleZero = [goodWaterOuterStyle, goodWaterInnerStyle];
const badWaterStyleZero = [badWaterOuterStyle];

function reportedStyle(feature) {
    const hasReports = feature.get('hasReports') || 0;
    if (hasReports === 0) {
        return reportedStyleZero;
    }
    return [reportedOuterStyle, reportedInnerStyle, getTextStyle(hasReports)];
}

function goodWaterStyle(feature) {
    const hasReports = feature.get('hasReports') || 0;
    if (hasReports === 0) {
        return goodWaterStyleZero;
    }
    return [goodWaterOuterStyle, goodWaterInnerStyle, getTextStyle(hasReports)];
}

function badWaterStyle(feature) {
    const hasReports = feature.get('hasReports') || 0;
    if (hasReports === 0) {
        return badWaterStyleZero;
    }
    return [badWaterOuterStyle, getTextStyle(hasReports), badWaterInnerStyle];
}

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
