import { Circle as CircleStyle, Fill, Stroke, Style } from 'ol/src/style';

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
        radius: 12,
        fill: new Fill({color: [67, 191, 225, 0.1]}),
        stroke: new Stroke({
            color: '#67BFFF',
            width: 1,
            //lineDash: [6],
        }),
    }),
});

export { style as default }
// export default (feature, resolution) => {
//     if (feature.get('intermittent') == 'yes'
//         || feature.get('seasonal') == 'yes') {
//         return intermittentStyle;
//     }

//     return style;
// }
