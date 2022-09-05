import { Vector as VectorSource } from 'ol/source';
import GeoJSON from 'ol/format/GeoJSON';

export default class SpringsFinalSource extends VectorSource {
    constructor() {
        super({
            format: new GeoJSON()
        });

        this.on('featuresloadend', (event) => {
            window.rodnikMap.featuresLoadEnd();
        });
    }

    setUser(userId) {
        this.setUrl('/users/' + parseInt(userId) + '/springs.json');
    }
}
