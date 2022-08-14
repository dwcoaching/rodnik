import { fromLonLat } from 'ol/src/proj';

export function getInitialCenter() {
    let centerLatitude = parseFloat(localStorage.getItem('centerLatitude'));
    let centerLongitude = parseFloat(localStorage.getItem('centerLongitude'));

    return isNaN(centerLongitude) || isNaN(centerLatitude) ? fromLonLat([37.5, 55.5]) : [centerLongitude, centerLatitude];
}

export function getInitialZoom() {
    let lastZoom = parseFloat(localStorage.getItem('zoom'));

    return isNaN(lastZoom) ? 10 : lastZoom;
}

export function saveLastCenter(center) {
    localStorage.setItem('centerLongitude', center[0]);
    localStorage.setItem('centerLatitude', center[1]);
}

export function saveLastZoom(zoom) {
    localStorage.setItem('zoom', zoom);
}

