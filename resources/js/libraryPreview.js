export function routePreview(points) {
    if (!Array.isArray(points)) return '';
    const coordinates = points.filter(point => Array.isArray(point) && point.length >= 2
        && Number.isFinite(point[0]) && Number.isFinite(point[1]) && Math.abs(point[0]) <= 180 && Math.abs(point[1]) <= 90);
    if (coordinates.length < 2) return '';
    const longitude = coordinates[0][0];
    const projected = coordinates.map(([lon, lat]) => {
        const latitude = Math.max(-85, Math.min(85, lat)) * Math.PI / 180;
        return [((lon - longitude + 540) % 360) - 180, Math.log(Math.tan(Math.PI / 4 + latitude / 2)) * 180 / Math.PI];
    });
    const xs = projected.map(point => point[0]);
    const ys = projected.map(point => point[1]);
    const minX = Math.min(...xs);
    const minY = Math.min(...ys);
    const width = Math.max(...xs) - minX;
    const height = Math.max(...ys) - minY;
    const scale = Math.min(76 / Math.max(width, 0.000001), 52 / Math.max(height, 0.000001));
    return projected.map(([x, y], index) => `${index ? 'L' : 'M'}${(50 + (x - minX - width / 2) * scale).toFixed(1)},${(38 - (y - minY - height / 2) * scale).toFixed(1)}`).join(' ');
}

export function libraryDate(value, locale = 'en') {
    const date = new Date(value);
    return value && Number.isFinite(date.getTime()) ? new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short', year: 'numeric' }).format(date) : '';
}

export function mapCoordinates(center) {
    return Array.isArray(center) && center.length >= 2 && center.every(Number.isFinite)
        ? `${Math.abs(center[1]).toFixed(2)}° ${center[1] < 0 ? 'S' : 'N'}, ${Math.abs(center[0]).toFixed(2)}° ${center[0] < 0 ? 'W' : 'E'}` : '';
}
