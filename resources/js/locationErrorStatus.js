// GeolocationPositionError codes: 1 PERMISSION_DENIED, 2 POSITION_UNAVAILABLE, 3 TIMEOUT.
export default function locationErrorStatus(error) {
    switch (error?.code) {
        case 1: return 'denied';
        case 3: return 'timeout';
        default: return 'unavailable';
    }
}
