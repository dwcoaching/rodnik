import ImageResizeWorker from './imageResize.worker.js?worker';

let worker = null;
let jobId = 0;
const pending = new Map();

function getWorker() {
    if (!worker) {
        worker = new ImageResizeWorker();

        worker.onmessage = (event) => {
            const { id, ok, blob, error } = event.data;
            const job = pending.get(id);

            if (!job) {
                return;
            }

            pending.delete(id);

            if (ok) {
                job.resolve(blob);
            } else {
                job.reject(new Error(error));
            }
        };

        worker.onerror = (event) => {
            const message = event.message || 'Image resize worker failed';
            pending.forEach((job) => job.reject(new Error(message)));
            pending.clear();
            worker = null;
        };
    }

    return worker;
}

/**
 * Resize a File or Blob off the main thread.
 *
 * @param {File|Blob} input
 * @param {{ max?: number }} options
 * @returns {Promise<File|Blob>}
 */
export function resizeImage(input, options = {}) {
    const { max = 1280 } = options;
    const blob = input;
    const id = ++jobId;

    return new Promise((resolve, reject) => {
        pending.set(id, {
            resolve: (resizedBlob) => {
                if (input instanceof File) {
                    resolve(new File([resizedBlob], input.name, { type: resizedBlob.type }));
                    return;
                }

                resolve(resizedBlob);
            },
            reject,
        });

        getWorker().postMessage({ id, blob, max });
    });
}
