import Alpine from 'alpinejs';
import Clipboard from "@ryangjchandler/alpine-clipboard"
import ImageBlobReduce from 'image-blob-reduce';
import { v1 as uuidv1 } from 'uuid';
import OpenLayersMap from './openLayers.js';
import OpenHelper from './openHelper.js';
import OpenPicker from './openPicker.js';
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';

Alpine.plugin(Clipboard);

window.Alpine = Alpine;
window.ImageBlobReduce = new ImageBlobReduce();
window.uuidv1 = uuidv1;

window.rodnikConfig = {
    zoomLevels: {
        approximated: 6,
        final: 9
    }
};

window.initOpenLayers = function(id) {
    window.rodnikMap = new OpenLayersMap(id);
}

window.initOpenHelper = function(element, coordinates) {
    window.rodnikHelper = new OpenHelper(element, coordinates);
}

window.initOpenPicker = function(element, coordinates) {
    window.rodnikPicker = new OpenPicker(element, coordinates);
}

window.initPhotoSwipe = function(id) {
    const lightbox = new PhotoSwipeLightbox({
        gallery: id,
        children: '.photoswipeImage',
        pswpModule: () => import('photoswipe')
    });
    lightbox.init();
}

Alpine.start();

window.ymCode = 90143259;
