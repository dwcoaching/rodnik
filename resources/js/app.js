import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm'
import Clipboard from "@ryangjchandler/alpine-clipboard"
import ImageBlobReduce from 'image-blob-reduce'
import { v1 as uuidv1 } from 'uuid';
import OpenLayersMap from './openLayers.js';
import OpenHelper from './openHelper.js';
import OpenPicker from './openPicker.js';
import OpenDiffer from './openDiffer.js';
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';
import Coordinates from 'coordinate-parser';
import { getInitialSourceName } from '@/initial';

Alpine.plugin(Clipboard);

window.Alpine = Alpine;
window.ImageBlobReduce = new ImageBlobReduce();
window.uuidv1 = uuidv1;

window.getInitialSourceName = getInitialSourceName;

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

window.parseCoordinates = function(coordinatesString) {
    return new Coordinates(coordinatesString);
}

window.initOpenDiffer = function(element, oldCoordinates, newCoordinates) {
    window.rodnikDiffer = new OpenDiffer(element, oldCoordinates, newCoordinates);
}

window.openedPhotoswipe = null;

window.initPhotoSwipe = function(id) {
    const lightbox = new PhotoSwipeLightbox({
        gallery: id,
        children: '.photoswipeImage',
        pswpModule: () => import('photoswipe'),
        loop: false
    });
    lightbox.init();
    lightbox.on('beforeOpen', () => {
          window.openedPhotoswipe = lightbox.pswp
    })
    lightbox.on('destroy', () => {
          window.openedPhotoswipe = null
    })
}

Livewire.start()

window.ymCode = 90143259;
