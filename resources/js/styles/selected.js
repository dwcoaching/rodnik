import { Circle as CircleStyle, Fill, Stroke, Style } from 'ol/style';

let radius = 12;
let width = 1;

let intermittentStyle = new Style({
    image: new CircleStyle({
        radius: radius,
        fill: new Fill({color: [67, 191, 225, 0.1]}),
        stroke: new Stroke({
            color: '#67BFFF',
            width: width,
            lineDash: [6],
        }),
    }),
});

let style = new Style({
    image: new CircleStyle({
        radius: radius,
        fill: new Fill({color: [255, 255, 255, 0]}),
        stroke: new Stroke({
            color: '#cc0000',
            width: 2,
            //lineDash: [6],
        }),
    })
});

export { style as default }
