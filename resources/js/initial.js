import { fromLonLat } from 'ol/proj';

export function getInitialCenter() {
    let centerLatitude = parseFloat(localStorage.getItem('centerLatitude'));
    let centerLongitude = parseFloat(localStorage.getItem('centerLongitude'));

    return isNaN(centerLongitude) || isNaN(centerLatitude) ? fromLonLat([19.748, 49.213]) : [centerLongitude, centerLatitude];
}

export function getInitialZoom() {
    let lastZoom = parseFloat(localStorage.getItem('zoom'));

    return isNaN(lastZoom) ? 3 : lastZoom;
}

export function getInitialSourceName() {
    let source = localStorage.getItem('source')

    return source ? source : 'osm';
}

export function saveLastCenter(center) {
    localStorage.setItem('centerLongitude', center[0]);
    localStorage.setItem('centerLatitude', center[1]);
}

export function saveLastZoom(zoom) {
    localStorage.setItem('zoom', zoom);
}

export function saveLastSourceName(source) {
    localStorage.setItem('source', source);
}
