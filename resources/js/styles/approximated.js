import { Circle as CircleStyle, Fill, Style } from 'ol/style';
import visible from '@/filters/visible.js'

let defaultRadius = 30;
let radius = 20;

let defaultTransparency = 0.5;
let transparency = 0.5;

let hiddenStyle = new Style({});

let style = new Style({
    image: new CircleStyle({
        radius: defaultRadius,
        fill: new Fill({color: [51, 169, 255, defaultTransparency]}),
    }),
    zIndex: 5,
});

const reportedStyle = new Style({
    image: new CircleStyle({
        radius: radius,
        fill: new Fill({color: [255, 180, 0, transparency]}),
    }),
    zIndex: 60,
});

const goodWaterStyle = new Style({
    image: new CircleStyle({
        radius: radius,
        fill: new Fill({color: [0, 153, 0, transparency]}),
    }),
    zIndex: 80,
});

const badWaterStyle = new Style({
    image: new CircleStyle({
        radius: radius,
        fill: new Fill({color: [255, 0, 0, transparency]}),
    }),
    zIndex: 40,
});

export default (feature) => {
    if (! visible(feature)) {
        return hiddenStyle
    }

    if (feature.get('score') > 0) {
        return goodWaterStyle;
    }

    if (feature.get('score') < 0) {
        return badWaterStyle;
    }

    if (feature.get('hasReports') > 0) {
        return reportedStyle;
    }

    return style;
}
