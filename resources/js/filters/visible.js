export default (feature) => {
    if (! window.rodnikMap.filters.spring && feature.get('type') == 'Spring') {
        return false
    }

    if (! window.rodnikMap.filters.water_well && feature.get('type') == 'Water well') {
        return false
    }

    if (! window.rodnikMap.filters.water_tap && feature.get('type') == 'Water tap') {
        return false
    }

    if (! window.rodnikMap.filters.drinking_water && feature.get('type') == 'Drinking water source') {
        return false
    }

    if (! window.rodnikMap.filters.fountain && feature.get('type') == 'Fountain') {
        return false
    }

    if (! window.rodnikMap.filters.other && feature.get('type') == 'Water source') {
        return false
    }

    if (window.rodnikMap.filters.confirmed && ! feature.get('waterConfirmed')) {
        return false
    }

    return true
}
