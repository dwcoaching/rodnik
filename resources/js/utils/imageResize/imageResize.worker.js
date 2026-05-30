import { resizeBlob } from './resizeLogic.js';

self.onmessage = async (event) => {
    const { id, blob, max } = event.data;

    try {
        const resized = await resizeBlob(blob, { max });
        self.postMessage({ id, ok: true, blob: resized });
    } catch (error) {
        self.postMessage({
            id,
            ok: false,
            error: error?.message ?? 'Failed to resize image',
        });
    }
};
