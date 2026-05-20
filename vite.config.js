import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import {homedir} from 'os';
import {resolve} from 'path';
import fs from 'fs';

let host = 'rodnik.test';

export default defineConfig({
    plugins: [
        laravel([
            'resources/css/app.css',
            'resources/js/app.js',
        ]),
    ],
    server: detectServerConfig(host),
    resolve:{
        alias:{
            '@' : path.resolve(__dirname, './resources/js')
        },
    },
    build: {
        rollupOptions: {
          output: {
            manualChunks: {
              'ol': ['ol'],
              'turf': ['@turf/helpers', '@turf/turf'],
              'exifr': ['exifr'],
              'heic': ['heic2any'],
            }
          }
        }
      }
});

function detectServerConfig(host) {
    const candidates = [
        resolve(homedir(), `Library/Application Support/Herd/config/valet/Certificates/${host}`),
        resolve(homedir(), `.config/valet/Certificates/${host}`),
    ]

    const base = candidates.find(p => fs.existsSync(`${p}.key`) && fs.existsSync(`${p}.crt`))

    if (!base) {
        return {}
    }

    const keyPath = `${base}.key`
    const certificatePath = `${base}.crt`

    return {
        hmr: {host},
        host,
        https: {
            key: fs.readFileSync(keyPath),
            cert: fs.readFileSync(certificatePath),
        },
    }
}
