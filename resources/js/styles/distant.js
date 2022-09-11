import { Circle as CircleStyle, Fill, Stroke, Style } from 'ol/style';

let style = new Style({
    image: new CircleStyle({
        radius: 40,
        fill: new Fill({color: [67, 191, 225, 0.33]}),
    })
});

export { style as default }
