require('./bootstrap');

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

window.ImageBlobReduce = require('image-blob-reduce')();
window.uuid = require('uuid');


