function initMap(elementId, springs) {
    const coordinates = [55.65514, 37.71009];
    var map = L.map(elementId, {
        zoomAnimation: true,
        fadeAnimation: true,
        inertia: true,
        zoomSnap: 0,
        zoomDelta: 1,
        maxBoundsViscosity: 1,
        markerZoomAnimation: true,
        zoomAnimation: true,
    }).setView(coordinates, 4);

    L.control.scale().addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    springs.forEach(spring => {
        L.circleMarker([spring.latitude, spring.longitude], {
            color: '#67BFFF',
            fillColor: '#67BFFF',
            fillOpacity: 0.1,
            radius: 8,
            weight: 4,
        }).addTo(map)
        .bindPopup(spring.name)
        .on('click', function() {
            const event = new CustomEvent('spring-selected', {detail: {id: spring.id}});
            window.dispatchEvent(event);
        });
        //.openPopup();
    })
}

function initUploadMap(element, coordinates) {

    let map = L.map(element).setView(coordinates, 18);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    L.marker(coordinates).addTo(map);
}
