function initMap(elementId, springs) {
    const coordinates = [55.65514, 36.71009];
    var map = L.map(elementId).setView(coordinates, 18);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    springs.forEach(spring => {
        L.marker([spring.latitude, spring.longitude]).addTo(map)
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
