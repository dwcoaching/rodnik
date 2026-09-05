import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm'
import Clipboard from "@ryangjchandler/alpine-clipboard"
import { v1 as uuidv1 } from 'uuid';
import { gps as exifrGPS } from 'exifr';
import { resizeImage } from '@/utils/imageResize/resizeImage';
import OpenLayersMap from './openLayers.js';
import mapReports from './mapReports.js';
import OpenHelper from './openHelper.js';
import OpenDiffer from './openDiffer.js';
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';
import Coordinates from 'coordinate-parser';
import { getInitialSourceName } from '@/initial';
import locateByPhoto from '@/utils/locateByPhoto';
import sort from '@alpinejs/sort'
import trans from '@/i18n';

Alpine.plugin(Clipboard);
Alpine.plugin(sort);
Alpine.data('mapReports', mapReports);

window.Alpine = Alpine;
window.resizeImage = resizeImage;
window.locateByPhoto = locateByPhoto;
window.uuidv1 = uuidv1;

// Dynamic HEIC converter that loads the Vite-managed chunk only when needed.
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

window.reportCreateForm = function(config) {
    const wire = config.wire;

    return {
        visited_at: wire.$entangle('visited_at'),
        state: wire.$entangle('state'),
        quality: wire.$entangle('quality'),

        access_limited: wire.$entangle('access_limited'),
        littered: wire.$entangle('littered'),
        broken: wire.$entangle('broken'),

        sortablePhotos: wire.$entangle('sortablePhotos'),
        dragover: false,
        photoItems: (Array.isArray(config.initialPhotos) ? config.initialPhotos : []).map((photo) => ({
            ...photo,
            key: `photo-${photo.id}`,
            status: 'uploaded',
            progress: 100,
        })),
        activeUploads: 0,
        maxActiveUploads: 2,
        withDate: true,
        previousDate: null,
        today: '',

        init() {
            this.refreshToday();
            if (! config.reportId) {
                this.visited_at = this.today;
            }
            this.withDate = !! this.visited_at;

            this.$watch('state', (value) => {
                if (value === 'notfound') {
                    this.quality = null;
                    this.access_limited = false;
                    this.littered = false;
                    this.broken = false;
                }
            });

            this.syncTimezone();
        },

        localDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        },

        refreshToday() {
            this.today = this.localDate(new Date());
        },

        syncTimezone() {
            const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

            if (timezone) {
                wire.$set('timezone', timezone, false);
            }
        },

        syncDateContext() {
            this.refreshToday();
            this.syncTimezone();
        },

        toggleDate() {
            if (this.withDate) {
                this.previousDate = this.visited_at;
                this.withDate = false;
                this.visited_at = null;
            } else {
                this.visited_at = this.previousDate;
                this.withDate = true;
            }
        },

        isUploadBusy() {
            return this.photoItems.some((item) => ['queued', 'resizing', 'uploading'].includes(item.status));
        },

        isPending(item) {
            return ['queued', 'resizing', 'uploading'].includes(item.status);
        },

        buildSortablePhotos() {
            return this.photoItems
                .filter((item) => item.status === 'uploaded' && item.id)
                .map((item, index) => ({
                    value: item.id,
                    order: index + 1,
                }));
        },

        async submitReport() {
            this.syncDateContext();

            const sortablePhotos = this.buildSortablePhotos();

            this.sortablePhotos = sortablePhotos;

            if (typeof config.submitReport === 'function') {
                await config.submitReport(sortablePhotos);
                return;
            }
        },

        sortPhotos(key, position) {
            const index = this.photoItems.findIndex((photo) => photo.key === key);

            if (index < 0) {
                return;
            }

            const [item] = this.photoItems.splice(index, 1);
            this.photoItems.splice(position, 0, item);
        },

        removePhotoItem(item) {
            item.cancelled = true;

            if (item.xhr) {
                item.xhr.abort();
            }

            this.photoItems = this.photoItems.filter((photo) => photo.key !== item.key);

            if (item.previewUrl) {
                URL.revokeObjectURL(item.previewUrl);
            }

            // Removed-but-uploaded photos stay unattached on the server; a
            // background job prunes unattached photos, so nothing to delete here.
            this.processUploadQueue();
        },

        addFiles(fileList) {
            Array.from(fileList).forEach((file) => {
                const key = window.uuidv1();
                const previewUrl = URL.createObjectURL(file);

                this.photoItems.push({
                    key,
                    id: null,
                    file,
                    name: file.name,
                    oldSize: file.size,
                    newSize: null,
                    url: previewUrl,
                    previewUrl,
                    width: null,
                    height: null,
                    status: 'queued',
                    progress: 0,
                    error: null,
                    xhr: null,
                    cancelled: false,
                });
            });

            this.processUploadQueue();
        },

        processUploadQueue() {
            while (this.activeUploads < this.maxActiveUploads) {
                const item = this.photoItems.find((photo) => photo.status === 'queued' && ! photo.cancelled);

                if (! item) {
                    return;
                }

                this.activeUploads++;
                this.prepareAndUpload(item).finally(() => {
                    this.activeUploads--;
                    this.processUploadQueue();
                });
            }
        },

        async prepareAndUpload(item) {
            try {
                item.status = 'resizing';

                // Read GPS from the original file before resizing strips EXIF.
                let coords = null;
                try {
                    const gps = await exifrGPS(item.file);
                    if (gps
                        && Number.isFinite(gps.latitude) && gps.latitude >= -90 && gps.latitude <= 90
                        && Number.isFinite(gps.longitude) && gps.longitude >= -180 && gps.longitude <= 180) {
                        coords = { latitude: gps.latitude, longitude: gps.longitude };
                    }
                } catch (error) {
                    // No GPS metadata — fine, upload without coordinates.
                }

                const isHeic = item.file.type === 'image/heic'
                    || item.file.type === 'image/heif'
                    || item.file.name.toLowerCase().endsWith('.heic')
                    || item.file.name.toLowerCase().endsWith('.heif');

                let processedFile = item.file;

                if (isHeic) {
                    processedFile = await window.convertHeicToJpeg(processedFile);
                }

                if (item.cancelled) {
                    return;
                }

                let uploadFile = processedFile;

                try {
                    uploadFile = await window.resizeImage(processedFile, { max: 1280 });
                } catch (error) {
                    console.warn('Image resize failed, uploading original file instead.', error);
                }

                if (item.cancelled) {
                    return;
                }

                item.newSize = uploadFile.size;
                item.status = 'uploading';
                item.progress = 0;

                const uploaded = await this.uploadPhoto(item, uploadFile, coords);

                if (item.cancelled) {
                    return;
                }

                item.id = uploaded.id;
                if (item.previewUrl) {
                    URL.revokeObjectURL(item.previewUrl);
                    item.previewUrl = null;
                }
                item.url = uploaded.url;
                item.width = uploaded.width;
                item.height = uploaded.height;
                item.status = 'uploaded';
                item.progress = 100;
                item.file = null;
                item.xhr = null;
            } catch (error) {
                if (item.cancelled) {
                    return;
                }

                item.status = 'failed';
                item.error = error?.message || trans('upload_failed', 'Upload failed');
                item.xhr = null;
            }
        },

        uploadPhoto(item, file, coords = null) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                const formData = new FormData();
                let lastProgressAt = Date.now();
                let timedOut = false;

                formData.append('photo', file, file.name);

                if (config.reportId) {
                    formData.append('report_id', config.reportId);
                }

                if (coords) {
                    formData.append('latitude', coords.latitude);
                    formData.append('longitude', coords.longitude);
                }

                xhr.open('POST', config.uploadUrl);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-CSRF-TOKEN', config.csrfToken);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.setRequestHeader('X-Rodnik-Locale', window.rodnikLocale || 'en');

                const watchdog = window.setInterval(() => {
                    if (Date.now() - lastProgressAt > 120000) {
                        timedOut = true;
                        xhr.abort();
                        reject(new Error(trans('upload_timed_out', 'Upload timed out')));
                    }
                }, 5000);

                const cleanup = () => window.clearInterval(watchdog);

                xhr.upload.onprogress = (event) => {
                    lastProgressAt = Date.now();

                    if (event.lengthComputable) {
                        item.progress = Math.max(1, Math.round((event.loaded / event.total) * 100));
                    }
                };

                xhr.onload = () => {
                    cleanup();

                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve(JSON.parse(xhr.responseText));
                        return;
                    }

                    reject(new Error(this.extractUploadError(xhr)));
                };

                xhr.onerror = () => {
                    cleanup();
                    reject(new Error(trans('network_error', 'Network error')));
                };

                xhr.onabort = () => {
                    cleanup();
                    reject(new Error(timedOut
                        ? trans('upload_timed_out', 'Upload timed out')
                        : trans('upload_cancelled', 'Upload cancelled')));
                };

                item.xhr = xhr;
                xhr.send(formData);
            });
        },

        extractUploadError(xhr) {
            try {
                const response = JSON.parse(xhr.responseText);

                if (response.message) {
                    return response.message;
                }

                if (response.errors) {
                    return Object.values(response.errors).flat()[0] || trans('upload_failed', 'Upload failed');
                }
            } catch (error) {
                return trans('upload_failed', 'Upload failed');
            }

            return trans('upload_failed', 'Upload failed');
        },

        retryPhoto(item) {
            item.error = null;
            item.progress = 0;
            item.status = 'queued';
            item.cancelled = false;
            this.processUploadQueue();
        },

        handleFileDrop(event) {
            this.dragover = false;

            if (event.dataTransfer.files.length > 0) {
                this.addFiles(event.dataTransfer.files);
            }
        },

        handleFileSelect(event) {
            if (event.target.files.length > 0) {
                this.addFiles(event.target.files);
            }

            event.target.value = '';
        },
    };
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
        loop: false,
        closeTitle: trans('photo_close', 'Close'),
        zoomTitle: trans('photo_zoom', 'Zoom'),
        arrowPrevTitle: trans('photo_previous', 'Previous'),
        arrowNextTitle: trans('photo_next', 'Next'),
        errorMsg: trans('photo_load_error', 'The image cannot be loaded'),
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
