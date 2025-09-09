import { Stroke, Style, Circle, Fill, Text } from 'ol/style'

export default function(feature) {
    const label = feature.get('name') || feature.get('desc')

    return new Style({
        stroke: new Stroke({
            color: '#ff0000',
            width: 2,
        }),
        image: new Circle({
            radius: 4,
            fill: new Fill({
                color: '#ff0000',
            }),
            stroke: new Stroke({
                color: '#ffffff',
                width: 2
            })
        }),
        text: new Text({
            text: label,
            font: 'bold 12px sans-serif',
            offsetY: -10,
            fill: new Fill({
                color: '#cc0000'
            }),
            stroke: new Stroke({
                color: '#ffffff',
                width: 3
            }),
            textAlign: 'center',
            textBaseline: 'bottom'
        })
    })
}
