import { defaults as defaultControls } from 'ol/control';
import trans from '@/i18n';

export default function localizedControls() {
    return defaultControls({
        zoomOptions: {
            zoomInTipLabel: trans('zoom_in', 'Zoom in'),
            zoomOutTipLabel: trans('zoom_out', 'Zoom out'),
        },
        rotateOptions: {
            tipLabel: trans('reset_rotation', 'Reset rotation'),
        },
        attributionOptions: {
            tipLabel: trans('attributions', 'Attributions'),
        },
    });
}
