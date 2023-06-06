import { Circle as CircleStyle, Fill, Stroke, Style } from 'ol/style';

let style = new Style({
    image: new CircleStyle({
        radius: 30,
        fill: new Fill({color: [51, 169, 255, 0.33]}),
    })
});

let reportedStyle = new Style({
    image: new CircleStyle({
        radius: 20,
        fill: new Fill({color: [255, 180, 0, 0.5]}),
    })
});

export default (feature, resolution) => {
    if (feature.get('hasReports') > 0) {
        return reportedStyle;
    }

    return style;
}
