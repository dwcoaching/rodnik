// require('./bootstrap');

import Alpine from 'alpinejs';
import ImageBlobReduce from 'image-blob-reduce';
import { v1 as uuidv1 } from 'uuid';

import { initMap } from './map.js';

window.ImageBlobReduce = new ImageBlobReduce();
window.uuidv1 = uuidv1;
window.Alpine = Alpine;
window.initMap = initMap;

Alpine.start();



