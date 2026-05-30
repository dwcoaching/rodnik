import pica from 'pica';

const resizer = pica();

function dimensionsFor(imageBitmap, max) {
    const scale = Math.min(1, max / Math.max(imageBitmap.width, imageBitmap.height));

    return {
        width: Math.max(Math.round(imageBitmap.width * scale), 1),
        height: Math.max(Math.round(imageBitmap.height * scale), 1),
    };
}

function outputMimeType(blob) {
    return blob.type === 'image/png' ? 'image/png' : 'image/jpeg';
}

async function resizeWithPica(imageBitmap, blob, dimensions) {
    const canvas = new OffscreenCanvas(dimensions.width, dimensions.height);

    await resizer.resize(imageBitmap, canvas, {
        alpha: blob.type === 'image/png',
    });

    return resizer.toBlob(canvas, outputMimeType(blob), 0.8);
}

async function resizeWithNativeCanvas(imageBitmap, blob, dimensions) {
    const canvas = new OffscreenCanvas(dimensions.width, dimensions.height);
    const context = canvas.getContext('2d');

    context.drawImage(imageBitmap, 0, 0, dimensions.width, dimensions.height);

    return canvas.convertToBlob({
        type: outputMimeType(blob),
        quality: 0.8,
    });
}

/**
 * Resize an image blob so its longest edge is at most `max` pixels.
 * Intended to run inside a Web Worker (uses OffscreenCanvas + createImageBitmap).
 */
export async function resizeBlob(blob, { max = 1280 } = {}) {
    const imageBitmap = await createImageBitmap(blob, { imageOrientation: 'from-image' });

    try {
        const dimensions = dimensionsFor(imageBitmap, max);

        try {
            return await resizeWithPica(imageBitmap, blob, dimensions);
        } catch (error) {
            return resizeWithNativeCanvas(imageBitmap, blob, dimensions);
        }
    } finally {
        imageBitmap.close();
    }
}
