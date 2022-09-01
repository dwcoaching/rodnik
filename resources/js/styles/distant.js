import { Circle as CircleStyle, Fill, Stroke, Style } from 'ol/style';

let style = new Style({
    image: new CircleStyle({
        radius: 30,
        fill: new Fill({color: [67, 191, 225, 0.15]}),
    })
});

export { style as default }
