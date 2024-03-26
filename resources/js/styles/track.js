import { Stroke, Style, Circle, Fill } from 'ol/style'

export default new Style({
    stroke: new Stroke({
        color: '#000000',
        width: 2,

    }),
    image: new Circle({
        radius: 2,
        fill: new Fill({
            color: '#000000',
        }),
    })
})
