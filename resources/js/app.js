import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm'
import Clipboard from "@ryangjchandler/alpine-clipboard"
import ImageBlobReduce from 'image-blob-reduce'
import { v1 as uuidv1 } from 'uuid';
import OpenLayersMap from './openLayers.js';
import OpenHelper from './openHelper.js';
import OpenDiffer from './openDiffer.js';
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';
import Coordinates from 'coordinate-parser';
import { getInitialSourceName } from '@/initial';
import { gps as exifrGPS } from 'exifr';
import sort from '@alpinejs/sort'

Alpine.plugin(Clipboard);
Alpine.plugin(sort);

window.Alpine = Alpine;
window.ImageBlobReduce = new ImageBlobReduce();
window.exifrGPS = exifrGPS;
window.uuidv1 = uuidv1;

// Dynamic HEIC converter that loads the library only when needed
window.convertHeicToJpeg = function(file) {
    return import('heic2any').then(heic2any => {
        return heic2any.default({
            blob: file,
            toType: 'image/jpeg',
            quality: 0.8
        });
    }).then(convertedBlob => {
        // heic2any returns an array if multiple outputs, single blob otherwise
        const blob = Array.isArray(convertedBlob) ? convertedBlob[0] : convertedBlob;
        // Create a new file with .jpg extension
        const newName = file.name.replace(/\.(heic|heif)$/i, '.jpg');
        return new File([blob], newName, { type: 'image/jpeg' });
    });
};

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
