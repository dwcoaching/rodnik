import { Circle as CircleStyle, Fill, Stroke, Style } from 'ol/style';

let style = new Style({
    image: new CircleStyle({
        radius: 40,
        fill: new Fill({color: [67, 191, 225, 0.33]}),
    })
});

let reportedStyle = new Style({
    image: new CircleStyle({
        radius: 40,
        fill: new Fill({color: [255, 180, 0, 0.8]})
    })
});

export default (feature, resolution) => {
    if (feature.get('hasReports') > 0) {
        return reportedStyle;
    }

    return style;
}
