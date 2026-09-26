import VectorSource from 'ol/source/Vector.js';
import GeoJSON from 'ol/format/GeoJSON.js';

export default class SpringsUserSource extends VectorSource {
    constructor(onLoaded = () => window.rodnikMap?.featuresLoadEnd()) {
        const format = new GeoJSON();
        super({ format });
        this.userId = null;
        this.generation = 0;
        this.cache = new Map();
        this.request = null;
        this.on('featuresloadend', onLoaded);
        this.setLoader((extent, resolution, projection, success, failure) => {
            const userId = this.userId;
            if (!userId) {
                success([]);
                return;
            }
            const generation = this.generation;
            const apply = data => {
                if (generation !== this.generation) return;
                const features = format.readFeatures(data, { featureProjection: projection });
                this.addFeatures(features);
                success(features);
            };
            if (this.cache.has(userId)) {
                apply(this.cache.get(userId));
                return;
            }
            const request = this.request = new XMLHttpRequest();
            let finished = false;
            const fail = () => {
                if (finished) return;
                finished = true;
                if (this.request === request) this.request = null;
                this.removeLoadedExtent(extent);
                failure();
            };
            request.open('GET', `/users/${userId}/springs.json`);
            request.onload = () => {
                if (finished || generation !== this.generation) return;
                if (request.status < 200 || request.status >= 300) return fail();
                try {
                    const data = JSON.parse(request.responseText);
                    apply(data);
                    this.cache.set(userId, data);
                    if (this.cache.size > 8) this.cache.delete(this.cache.keys().next().value);
                    finished = true;
                    this.request = null;
                } catch {
                    fail();
                }
            };
            request.onerror = fail;
            request.onabort = fail;
            request.send();
        });
    }

    getUser() {
        return this.userId;
    }

    setUser(userId) {
        const value = Number(userId) || null;
        if (this.userId === value) return;
        this.cancelRequests();
        this.userId = value;
        this.refresh();
    }

    invalidateCache() {
        this.cancelRequests();
        this.cache.clear();
        this.refresh();
    }

    cancelRequests() {
        this.generation++;
        this.request?.abort();
        this.request = null;
    }
}
