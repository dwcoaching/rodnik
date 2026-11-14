import TileLayer from 'ol/layer/Tile.js';
import XYZ from 'ol/source/XYZ.js';
import { sourceOverviewUrl } from '../../sourceOverview.js';

export default class SpringsRasterLayer extends TileLayer {
    constructor({ opacity = 0.85, onError = () => {} } = {}) {
        super({ visible: false, opacity, zIndex: 300 });
        this.overviewKey = null;
        this.onTileError = onError;
    }

    setOverview(manifest, filters) {
        const url = sourceOverviewUrl(manifest, filters);
        if (!url) return false;

        const key = JSON.stringify([manifest.revision, manifest.max_zoom, manifest.tile_size, manifest.tile_pixel_ratio, url]);
        if (key !== this.overviewKey) {
            const previousSource = this.getSource();
            const source = new XYZ({
                url,
                minZoom: 0,
                maxZoom: manifest.max_zoom,
                tileSize: manifest.tile_size / manifest.tile_pixel_ratio,
                tilePixelRatio: manifest.tile_pixel_ratio,
                transition: 0,
                wrapX: true,
            });
            source.on('tileloaderror', () => {
                if (this.getSource() === source) this.onTileError();
            });
            this.setSource(source);
            this.overviewKey = key;
            previousSource?.dispose();
        }
        this.setMaxZoom(manifest.max_zoom);
        return true;
    }

    clearOverview() {
        const source = this.getSource();
        this.setSource(null);
        this.overviewKey = null;
        source?.dispose();
    }

    disposeInternal() {
        this.clearOverview();
        super.disposeInternal();
    }
}
