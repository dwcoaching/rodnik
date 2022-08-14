import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel([
            'resources/css/app.css',
            'resources/js/app.js',
        ]),
    ],
    resolve:{
        alias:{
            '@' : path.resolve(__dirname, './resources/js')
        },
    },
});
