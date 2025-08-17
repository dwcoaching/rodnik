import { Circle as CircleStyle, Stroke, Style } from 'ol/style';
import finalStyleFunction from './final.js';

// Create a red stroke style that will be applied to all variants
const selectedStyle = new Style({
    image: new CircleStyle({
        radius: 12,
        stroke: new Stroke({
            color: '#8A2BE2',
            width: 4,
        }),
    }),
    zIndex: 1000, // High z-index to appear on top
});

export default (feature) => {
    const baseStyles = finalStyleFunction(feature);
    
    // If baseStyles is an array, add the red stroke to it
    if (Array.isArray(baseStyles)) {
        return [...baseStyles, selectedStyle];
    }
    
    // If baseStyles is a single style, return both
    return [baseStyles, selectedStyle];
}
