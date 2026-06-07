import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';
import {homedir} from 'os';
import {resolve} from 'path';
import fs from 'fs';

let host = 'rodnik.test';

export default defineConfig({
    plugins: [
        tailwindcss(),
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
            manualChunks(id) {
              if (id.includes('node_modules/heic2any')) {
                return 'heic2any';
              }

              if (id.includes('node_modules/ol')) {
                return 'ol';
              }

              if (id.includes('node_modules/@turf')) {
                return 'turf';
              }

              if (id.includes('node_modules/exifr')) {
                return 'exifr';
              }
            },
          },
        },
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
