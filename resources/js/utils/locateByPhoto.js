import { gps as exifrGPS } from 'exifr';

export default async function (photo, callback) {
    if (photo.type.startsWith('image/')) {
        const result = await exifrGPS(photo)
        if (result
            && result.latitude !== undefined
            && ! isNaN(result.latitude)
            && result.latitude >= -90
            && result.latitude <= 90
            && result.longitude !== undefined
            && ! isNaN(result.longitude)
            && result.latitude >= -180
            && result.latitude <= 180) {

            callback(result)
        } else {
            alert("This photo doesn't have coordinates");
        }
    }
}