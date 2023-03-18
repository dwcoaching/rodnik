import { Vector as VectorSource } from 'ol/source';
import GeoJSON from 'ol/format/GeoJSON';

export default class SpringsFinalSource extends VectorSource {
    constructor() {
        super({
            format: new GeoJSON()
        });

        this.userId = null;
        this.on('featuresloadend', (event) => {
            window.rodnikMap.featuresLoadEnd()
            window.rodnikMap.locateWorld()
        });
    }

    getUser() {
        return this.userId
    }

    setUser(userId) {
        this.userId = userId

        const url = '/users/' + parseInt(userId) + '/springs.json'
        if (this.getUrl() !== url) {
            this.setUrl(url)
            this.refresh()
        }
    }
}
