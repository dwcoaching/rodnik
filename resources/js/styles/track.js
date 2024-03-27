import { Stroke, Style, Circle, Fill } from 'ol/style'

export default new Style({
    stroke: new Stroke({
        color: '#ff0000',
        width: 2,

    }),
    image: new Circle({
        radius: 3,
        fill: new Fill({
            color: '#ff0000',
        }),
    })
})
