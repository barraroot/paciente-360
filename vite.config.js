import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        vue(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        cors: {
            origin: [
                /^https?:\/\/([a-z0-9-]+\.)*lvh\.me(:\d+)?$/,
                /^https?:\/\/localhost(:\d+)?$/,
                /^https?:\/\/127\.0\.0\.1(:\d+)?$/,
            ],
            credentials: true,
        },
        hmr: {
            host: 'localhost',
            protocol: 'ws',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
