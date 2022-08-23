import Alpine from 'alpinejs';
import Clipboard from "@ryangjchandler/alpine-clipboard"
import ImageBlobReduce from 'image-blob-reduce';
import { v1 as uuidv1 } from 'uuid';
import OpenLayersMap from './openLayers.js';
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';

Alpine.plugin(Clipboard);

window.Alpine = Alpine;
window.ImageBlobReduce = new ImageBlobReduce();
window.uuidv1 = uuidv1;

window.initOpenLayers = function(id) {
    window.rodnikMap = new OpenLayersMap(id);
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



