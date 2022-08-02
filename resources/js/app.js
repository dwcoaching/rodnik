import Alpine from 'alpinejs';
import ImageBlobReduce from 'image-blob-reduce';
import { v1 as uuidv1 } from 'uuid';
import OpenLayersMap from './openLayers.js';

window.Alpine = Alpine;
window.ImageBlobReduce = new ImageBlobReduce();
window.uuidv1 = uuidv1;

window.initOpenLayers = function(id) {
    window.rodnikMap = new OpenLayersMap(id);
}

Alpine.start();




