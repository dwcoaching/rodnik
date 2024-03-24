import { Circle as CircleStyle, Fill, Stroke, Style } from 'ol/style';
import visible from '@/filters/visible.js'

let radius = 12;
let width = 1;

let intermittentStyle = new Style({
    image: new CircleStyle({
        radius: radius,
        fill: new Fill({color: [51, 169, 255, 0.125]}),
        stroke: new Stroke({
            color: '#33A9FF',
            width: width,
            lineDash: [6],
        }),
    }),
});

let hiddenStyle = new Style({});

let style = new Style({
    image: new CircleStyle({
        radius: 12,
        fill: new Fill({color: [51, 169, 255, 0.1]}),
        stroke: new Stroke({
            color: '#33A9FF',
            width: 1,
            //lineDash: [6],
        }),
    }),
    zIndex: 5,
});

// window.color = [210, 51, 255, 1]; // pink
// window.color = [0, 153, 0, 1]; // green
window.color = [255, 102, 51, 1]; // orange
 // window.color = [51, 169, 255]; // default
 // window.color = [51, 169, 255]; // default

let reportedStyle = new Style({
    image: new CircleStyle({
        radius: 12,
        fill: new Fill({color: [255, 211, 0, 0.25]}), // 255, 211, 0
        stroke: new Stroke({
            color: window.color,
            width: 1,
            //lineDash: [6],
        }),
    }),
    zIndex: 10,
});

export default (feature, resolution) => {
    if (! visible(feature)) {
        return hiddenStyle
    }

    if (feature.get('hasReports') > 0) {
        return reportedStyle;
    }

    if (feature.get('intermittent') == 'yes') {
        return intermittentStyle;
    }

    return style;
}
